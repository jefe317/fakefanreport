<?php
/**
 * api.php
 *
 * Talks to ESPN's public site API using only PHP's built-in cURL
 * extension (no external libraries).
 *
 * Note: the "last N games" feature needs each team's game-by-game
 * results, which the "Specific Team" endpoint from the API list doesn't
 * include (it's mostly roster/franchise info). ESPN's site API exposes
 * that under a /schedule sub-resource on the same team endpoint, so
 * that's what this file calls:
 *
 *   http://site.api.espn.com/apis/site/v2/sports/{sport}/{league}/teams/{abbr}/schedule
 */

define('ESPN_BASE', 'http://site.api.espn.com/apis/site/v2/sports');

/**
 * Fetch several JSON API URLs in parallel using curl_multi so a page
 * with several teams doesn't wait on each request one at a time.
 *
 * @param array<int|string,string> $urls key => url
 * @return array<int|string,array|null> same keys => decoded JSON, or null on failure
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
 * Turn a single ESPN "event" into a simple result array from the given
 * team's point of view. Returns null if the shape isn't what we expect.
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

    if (isset($team['winner'])) {
        $won = (bool) $team['winner'];
    } else {
        $won = ((float) $teamScore) > ((float) $oppScore);
    }

    return [
        'won'        => $won,
        'team_score' => $teamScore ?? '?',
        'opp_score'  => $oppScore ?? '?',
        'opponent'   => $opponent['team']['displayName'] ?? ($opponent['team']['name'] ?? 'Opponent'),
        'is_home'    => ($team['homeAway'] ?? '') === 'home',
        'date'       => isset($event['date']) ? date('M j', strtotime($event['date'])) : '',
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
