<?php
/**
 * api.php
 *
 * Talks to ESPN's public site API using only PHP's built-in cURL
 * extension (no external libraries).
 */

define('ESPN_BASE', 'http://site.api.espn.com/apis/site/v2/sports');

/**
 * Fetch several JSON API URLs in parallel using curl_multi so a page
 * with several teams doesn't wait on each request one at a time.
 */
function fetch_json_multi(array $urls, ?callable $log = null): array
{
    if (empty($urls)) {
        return [];
    }

    $mh = curl_multi_init();
    $handles = [];

    foreach ($urls as $key => $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; LocalSportsSite/1.0)',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            // Follow http->https (and any other) redirects. ESPN's endpoints
            // are requested over http:// in a few places; if the server ever
            // 301/302s to https, without this the response would be a redirect
            // body with a non-200 code and get discarded as a failure.
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$key] = $ch;
    }

    $running = null;
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) {
            curl_multi_select($mh);
        }
    } while ($running > 0 && $status === CURLM_OK);

    $results = [];
    foreach ($handles as $key => $ch) {
        $body     = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errNo    = curl_errno($ch);
        $errMsg   = curl_error($ch);
        $decoded  = ($body && $httpCode === 200) ? json_decode($body, true) : null;
        $results[$key] = $decoded;

        if ($log) {
            $log(sprintf(
                'fetch key=%s http=%d bodyBytes=%d curlErr=%d%s decodedOk=%s',
                $key,
                $httpCode,
                is_string($body) ? strlen($body) : 0,
                $errNo,
                $errMsg ? " (\"$errMsg\")" : '',
                $decoded === null ? 'NO' : 'yes'
            ));
        }

        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);

    return $results;
}

/**
 * Build the ESPN schedule URL for a team.
 */
/**
 * Turn a $MAJOR_EVENTS entry's 'window_months' into an actual
 * YYYYMMDD-YYYYMMDD range for the current year (ESPN's scoreboard
 * endpoint only returns "today" without a dates range, so without
 * this, postseason games sitting outside the current window would
 * never show up at all).
 *
 * Handles wraparound for championships whose window crosses into the
 * following January relative to when their season started (e.g. the
 * Super Bowl, played in Feb of the year *after* the season's fall
 * start) via 'spans_new_year'.
 */
function major_event_dates_range(array $months, bool $spansNewYear): string
{
    sort($months);
    $firstMonth = $months[0];
    $lastMonth  = end($months);

    $now  = (int) date('Y');
    $prev = $now - 1;

    if ($spansNewYear) {
        // e.g. months = [1, 2] means "Jan-Feb of this year" for a
        // season that started last fall; also include next Jan-Feb in
        // case we're currently in the fall portion of the same season.
        $startYear = $now;
        $endYear   = $now;
    } else {
        $startYear = $now;
        $endYear   = $now;
    }

    $start = sprintf('%04d%02d01', $startYear, $firstMonth);
    $lastDay = (int) date('t', mktime(0, 0, 0, $lastMonth, 1, $endYear));
    $end   = sprintf('%04d%02d%02d', $endYear, $lastMonth, $lastDay);

    return "{$start}-{$end}";
}

function schedule_url(string $sport, string $league, string $abbr): string
{
    return ESPN_BASE . "/{$sport}/{$league}/teams/{$abbr}/schedule";
}

/**
 * Build an ESPN scoreboard URL for a whole league, optionally scoped to
 * a postseason window via a date range (YYYYMMDD-YYYYMMDD) so we don't
 * have to guess week numbers. A wide range is fine — the scoreboard
 * endpoint just returns whatever games fall inside it.
 */
function scoreboard_url(string $sport, string $league, ?string $datesRange = null): string
{
    $url = ESPN_BASE . "/{$sport}/{$league}/scoreboard?limit=100";
    if ($datesRange) {
        $url .= "&dates={$datesRange}";
    }
    return $url;
}

/**
 * From a decoded scoreboard payload, find the single game that looks
 * like "the championship" for that league. The checks that must pass:
 *
 *   1. The game must be completed.
 *   2. If $requiresPostseason is true, the event's season type must be
 *      postseason (ESPN's season.type === 3). This is the important
 *      guard for US leagues — without it, a plain "any completed game
 *      on today's scoreboard" search will happily grab a random
 *      regular-season game (e.g. an MLB game completed today in July)
 *      and mislabel it as "the World Series." Single-elimination
 *      international tournaments (World Cup, Champions League) don't
 *      reliably expose the same season-type field in ESPN's soccer
 *      payloads, so callers pass false for those and rely on keyword
 *      matching alone — appropriate since essentially every match in
 *      those competitions is part of a knockout/group stage anyway,
 *      and "final" in the title is the meaningful signal.
 *   3. The event's name/shortName/notes headline should match one of
 *      the given title keywords (e.g. "World Series", "Super Bowl"),
 *      to distinguish the actual final from an earlier round that's
 *      also completed (and, for US leagues, also postseason).
 *
 * If nothing qualifies yet (e.g. mid regular season), this correctly
 * returns null rather than guessing.
 *
 * Falls back to the most recent completed game that passed the
 * postseason gate (when applicable) if no keyword match is found, so
 * a naming convention change doesn't silently produce nothing.
 */
function find_championship_game(?array $scoreboardData, array $titleKeywords, bool $requiresPostseason = true): ?array
{
    if (!$scoreboardData || empty($scoreboardData['events'])) {
        return null;
    }

    $candidates = [];
    foreach ($scoreboardData['events'] as $event) {
        $competition   = $event['competitions'][0] ?? null;
        $completedFlag = $competition['status']['type']['completed'] ?? false;
        if (!$competition || !$completedFlag) {
            continue;
        }

        if ($requiresPostseason) {
            // Require postseason. ESPN represents this a couple of
            // different ways depending on sport/endpoint, so check
            // what's available rather than assuming one exact shape.
            $seasonType = $event['season']['type']
                ?? $scoreboardData['season']['type']
                ?? null;
            $seasonSlug = strtolower($event['season']['slug'] ?? $scoreboardData['season']['slug'] ?? '');

            $isPostseason = ($seasonType === 3 || $seasonType === '3' || $seasonSlug === 'post-season' || $seasonSlug === 'postseason');

            if (!$isPostseason) {
                continue;
            }
        }

        $candidates[] = $event;
    }

    if (empty($candidates)) {
        return null;
    }

    usort($candidates, function ($a, $b) {
        return strtotime($b['date'] ?? 'now') <=> strtotime($a['date'] ?? 'now');
    });

    // Prefer a game whose name/shortName/notes headline matches a known
    // championship title (handles leagues/tournaments that keep other
    // rounds in the same scoreboard window).
    foreach ($candidates as $event) {
        $haystack = strtolower(
            ($event['name'] ?? '') . ' ' .
            ($event['shortName'] ?? '') . ' ' .
            (($event['competitions'][0]['notes'][0]['headline'] ?? '') ?? '')
        );
        foreach ($titleKeywords as $keyword) {
            if (str_contains($haystack, strtolower($keyword))) {
                return $event;
            }
        }
    }

    // No keyword requires an exact match to be meaningful when we
    // couldn't gate on postseason at all — in that case, don't guess.
    if (!$requiresPostseason) {
        return null;
    }

    // Fallback: most recent completed postseason game overall (still
    // gated by the postseason check above — never a regular-season game).
    return $candidates[0];
}

/**
 * Turn a championship scoreboard event into the same simple shape the
 * regional game cards use, but from a neutral (no home team) point of
 * view since there's no "our team" for a nationally-watched final.
 */
function summarize_championship_game(array $event, string $eventLabel): ?array
{
    $competition = $event['competitions'][0] ?? null;
    if (!$competition || empty($competition['competitors'])) {
        return null;
    }

    $competitors = $competition['competitors'];
    if (count($competitors) < 2) {
        return null;
    }

    // ESPN puts the winner first in some sports and marks homeAway
    // otherwise; sort so the winner (if decided) displays first.
    usort($competitors, function ($a, $b) {
        $aWin = !empty($a['winner']) ? 1 : 0;
        $bWin = !empty($b['winner']) ? 1 : 0;
        return $bWin <=> $aWin;
    });

    $teamA = $competitors[0];
    $teamB = $competitors[1];

    $scoreA = extract_score($teamA['score'] ?? null);
    $scoreB = extract_score($teamB['score'] ?? null);

    $statusName = strtoupper($competition['status']['type']['name'] ?? '');
    $isFinal    = !in_array($statusName, NON_FINAL_STATUS_NAMES, true);

    $headline = $competition['notes'][0]['headline'] ?? ($event['name'] ?? $eventLabel);

    return [
        'event_label'   => $eventLabel,
        'headline'      => $headline,
        'is_final'      => $isFinal,
        'team_a'        => $teamA['team']['displayName'] ?? ($teamA['team']['name'] ?? 'Team A'),
        'team_a_score'  => $scoreA ?? '?',
        'team_a_winner' => !empty($teamA['winner']),
        'team_b'        => $teamB['team']['displayName'] ?? ($teamB['team']['name'] ?? 'Team B'),
        'team_b_score'  => $scoreB ?? '?',
        'team_b_winner' => !empty($teamB['winner']),
        'date_str'      => isset($event['date']) ? date('M j, Y', strtotime($event['date'])) : '',
        'date_raw'      => $event['date'] ?? '',
    ];
}

/**
 * From a decoded schedule payload, pull the most recent $count
 * completed games, newest first.
 */
function last_completed_games(?array $scheduleData, int $count = 2): array
{
    if (!$scheduleData || empty($scheduleData['events'])) {
        return [];
    }

    $completed = [];
    foreach ($scheduleData['events'] as $event) {
        $competition   = $event['competitions'][0] ?? null;
        $completedFlag = $competition['status']['type']['completed'] ?? false;
        if ($competition && $completedFlag) {
            $completed[] = $event;
        }
    }

    usort($completed, function ($a, $b) {
        return strtotime($b['date'] ?? 'now') <=> strtotime($a['date'] ?? 'now');
    });

    return array_slice($completed, 0, $count);
}

/**
 * Find the next upcoming game within the next 2 weeks.
 */
function get_upcoming_game(?array $scheduleData, string $teamAbbr): ?array
{
    if (!$scheduleData || empty($scheduleData['events'])) {
        return null;
    }

    $upcoming = [];
    $now = time();
    $twoWeeksFromNow = strtotime('+2 weeks');

    foreach ($scheduleData['events'] as $event) {
        $competition   = $event['competitions'][0] ?? null;
        $completedFlag = $competition['status']['type']['completed'] ?? false;
        
        // If not completed, it's a future or live game
        if ($competition && !$completedFlag) {
            $gameTime = strtotime($event['date'] ?? '+1 year');
            if ($gameTime > $now && $gameTime <= $twoWeeksFromNow) {
                $upcoming[] = $event;
            }
        }
    }

    if (empty($upcoming)) {
        return null;
    }

    // Sort to find the soonest game
    usort($upcoming, function ($a, $b) {
        return strtotime($a['date'] ?? 'now') <=> strtotime($b['date'] ?? 'now');
    });

    $event = $upcoming[0];
    $competition = $event['competitions'][0];
    
    $team = null;
    $opponent = null;
    foreach ($competition['competitors'] as $competitor) {
        $competitorAbbr = strtolower($competitor['team']['abbreviation'] ?? '');
        if ($competitorAbbr === strtolower($teamAbbr)) {
            $team = $competitor;
        } else {
            $opponent = $competitor;
        }
    }

    if (!$team || !$opponent) {
        return null;
    }

    return [
        'opponent'   => $opponent['team']['displayName'] ?? ($opponent['team']['name'] ?? 'Opponent'),
        'is_home'    => ($team['homeAway'] ?? '') === 'home',
        'date_str'   => isset($event['date']) ? date('M j, g:i A', strtotime($event['date'])) : '',
        'date_raw'   => $event['date'] ?? '', // Passed through for debugging
    ];
}

/**
 * Statuses ESPN uses for games that finished without a normal win/loss
 * (postponed/suspended/canceled). last_completed_games() already filters
 * to status.type.completed === true, but a suspended-and-later-resumed
 * game can still show one of these names on a "completed" event, so we
 * check explicitly rather than assuming completed always means a clean
 * final score.
 */
const NON_FINAL_STATUS_NAMES = ['STATUS_POSTPONED', 'STATUS_CANCELED', 'STATUS_SUSPENDED', 'STATUS_DELAYED'];

/**
 * Turn a single ESPN "event" into a simple result array from the given
 * team's point of view. Returns null if the shape isn't what we expect.
 *
 * 'status' is one of: 'win', 'loss', 'tie', 'postponed'.
 */
function summarize_game(array $event, string $teamAbbr): ?array
{
    $competition = $event['competitions'][0] ?? null;
    if (!$competition || empty($competition['competitors'])) {
        return null;
    }

    $team     = null;
    $opponent = null;
    foreach ($competition['competitors'] as $competitor) {
        $competitorAbbr = strtolower($competitor['team']['abbreviation'] ?? '');
        if ($competitorAbbr === strtolower($teamAbbr)) {
            $team = $competitor;
        } else {
            $opponent = $competitor;
        }
    }

    if (!$team || !$opponent) {
        return null;
    }

    $teamScore = extract_score($team['score'] ?? null);
    $oppScore  = extract_score($opponent['score'] ?? null);

    $statusName = strtoupper($competition['status']['type']['name'] ?? '');

    if (in_array($statusName, NON_FINAL_STATUS_NAMES, true)) {
        $status = 'postponed';
    } elseif (isset($team['winner']) && isset($opponent['winner']) && !$team['winner'] && !$opponent['winner']) {
        // ESPN marks both sides winner:false for a tie (e.g. NFL/NHL ties).
        $status = 'tie';
    } elseif (isset($team['winner'])) {
        $status = $team['winner'] ? 'win' : 'loss';
    } elseif ($teamScore !== null && $oppScore !== null && (float) $teamScore === (float) $oppScore) {
        $status = 'tie';
    } else {
        $status = ((float) $teamScore > (float) $oppScore) ? 'win' : 'loss';
    }

    return [
        'status'     => $status,
        'won'        => $status === 'win', // kept for backward compatibility
        'team_score' => $teamScore ?? '?',
        'opp_score'  => $oppScore ?? '?',
        'opponent'   => $opponent['team']['displayName'] ?? ($opponent['team']['name'] ?? 'Opponent'),
        'is_home'    => ($team['homeAway'] ?? '') === 'home',
        'date'       => isset($event['date']) ? date('M j', strtotime($event['date'])) : '',
        'date_raw'   => $event['date'] ?? '', // Passed through for debugging
    ];
}

/**
 * ESPN sometimes returns score as a plain string ("24") and sometimes
 * as an object ({"value": 24, "displayValue": "24"}). Normalize both.
 */
function extract_score($score)
{
    if (is_array($score)) {
        return $score['displayValue'] ?? $score['value'] ?? null;
    }
    return $score;
}
?>