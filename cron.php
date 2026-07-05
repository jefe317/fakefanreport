<?php
declare(strict_types=1);
date_default_timezone_set('America/Chicago');

set_time_limit(0);
ini_set('memory_limit', '256M');

require_once __DIR__ . '/config-15.php';
require_once __DIR__ . '/api.php';

$database = [];
$allLeaguesData = [];
$allRequestedUrls = []; // Track all generated API URLs
$oneMonthAgo = strtotime('-1 month');

// Initialize the master "All Cities" array structure upfront
foreach ($CITIES as $cityData) {
    foreach ($cityData['teams'] as $team) {
        $leagueKey = strtoupper($team['league']);
        if (!isset($allLeaguesData[$leagueKey])) {
            $allLeaguesData[$leagueKey] = [
                'latest_timestamp' => 0,
                'games'            => [],
                'upcoming'         => []
            ];
        }
    }
}

echo "Starting data fetch and compilation...\n";

// --- NEW: Fetch World Cup USA Data ---
echo "Fetching USA World Cup data...\n";
$worldCupUrl = 'https://site.api.espn.com/apis/site/v2/sports/soccer/fifa.world/scoreboard?limit=100&dates=20260601-20260731';
$allRequestedUrls[] = $worldCupUrl;
$wcResponses = fetch_json_multi([$worldCupUrl]);
$wcData = $wcResponses[0] ?? null;

$usaEvents = [];
if ($wcData && !empty($wcData['events'])) {
    foreach ($wcData['events'] as $event) {
        $competition = $event['competitions'][0] ?? null;
        if ($competition && !empty($competition['competitors'])) {
            foreach ($competition['competitors'] as $comp) {
                // Look specifically for the USA team
                if (strtolower($comp['team']['abbreviation'] ?? '') === 'usa') {
                    $usaEvents[] = $event;
                    break;
                }
            }
        }
    }
}

$usaData = ['events' => $usaEvents];
$fifaGames = [];
$fifaUpcoming = [];
$fifaLatestTimestamp = 0;

// Process USA completed games
$games = last_completed_games($usaData, 2);
foreach ($games as $event) {
    $gameTimestamp = isset($event['date']) ? strtotime($event['date']) : 0;
    if ($gameTimestamp < $oneMonthAgo) continue;

    $result = summarize_game($event, 'usa');
    if (!$result) continue;

    $gameDateStr   = date('Y-m-d', $gameTimestamp);
    $todayStr      = date('Y-m-d');
    $yesterdayStr  = date('Y-m-d', strtotime('yesterday'));
    $twoDaysAgoStr = date('Y-m-d', strtotime('-2 days'));

    if ($gameDateStr === $todayStr) {
        $relativeDate = 'Today';
    } elseif ($gameDateStr === $yesterdayStr) {
        $relativeDate = 'Yesterday';
    } elseif ($gameDateStr === $twoDaysAgoStr) {
        $relativeDate = '2 days ago';
    } else {
        $relativeDate = date('M j', $gameTimestamp);
    }

    $outcomeLabels = [
        'win'       => 'Won',
        'loss'      => 'Lost',
        'tie'       => 'Tied',
        'postponed' => 'Postponed',
    ];
    $outcome = $outcomeLabels[$result['status']] ?? 'Final';
    $vsAt    = $result['is_home'] ? 'vs' : '@';

    $fifaGames[] = [
        'timestamp'  => $gameTimestamp,
        'team_name'  => 'USA',
        'label'      => 'World Cup',
        'outcome'    => $outcome,
        'vsAt'       => $vsAt,
        'opponent'   => $result['opponent'],
        'team_score' => $result['team_score'],
        'opp_score'  => $result['opp_score'],
        'date_str'   => $relativeDate,
        'date_raw'   => $result['date_raw'],
    ];

    if ($gameTimestamp > $fifaLatestTimestamp) {
        $fifaLatestTimestamp = $gameTimestamp;
    }
}

// Process USA upcoming game
$upcomingResult = get_upcoming_game($usaData, 'usa');
if ($upcomingResult) {
    $upcomingTimestamp = strtotime($upcomingResult['date_raw']);
    $gameDateStr       = date('Y-m-d', $upcomingTimestamp);
    $todayStr          = date('Y-m-d');
    $tomorrowStr       = date('Y-m-d', strtotime('tomorrow'));

    if ($gameDateStr === $todayStr) {
        $relativeUpcoming = 'Today, ' . date('g:i A', $upcomingTimestamp);
    } elseif ($gameDateStr === $tomorrowStr) {
        $relativeUpcoming = 'Tomorrow, ' . date('g:i A', $upcomingTimestamp);
    } else {
        $relativeUpcoming = date('M j, g:i A', $upcomingTimestamp);
    }

    $fifaUpcoming[] = [
        'timestamp' => $upcomingTimestamp,
        'team_name' => 'USA',
        'label'     => 'World Cup',
        'vsAt'      => $upcomingResult['is_home'] ? 'vs' : '@',
        'opponent'  => $upcomingResult['opponent'],
        'date_str'  => $relativeUpcoming,
        'date_raw'  => $upcomingResult['date_raw'],
    ];
}
unset($wcResponses); // Cleanup memory
// --- END WORLD CUP LOGIC ---


// Process City by City to keep memory usage flat
foreach ($CITIES as $cityKey => $cityData) {
    echo "Processing {$cityData['label']}...\n";
    
    $cityUrls = [];
    $leaguesData = [];

    // Setup leagues and URLs for just this city
    foreach ($cityData['teams'] as $i => $team) {
        $leagueKey = strtoupper($team['league']);
        if (!isset($leaguesData[$leagueKey])) {
            $leaguesData[$leagueKey] = [
                'latest_timestamp' => 0,
                'games'            => [],
                'upcoming'         => []
            ];
        }

        $sportInfo = $SPORT_LABELS[$team['league']] ?? null;
        if ($sportInfo) {
            $url = schedule_url($sportInfo['sport'], $team['league'], $team['abbr']);
            $cityUrls["{$cityKey}_{$i}"] = $url;
            $allRequestedUrls[] = $url;
        }
    }

    // Fetch only this city's data in parallel
    $scheduleResponses = fetch_json_multi($cityUrls);

    // Process games for this city
    foreach ($cityData['teams'] as $i => $team) {
        $leagueKey  = strtoupper($team['league']);
        $sportInfo  = $SPORT_LABELS[$team['league']] ?? null;
        $requestKey = "{$cityKey}_{$i}";
        
        if (!$sportInfo || empty($scheduleResponses[$requestKey])) continue;

        $rawSchedule = $scheduleResponses[$requestKey];

        // --- Process Last Completed Games ---
        $games = last_completed_games($rawSchedule, 2);
        foreach ($games as $event) {
            $gameTimestamp = isset($event['date']) ? strtotime($event['date']) : 0;

            if ($gameTimestamp < $oneMonthAgo) continue;

            $result = summarize_game($event, $team['abbr']);
            if (!$result) continue;

            $gameDateStr   = date('Y-m-d', $gameTimestamp);
            $todayStr      = date('Y-m-d');
            $yesterdayStr  = date('Y-m-d', strtotime('yesterday'));
            $twoDaysAgoStr = date('Y-m-d', strtotime('-2 days'));

            if ($gameDateStr === $todayStr) {
                $relativeDate = 'Today';
            } elseif ($gameDateStr === $yesterdayStr) {
                $relativeDate = 'Yesterday';
            } elseif ($gameDateStr === $twoDaysAgoStr) {
                $relativeDate = '2 days ago';
            } else {
                $relativeDate = date('M j', $gameTimestamp);
            }

            $outcomeLabels = [
                'win'       => 'Won',
                'loss'      => 'Lost',
                'tie'       => 'Tied',
                'postponed' => 'Postponed',
            ];
            $outcome = $outcomeLabels[$result['status']] ?? 'Final';
            $vsAt    = $result['is_home'] ? 'vs' : '@';

            $gameRecord = [
                'timestamp'  => $gameTimestamp,
                'team_name'  => $team['name'],
                'label'      => $sportInfo['label'],
                'outcome'    => $outcome,
                'vsAt'       => $vsAt,
                'opponent'   => $result['opponent'],
                'team_score' => $result['team_score'],
                'opp_score'  => $result['opp_score'],
                'date_str'   => $relativeDate,
                'date_raw'   => $result['date_raw'],
            ];

            $leaguesData[$leagueKey]['games'][] = $gameRecord;
            $allLeaguesData[$leagueKey]['games'][] = $gameRecord;

            if ($gameTimestamp > $leaguesData[$leagueKey]['latest_timestamp']) {
                $leaguesData[$leagueKey]['latest_timestamp'] = $gameTimestamp;
            }
            if ($gameTimestamp > $allLeaguesData[$leagueKey]['latest_timestamp']) {
                $allLeaguesData[$leagueKey]['latest_timestamp'] = $gameTimestamp;
            }
        }

        // --- Process Upcoming Game ---
        $upcomingResult = get_upcoming_game($rawSchedule, $team['abbr']);
        if ($upcomingResult) {
            $upcomingTimestamp = strtotime($upcomingResult['date_raw']);
            $gameDateStr       = date('Y-m-d', $upcomingTimestamp);
            $todayStr          = date('Y-m-d');
            $tomorrowStr       = date('Y-m-d', strtotime('tomorrow'));

            if ($gameDateStr === $todayStr) {
                $relativeUpcoming = 'Today, ' . date('g:i A', $upcomingTimestamp);
            } elseif ($gameDateStr === $tomorrowStr) {
                $relativeUpcoming = 'Tomorrow, ' . date('g:i A', $upcomingTimestamp);
            } else {
                $relativeUpcoming = date('M j, g:i A', $upcomingTimestamp);
            }

            $upcomingRecord = [
                'timestamp' => $upcomingTimestamp,
                'team_name' => $team['name'],
                'label'     => $sportInfo['label'],
                'vsAt'      => $upcomingResult['is_home'] ? 'vs' : '@',
                'opponent'  => $upcomingResult['opponent'],
                'date_str'  => $relativeUpcoming,
                'date_raw'  => $upcomingResult['date_raw'],
            ];

            $leaguesData[$leagueKey]['upcoming'][] = $upcomingRecord;
            $allLeaguesData[$leagueKey]['upcoming'][] = $upcomingRecord;
        }
    }

    // Add USA World Cup if games exist
    if (!empty($fifaGames) || !empty($fifaUpcoming)) {
        $leaguesData['FIFA'] = [
            'latest_timestamp' => $fifaLatestTimestamp,
            'games'            => $fifaGames,
            'upcoming'         => $fifaUpcoming
        ];
    }

    // Sort city leagues
    uasort($leaguesData, function ($a, $b) {
        return $b['latest_timestamp'] <=> $a['latest_timestamp'];
    });

    foreach ($leaguesData as &$data) {
        usort($data['games'], function ($a, $b) { return $b['timestamp'] <=> $a['timestamp']; });
        usort($data['upcoming'], function ($a, $b) { return $a['timestamp'] <=> $b['timestamp']; });
    }
    unset($data);

    $database[$cityKey] = [
        'label'   => $cityData['label'],
        'is_all'  => false,
        'leagues' => $leaguesData
    ];

    // Wipe the massive raw JSON strings from memory before the next city iteration
    unset($scheduleResponses);
    unset($cityUrls);
}

// Add USA World Cup to Global List
if (!empty($fifaGames) || !empty($fifaUpcoming)) {
    $allLeaguesData['FIFA'] = [
        'latest_timestamp' => $fifaLatestTimestamp,
        'games'            => $fifaGames,
        'upcoming'         => $fifaUpcoming
    ];
}

// Sort global leagues
uasort($allLeaguesData, function ($a, $b) {
    return $b['latest_timestamp'] <=> $a['latest_timestamp'];
});

foreach ($allLeaguesData as &$data) {
    usort($data['games'], function ($a, $b) { return $b['timestamp'] <=> $a['timestamp']; });
    usort($data['upcoming'], function ($a, $b) { return $a['timestamp'] <=> $b['timestamp']; });
}
unset($data);

$database['all'] = [
    'label'   => 'All Cities',
    'is_all'  => true,
    'leagues' => $allLeaguesData
];

// --- Major Events ---
echo "Fetching major events...\n";

$majorEventUrls = [];
foreach ($MAJOR_EVENTS as $i => $evt) {
    $datesRange = major_event_dates_range($evt['window_months'], $evt['spans_new_year']);
    $url = scoreboard_url($evt['sport'], $evt['league'], $datesRange);
    $majorEventUrls[$i] = $url;
    $allRequestedUrls[] = $url;
}

$majorEventResponses = fetch_json_multi($majorEventUrls);

$majorEventsOut = [];
foreach ($MAJOR_EVENTS as $i => $evt) {
    $scoreboardData = $majorEventResponses[$i] ?? null;
    $championshipEvent = find_championship_game(
        $scoreboardData,
        $evt['keywords'],
        $evt['requires_postseason_flag']
    );
    if (!$championshipEvent) {
        continue;
    }

    $summary = summarize_championship_game($championshipEvent, $evt['label']);
    if (!$summary) {
        continue;
    }

    $eventTimestamp = isset($summary['date_raw']) ? strtotime($summary['date_raw']) : 0;
    if ($eventTimestamp < $oneMonthAgo) {
        continue;
    }

    $majorEventsOut[] = $summary;
}

// Newest first
usort($majorEventsOut, function ($a, $b) {
    return strtotime($b['date_raw'] ?? 'now') <=> strtotime($a['date_raw'] ?? 'now');
});

$database['_major_events'] = $majorEventsOut;

$allTeamsOut = [];
foreach ($CITIES as $cityKey => $cityData) {
    foreach ($cityData['teams'] as $team) {
        $allTeamsOut[] = [
            'name'      => $team['name'],
            'league'    => strtoupper($team['league']),
            'abbr'      => $team['abbr'],
            'city_key'  => $cityKey,
            'city_label'=> $cityData['label'],
        ];
    }
}

// Expose USA for Custom selections
if (!empty($fifaGames) || !empty($fifaUpcoming)) {
    $allTeamsOut[] = [
        'name'      => 'USA',
        'league'    => 'FIFA',
        'abbr'      => 'usa',
        'city_key'  => 'all',
        'city_label'=> 'National',
    ];
}
$database['_all_teams'] = $allTeamsOut;

echo "Building index.php...\n";

// Prepare data for the JS/HTML template
$jsonDatabase = json_encode($database, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$timestamp    = date('l, F j, Y g:i:s A T');

// Build the dropdown options
$optionsHtml = '';
foreach ($CITIES as $key => $city) {
    $selected = ($key === 'chicago') ? ' selected' : '';
    $optionsHtml .= '                <option value="' . htmlspecialchars($key) . '"' . $selected . '>' . htmlspecialchars($city['label']) . '</option>' . "\n";
}
$optionsHtml .= '                <option value="all">All Cities</option>' . "\n";
$optionsHtml .= '                <option value="__custom__">Customize&hellip;</option>' . "\n";

// Build an HTML comment listing every API URL used
$urlCommentsHtml = "\n";

// Generate the raw code for index.php
$indexTemplate = <<<HTML
<?php
// AUTO-GENERATED at {$timestamp}
header("Cache-Control: public, max-age=3600");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Catch up on your local pro teams in seconds. See recent game results and upcoming games over the next two weeks—all in one place.">
<title>Fake Sports Fan Report</title>
<style>
    :root {
        color-scheme: dark light;
        --bg-color: #f8fafc;
        --card-bg: #ffffff;
        --text-primary: #0f172a;
        --text-secondary: #475569;
        --border: #e2e8f0;
        --radius: 8px;
        --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
        
        --upcoming-bg: #f0f9ff;
        --upcoming-border: #bae6fd;
        --win-bg: #f0fdf4;
        --win-border: #bbf7d0;
        --loss-bg: #fef2f2;
        --loss-border: #fecaca;
        --neutral-bg: #fefce8;
        --neutral-border: #fde68a;
    }

    @media (prefers-color-scheme: dark) {
        :root {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-primary: #f8fafc;
            --text-secondary: #bccbe1;
            --border: #334155;
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.5);
            
            --upcoming-bg: #082f49;
            --upcoming-border: #0369a1;
            --win-bg: #14532d;
            --win-border: #166534;
            --loss-bg: #7f1d1d;
            --loss-border: #991b1b;
            --neutral-bg: #422006;
            --neutral-border: #a16207;
        }
    }

    body {
        font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background-color: var(--bg-color);
        color: var(--text-primary);
        line-height: 1.3;
        margin: 0;
        padding: 0.75rem 0.75rem 1.5rem;
        font-size: 15px;
    }
    .container { max-width: 650px; margin: 0 auto; }
    h1 { font-size: 1.25rem; font-weight: 800; letter-spacing: -0.025em; margin: 0 0 0.5rem 0; }

    .selector-wrapper {
        background: var(--card-bg); padding: 0.5rem 0.625rem; border-radius: var(--radius);
        border: 1px solid var(--border); box-shadow: var(--shadow);
        margin-bottom: 0.75rem; display: flex; flex-direction: row; align-items: center; gap: 0.5rem;
    }
    label { font-weight: 600; font-size: 0.7rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; }
    select {
        padding: 0.3rem 0.4rem; border-radius: 6px; border: 1px solid var(--border);
        background-color: var(--card-bg); font-size: 0.9rem; color: var(--text-primary);
        width: 100%; cursor: pointer;
    }

    h2 {
        font-size: 0.7rem; color: var(--text-secondary); margin: 0.6rem 0 0.3rem 0;
        text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--border);
        padding-bottom: 0.15rem;
    }
    h2:first-child { margin-top: 0; }
    .game-card {
        background: var(--card-bg); padding: 0.35rem 0.6rem; border-radius: 6px;
        border: 1px solid var(--border); margin-bottom: 0.3rem;
        display: flex; flex-wrap: wrap; align-items: baseline; gap: 0 0.4rem;
        font-size: 0.85rem;
    }
    
    .game-card.upcoming { background: var(--upcoming-bg); border-color: var(--upcoming-border); }
    .game-card.win { background: var(--win-bg); border-color: var(--win-border); }
    .game-card.loss { background: var(--loss-bg); border-color: var(--loss-border); }
    .game-card.tie,
    .game-card.postponed { background: var(--neutral-bg); border-color: var(--neutral-border); }
    
    .game-card strong { color: var(--text-primary); font-weight: 600; }
    .game-details { color: var(--text-secondary); }
    .no-results { color: var(--text-secondary); font-style: italic; padding: 0.15rem 0; margin: 0 0 0.3rem 0; font-size: 0.85rem; }
    
    .last-updated { font-size: 0.7rem; color: var(--text-secondary); text-align: center; margin-top: 1rem; }
    .page-loaded { font-size: 0.65rem; color: var(--text-secondary); text-align: center; margin-top: 0.15rem; opacity: 0.8; }

    /* Major events (Super Bowl, Finals, World Cup, etc.) */
    .major-events { margin-top: 0.75rem; }
    .major-event-card {
        background: var(--card-bg); padding: 0.45rem 0.65rem; border-radius: 6px;
        border: 1px solid var(--border); margin-bottom: 0.3rem; font-size: 0.85rem;
        border-left: 3px solid #eab308;
    }
    .major-event-card .event-label {
        font-weight: 700; text-transform: uppercase; font-size: 0.65rem;
        letter-spacing: 0.05em; color: #b45309; display: block; margin-bottom: 0.15rem;
    }
    .major-event-card .matchup { color: var(--text-primary); font-weight: 600; }
    .major-event-card .matchup .winner { color: #15803d; }
    .major-event-card .event-date { color: var(--text-secondary); font-size: 0.75rem; margin-left: 0.35rem; }

    /* Customize panel */
    .customize-panel {
        background: var(--card-bg); padding: 0.6rem 0.65rem; border-radius: var(--radius);
        border: 1px solid var(--border); box-shadow: var(--shadow); margin-bottom: 0.75rem;
    }
    .customize-header-row {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 0.5rem;
    }
    .customize-title {
        font-weight: 700; font-size: 0.75rem; color: var(--text-secondary);
        text-transform: uppercase; letter-spacing: 0.05em;
    }
    .toggle-editor-btn {
        padding: 0.3rem 0.55rem; border-radius: 6px; border: 1px solid var(--border);
        background-color: var(--bg-color); color: var(--text-primary); font-size: 0.72rem;
        cursor: pointer; white-space: nowrap; font-weight: 600;
    }
    .toggle-editor-btn:hover { background-color: var(--border); }
    .customize-collapsed-hint {
        font-size: 0.75rem; color: var(--text-secondary); font-style: italic; margin: 0;
    }
    .customize-panel .customize-row {
        display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;
    }
    .customize-search {
        flex: 1; padding: 0.35rem 0.5rem; border-radius: 6px; border: 1px solid var(--border);
        background-color: var(--bg-color); color: var(--text-primary); font-size: 0.85rem;
    }
    .select-all-btn {
        padding: 0.35rem 0.6rem; border-radius: 6px; border: 1px solid var(--border);
        background-color: var(--bg-color); color: var(--text-primary); font-size: 0.75rem;
        cursor: pointer; white-space: nowrap; font-weight: 600;
    }
    .select-all-btn:hover { background-color: var(--border); }
    .team-checklist {
        max-height: 260px; overflow-y: auto; border: 1px solid var(--border);
        border-radius: 6px; padding: 0.35rem 0.5rem;
    }
    .league-group-header {
        font-weight: 700; font-size: 0.68rem; color: var(--text-secondary);
        text-transform: uppercase; letter-spacing: 0.06em; margin: 0.5rem 0 0.2rem 0;
        padding-bottom: 0.15rem; border-bottom: 1px solid var(--border);
    }
    .league-group-header:first-child { margin-top: 0; }
    .league-group-header.hidden { display: none; }
    .team-check-item {
        display: flex; align-items: center; gap: 0.4rem; padding: 0.2rem 0;
        font-size: 0.82rem;
    }
    .team-check-item input { cursor: pointer; }
    .team-check-item label { text-transform: none; font-weight: 400; font-size: 0.82rem; color: var(--text-primary); cursor: pointer; display: flex; align-items: baseline; gap: 0.35rem; }
    .team-check-item .team-city-tag {
        font-size: 0.68rem; color: var(--text-secondary); font-weight: 400;
    }
    .team-check-item.hidden { display: none; }
    .customize-empty-hint { font-size: 0.75rem; color: var(--text-secondary); font-style: italic; padding: 0.3rem 0; }

    .stale-banner {
        background: var(--neutral-bg); border: 1px solid var(--neutral-border);
        color: var(--text-primary); border-radius: var(--radius);
        padding: 0.5rem 0.7rem; margin-bottom: 0.75rem; font-size: 0.85rem;
    }
    .stale-banner a { color: var(--text-primary); font-weight: 600; }
    .stale-banner a:hover { text-decoration: none; }

    @media (max-width: 480px) {
        body { padding: 0.5rem 0.5rem 1rem; font-size: 14px; }
        h1 { font-size: 1.1rem; }
        .game-card { font-size: 0.8rem; padding: 0.3rem 0.5rem; }
        h3 { font-size: 0.65rem; }
    }
</style>
</head>
<body>

<main class="container">
    <h1>Fake Sports Fan Report</h1>

    <div id="stale-banner" class="stale-banner" hidden>
        New data is available. <a href="#" id="stale-refresh-link">Refresh to see the latest scores</a>.
    </div>

    <div class="selector-wrapper">
        <label for="city">City</label>
        <select id="city">
{$optionsHtml}
        </select>
    </div>

    <div id="customize-panel" class="customize-panel" hidden>
        <div class="customize-header-row">
            <span class="customize-title">Customize your teams</span>
            <button type="button" id="toggle-customize-editor" class="toggle-editor-btn">Hide picker</button>
        </div>
        <div id="customize-editor">
            <div class="customize-row">
                <input type="text" id="team-search" class="customize-search" placeholder="Search teams, leagues, or cities (e.g. &quot;NBA&quot;, &quot;Bears&quot;, &quot;Chicago&quot;)">
                <button type="button" id="select-all-shown" class="select-all-btn">Select all shown</button>
            </div>
            <div id="team-checklist" class="team-checklist"></div>
        </div>
        <p id="customize-collapsed-hint" class="customize-collapsed-hint" hidden>Picker hidden — your selections are still active.</p>
    </div>

    <div id="results-container"></div>

    <div class="last-updated">Scores last updated: {$timestamp}</div>
    <div class="page-loaded" id="page-loaded-line"></div>

</main>

<script>
    const sportsData = {$jsonDatabase};
    const majorEvents = sportsData._major_events || [];
    const allTeams = sportsData._all_teams || [];

    const citySelect = document.getElementById('city');
    const resultsContainer = document.getElementById('results-container');
    const customizePanel = document.getElementById('customize-panel');
    const teamChecklist = document.getElementById('team-checklist');
    const teamSearch = document.getElementById('team-search');
    const selectAllShownBtn = document.getElementById('select-all-shown');
    const customizeEditor = document.getElementById('customize-editor');
    const toggleCustomizeEditorBtn = document.getElementById('toggle-customize-editor');
    const customizeCollapsedHint = document.getElementById('customize-collapsed-hint');

    const CITY_STORAGE_KEY = 'sportsfan_selected_city';
    const CUSTOM_TEAMS_STORAGE_KEY = 'sportsfan_custom_teams';
    const CUSTOM_EDITOR_COLLAPSED_KEY = 'sportsfan_custom_editor_collapsed';
    const CUSTOM_VALUE = '__custom__';

    function getUrlCity() {
        const params = new URLSearchParams(window.location.search);
        return params.get('city');
    }

    function getStoredCustomTeams() {
        try {
            const raw = window.localStorage.getItem(CUSTOM_TEAMS_STORAGE_KEY);
            const parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function storeCustomTeams(teamKeys) {
        try {
            window.localStorage.setItem(CUSTOM_TEAMS_STORAGE_KEY, JSON.stringify(teamKeys));
        } catch (e) {
            // ignore write failures
        }
    }

    function teamKey(team) {
        return `\${team.league}::\${team.abbr}::\${team.name}`;
    }

    function buildCustomCityData(selectedKeys) {
        const selectedSet = new Set(selectedKeys);
        const leagues = {};

        for (const team of allTeams) {
            if (!selectedSet.has(teamKey(team))) continue;

            const cityBucket = sportsData[team.city_key];
            if (!cityBucket) continue;

            const leagueData = cityBucket.leagues[team.league];
            if (!leagueData) continue;

            if (!leagues[team.league]) {
                leagues[team.league] = { latest_timestamp: 0, games: [], upcoming: [] };
            }

            const teamGames = leagueData.games.filter(g => g.team_name === team.name);
            const teamUpcoming = leagueData.upcoming.filter(g => g.team_name === team.name);

            leagues[team.league].games.push(...teamGames);
            leagues[team.league].upcoming.push(...teamUpcoming);
        }

        for (const key of Object.keys(leagues)) {
            leagues[key].games.sort((a, b) => b.timestamp - a.timestamp);
            leagues[key].upcoming.sort((a, b) => a.timestamp - b.timestamp);
            leagues[key].latest_timestamp = leagues[key].games.length
                ? leagues[key].games[0].timestamp
                : 0;
        }

        return { label: 'Customize', is_all: false, leagues };
    }

    function getStoredCity() {
        try {
            return window.localStorage.getItem(CITY_STORAGE_KEY);
        } catch (e) {
            return null;
        }
    }

    function storeCity(cityId) {
        try {
            window.localStorage.setItem(CITY_STORAGE_KEY, cityId);
        } catch (e) {
            // ignore write failures
        }
    }

    function updateUrl(cityId) {
        const url = new URL(window.location.href);
        url.searchParams.set('city', cityId);
        window.history.replaceState({}, '', url);
    }

    function resolveInitialCity() {
        const urlCity = getUrlCity();
        if (urlCity && (sportsData[urlCity] || urlCity === CUSTOM_VALUE)) return urlCity;

        const storedCity = getStoredCity();
        if (storedCity && (sportsData[storedCity] || storedCity === CUSTOM_VALUE)) return storedCity;

        return citySelect.value;
    }

    function matchesSearch(team, query) {
        if (!query) return true;
        const q = query.toLowerCase();
        return team.name.toLowerCase().includes(q)
            || team.league.toLowerCase().includes(q)
            || team.city_label.toLowerCase().includes(q);
    }

    function renderTeamChecklist() {
        const storedKeys = new Set(getStoredCustomTeams());
        const query = teamSearch.value.trim();

        const sortedTeams = [...allTeams].sort((a, b) => {
            if (a.league !== b.league) return a.league.localeCompare(b.league);
            return a.name.localeCompare(b.name);
        });

        let html = '';
        let currentLeague = null;

        for (const team of sortedTeams) {
            const key = teamKey(team);
            const visible = matchesSearch(team, query);
            const checked = storedKeys.has(key) ? ' checked' : '';
            const inputId = `team-check-\${escapeHTML(key)}`;

            if (team.league !== currentLeague) {
                currentLeague = team.league;
                html += `<div class="league-group-header" data-league="\${escapeHTML(currentLeague)}">\${escapeHTML(currentLeague)}</div>`;
            }

            html += `
                <div class="team-check-item\${visible ? '' : ' hidden'}" data-team-key="\${escapeHTML(key)}" data-league="\${escapeHTML(team.league)}">
                    <input type="checkbox" id="\${inputId}"\${checked} data-team-key="\${escapeHTML(key)}">
                    <label for="\${inputId}">\${escapeHTML(team.name)} <span class="team-city-tag">\${escapeHTML(team.city_label)}</span></label>
                </div>
            `;
        }

        if (!sortedTeams.length) {
            html = `<p class="customize-empty-hint">No teams available.</p>`;
        }

        teamChecklist.innerHTML = html;

        teamChecklist.querySelectorAll('input[type="checkbox"]').forEach(input => {
            input.addEventListener('change', onTeamCheckboxChange);
        });

        updateLeagueHeaderVisibility();
    }

    function updateLeagueHeaderVisibility() {
        const headers = Array.from(teamChecklist.querySelectorAll('.league-group-header'));
        const items = Array.from(teamChecklist.querySelectorAll('.team-check-item'));

        headers.forEach(header => {
            const anyVisible = items.some(item =>
                item.dataset.league === header.dataset.league && !item.classList.contains('hidden')
            );
            header.classList.toggle('hidden', !anyVisible);
        });
    }

    function getCheckedKeysFromDom() {
        return Array.from(teamChecklist.querySelectorAll('input[type="checkbox"]:checked'))
            .map(input => input.dataset.teamKey);
    }

    function onTeamCheckboxChange() {
        storeCustomTeams(getCheckedKeysFromDom());
        if (citySelect.value === CUSTOM_VALUE) renderResults();
    }

    function filterChecklist() {
        const query = teamSearch.value.trim();
        teamChecklist.querySelectorAll('.team-check-item').forEach(item => {
            const key = item.dataset.teamKey;
            const team = allTeams.find(t => teamKey(t) === key);
            const visible = team ? matchesSearch(team, query) : true;
            item.classList.toggle('hidden', !visible);
        });
        updateLeagueHeaderVisibility();
    }

    function selectAllShown() {
        const shownInputs = Array.from(teamChecklist.querySelectorAll('.team-check-item:not(.hidden) input[type="checkbox"]'));
        shownInputs.forEach(input => { input.checked = true; });
        storeCustomTeams(getCheckedKeysFromDom());
        if (citySelect.value === CUSTOM_VALUE) renderResults();
    }

    function isEditorCollapsed() {
        try {
            return window.localStorage.getItem(CUSTOM_EDITOR_COLLAPSED_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function setEditorCollapsed(collapsed) {
        try {
            window.localStorage.setItem(CUSTOM_EDITOR_COLLAPSED_KEY, collapsed ? '1' : '0');
        } catch (e) {
            // ignore write failures
        }
        customizeEditor.hidden = collapsed;
        customizeCollapsedHint.hidden = !collapsed;
        toggleCustomizeEditorBtn.textContent = collapsed ? 'Edit teams' : 'Hide picker';
    }

    function updateCustomizeVisibility() {
        const isCustom = citySelect.value === CUSTOM_VALUE;
        customizePanel.hidden = !isCustom;
        if (isCustom) {
            renderTeamChecklist();
            setEditorCollapsed(isEditorCollapsed());
        }
    }

    function escapeHTML(str) {
        return String(str).replace(/[&<>'"]/g, 
            tag => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#39;',
                '"': '&quot;'
            }[tag] || tag)
        );
    }

    function renderMajorEventsHtml() {
        if (!majorEvents.length) return '';

        let html = '<h2>Championships &amp; Major Events</h2><div class="major-events">';
        majorEvents.forEach(evt => {
            const aClass = evt.team_a_winner ? ' winner' : '';
            const bClass = evt.team_b_winner ? ' winner' : '';
            const scoreLine = evt.is_final
                ? `\${escapeHTML(evt.team_a)} \${evt.team_a_score} – \${evt.team_b_score} \${escapeHTML(evt.team_b)}`
                : `\${escapeHTML(evt.team_a)} vs \${escapeHTML(evt.team_b)}`;

            html += `
                <div class="major-event-card">
                    <span class="event-label">\${escapeHTML(evt.event_label)}</span>
                    <span class="matchup">
                        <span class="\${aClass.trim()}">\${escapeHTML(evt.team_a)}\${evt.is_final ? ' ' + evt.team_a_score : ''}</span>
                        \${evt.is_final ? '–' : 'vs'}
                        <span class="\${bClass.trim()}">\${escapeHTML(evt.team_b)}\${evt.is_final ? ' ' + evt.team_b_score : ''}</span>
                    </span>
                    <span class="event-date">(\${escapeHTML(evt.date_str)})</span>
                </div>
            `;
        });
        html += '</div>';
        return html;
    }

    function renderResults() {
        const cityId = citySelect.value;
        resultsContainer.innerHTML = ''; 

        if (!cityId) return;

        const cityData = cityId === CUSTOM_VALUE
            ? buildCustomCityData(getStoredCustomTeams())
            : sportsData[cityId];

        if (!cityData) return;

        let html = '';

        const leagueEntries = Object.entries(cityData.leagues);

        if (cityId === CUSTOM_VALUE && leagueEntries.length === 0) {
            html += `<p class="no-results">No teams selected yet — use the Customize panel above to pick some.</p>`;
        }

        let emptyLeagues = [];

        for (const [league, data] of leagueEntries) {
            // Group empty leagues for combined message
            if (data.games.length === 0 && data.upcoming.length === 0) {
                // Ignore FIFA if it's empty so it doesn't print "No recent results for FIFA"
                if (league !== 'FIFA') {
                    emptyLeagues.push(league);
                }
                continue;
            }

            html += `<h2>\${escapeHTML(league)}</h2>`;
            
            // Upcoming Games
            if (data.upcoming.length > 0) {
                data.upcoming.forEach(game => {
                    const title = `\${game.team_name} \${game.label}`;
                    const details = `\${game.vsAt} \${game.opponent} (\${game.date_str})`;
                    
                    html += `
                        <div class="game-card upcoming">
                            <strong>\${escapeHTML(title)}</strong>
                            <span class="game-details">\${escapeHTML(details)}</span>
                        </div>
                    `;
                });
            }

            // Completed Games
            data.games.forEach(game => {
                const title = `\${game.team_name} \${game.outcome} \${game.label}`;
                const scorePart = game.outcome === 'Postponed' ? '' : `\${game.team_score}-\${game.opp_score} `;
                const details = `— \${scorePart}\${game.vsAt} \${game.opponent} (\${game.date_str})`;
                
                let outcomeClass = '';
                if (game.outcome === 'Won') outcomeClass = ' win';
                if (game.outcome === 'Lost') outcomeClass = ' loss';
                if (game.outcome === 'Tied') outcomeClass = ' tie';
                if (game.outcome === 'Postponed') outcomeClass = ' postponed';
                
                html += `
                    <div class="game-card\${outcomeClass}">
                        <strong>\${escapeHTML(title)}</strong>
                        <span class="game-details">\${escapeHTML(details)}</span>
                    </div>
                `;
            });
        }

        // Output combined empty leagues string
        if (emptyLeagues.length > 0) {
            let emptyStr = '';
            if (emptyLeagues.length === 1) {
                emptyStr = emptyLeagues[0];
            } else if (emptyLeagues.length === 2) {
                emptyStr = emptyLeagues.join(' or ');
            } else {
                const last = emptyLeagues.pop();
                emptyStr = emptyLeagues.join(', ') + ', or ' + last;
            }
            html += `<p class="no-results" style="margin-top: 0.5rem;">No recent or upcoming results available for \${escapeHTML(emptyStr)}.</p>`;
        }

        html += renderMajorEventsHtml();

        resultsContainer.innerHTML = html;
    }

    citySelect.addEventListener('change', () => {
        storeCity(citySelect.value);
        updateUrl(citySelect.value);
        updateCustomizeVisibility();
        renderResults();
    });

    teamSearch.addEventListener('input', filterChecklist);
    selectAllShownBtn.addEventListener('click', selectAllShown);
    toggleCustomizeEditorBtn.addEventListener('click', () => {
        setEditorCollapsed(!isEditorCollapsed());
    });

    citySelect.value = resolveInitialCity();

    if (getStoredCustomTeams().length === 0 && citySelect.value !== CUSTOM_VALUE && sportsData[citySelect.value]) {
        const initialCityLeagues = sportsData[citySelect.value].leagues || {};
        const seedKeys = [];
        for (const team of allTeams) {
            if (team.city_key === citySelect.value) seedKeys.push(teamKey(team));
        }
        if (seedKeys.length) storeCustomTeams(seedKeys);
    }

    updateUrl(citySelect.value);
    updateCustomizeVisibility();
    renderResults();

    const PAGE_LOAD_STORAGE_KEY = 'sportsfan_page_loaded_at';
    const pageLoadedAt = Date.now();

    try {
        window.localStorage.setItem(PAGE_LOAD_STORAGE_KEY, String(pageLoadedAt));
    } catch (e) {
    }

    const pageLoadedLine = document.getElementById('page-loaded-line');
    if (pageLoadedLine) {
        const loadedDate = new Date(pageLoadedAt);
        const formatted = new Intl.DateTimeFormat('en-US', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
            hour: 'numeric', minute: '2-digit', second: '2-digit',
            timeZoneName: 'short'
        }).format(loadedDate);
        pageLoadedLine.textContent = `Page loaded: \${formatted}`;
    }

    function getChicagoParts(date) {
        const parts = new Intl.DateTimeFormat('en-US', {
            timeZone: 'America/Chicago',
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', second: '2-digit',
            hour12: false
        }).formatToParts(date);
        const map = {};
        parts.forEach(p => { map[p.type] = p.value; });
        if (map.hour === '24') map.hour = '0';
        return {
            year: Number(map.year), month: Number(map.month), day: Number(map.day),
            hour: Number(map.hour), minute: Number(map.minute), second: Number(map.second)
        };
    }

    function mostRecent2amCentral() {
        const now = new Date();
        const chicagoNow = getChicagoParts(now);

        function chicagoWallClockToUtc(year, month, day, hour) {
            const guess = Date.UTC(year, month - 1, day, hour, 0, 0);
            const guessChicagoParts = getChicagoParts(new Date(guess));
            const guessAsUtc = Date.UTC(
                guessChicagoParts.year, guessChicagoParts.month - 1, guessChicagoParts.day,
                guessChicagoParts.hour, guessChicagoParts.minute, guessChicagoParts.second
            );
            const offsetMs = guess - guessAsUtc;
            return guess + offsetMs;
        }

        const todayAt2amUtc = chicagoWallClockToUtc(chicagoNow.year, chicagoNow.month, chicagoNow.day, 2);

        if (todayAt2amUtc <= now.getTime()) {
            return todayAt2amUtc;
        }
        const yesterday = new Date(now.getTime() - 24 * 60 * 60 * 1000);
        const chicagoYesterday = getChicagoParts(yesterday);
        return chicagoWallClockToUtc(chicagoYesterday.year, chicagoYesterday.month, chicagoYesterday.day, 2);
    }

    const staleBanner = document.getElementById('stale-banner');
    const staleRefreshLink = document.getElementById('stale-refresh-link');

    function showStaleBanner() {
        staleBanner.hidden = false;
    }

    function checkStaleness() {
        if (pageLoadedAt < mostRecent2amCentral()) {
            showStaleBanner();
        }
    }

    staleRefreshLink.addEventListener('click', (e) => {
        e.preventDefault();
        window.location.reload();
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') checkStaleness();
    });

    setInterval(checkStaleness, 5 * 60 * 1000); 

</script>

</body>
</html>
{$urlCommentsHtml}
HTML;

// 7. Write to index.php
$targetFile = __DIR__ . '/index.php';
file_put_contents($targetFile, $indexTemplate);

// 8. Bust the PHP OPcache for the newly generated file
if (function_exists('opcache_invalidate')) {
    opcache_invalidate($targetFile, true);
}

echo "Successfully generated index.php at " . date('l, F j, Y g:i:s A T') . "\n";
?>