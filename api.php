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
function fetch_json_multi(array $urls): array
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
        $results[$key] = ($body && $httpCode === 200) ? json_decode($body, true) : null;
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);

    return $results;
}

/**
 * Build the ESPN schedule URL for a team.
 */
function schedule_url(string $sport, string $league, string $abbr): string
{
    return ESPN_BASE . "/{$sport}/{$league}/teams/{$abbr}/schedule";
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