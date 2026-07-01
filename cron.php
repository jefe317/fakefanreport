<?php
declare(strict_types=1);
date_default_timezone_set('America/Chicago');

// Ensure this script can run as long as it needs to and isn't memory capped
set_time_limit(0);
ini_set('memory_limit', '256M');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api.php';

// 1. Build an array of EVERY team's URL across ALL cities
$urls = [];
foreach ($CITIES as $cityKey => $cityData) {
    foreach ($cityData['teams'] as $i => $team) {
        $sportInfo = $SPORT_LABELS[$team['league']] ?? null;
        if (!$sportInfo) {
            continue;
        }
        $requestKey = "{$cityKey}_{$i}";
        $urls[$requestKey] = schedule_url($sportInfo['sport'], $team['league'], $team['abbr']);
    }
}

// 2. Fetch all URLs in parallel
$scheduleResponses = fetch_json_multi($urls);

// 3. Process the results into a structured pre-computed database
$database = [];
$oneMonthAgo = strtotime('-1 month');

foreach ($CITIES as $cityKey => $cityData) {
    $leaguesData = [];
    
    // Initialize leagues
    foreach ($cityData['teams'] as $team) {
        $leagueKey = strtoupper($team['league']);
        if (!isset($leaguesData[$leagueKey])) {
            $leaguesData[$leagueKey] = [
                'latest_timestamp' => 0,
                'games'            => []
            ];
        }
    }

    // Process games
    foreach ($cityData['teams'] as $i => $team) {
        $leagueKey  = strtoupper($team['league']);
        $sportInfo  = $SPORT_LABELS[$team['league']] ?? null;
        $requestKey = "{$cityKey}_{$i}";
        
        if (!$sportInfo) continue;

        $games = last_completed_games($scheduleResponses[$requestKey] ?? null, 2);

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

            $outcome = $result['won'] ? 'Won' : 'Lost';
            $vsAt    = $result['is_home'] ? 'vs' : '@';

            $leaguesData[$leagueKey]['games'][] = [
                'timestamp'  => $gameTimestamp,
                'team_name'  => $team['name'],
                'label'      => $sportInfo['label'],
                'outcome'    => $outcome,
                'vsAt'       => $vsAt,
                'opponent'   => $result['opponent'],
                'team_score' => $result['team_score'],
                'opp_score'  => $result['opp_score'],
                'date_str'   => $relativeDate
            ];

            if ($gameTimestamp > $leaguesData[$leagueKey]['latest_timestamp']) {
                $leaguesData[$leagueKey]['latest_timestamp'] = $gameTimestamp;
            }
        }
    }

    // Sort leagues by latest game, and games within leagues by newest first
    uasort($leaguesData, function ($a, $b) {
        return $b['latest_timestamp'] <=> $a['latest_timestamp'];
    });

    foreach ($leaguesData as &$data) {
        usort($data['games'], function ($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });
    }
    unset($data);

    $database[$cityKey] = [
        'label'   => $cityData['label'],
        'is_all'  => false,
        'leagues' => $leaguesData
    ];
}

// 4. Build the master "All Cities" aggregate dataset
$allLeaguesData = [];

// Initialize every league that exists across any city
foreach ($CITIES as $cityData) {
    foreach ($cityData['teams'] as $team) {
        $leagueKey = strtoupper($team['league']);
        if (!isset($allLeaguesData[$leagueKey])) {
            $allLeaguesData[$leagueKey] = [
                'latest_timestamp' => 0,
                'games'            => []
            ];
        }
    }
}

// Merge the processed games from the database into the "All" dataset
foreach ($database as $cityKey => $cityProcessed) {
    foreach ($cityProcessed['leagues'] as $league => $leagueData) {
        if (empty($leagueData['games'])) continue;
        
        $allLeaguesData[$league]['games'] = array_merge($allLeaguesData[$league]['games'], $leagueData['games']);
        
        if ($leagueData['latest_timestamp'] > $allLeaguesData[$league]['latest_timestamp']) {
            $allLeaguesData[$league]['latest_timestamp'] = $leagueData['latest_timestamp'];
        }
    }
}

// Re-sort the global leagues and their combined games
uasort($allLeaguesData, function ($a, $b) {
    return $b['latest_timestamp'] <=> $a['latest_timestamp'];
});

foreach ($allLeaguesData as &$data) {
    usort($data['games'], function ($a, $b) {
        return $b['timestamp'] <=> $a['timestamp'];
    });
}
unset($data);

// Attach it to the main database payload
$database['all'] = [
    'label'   => 'All Cities',
    'is_all'  => true,
    'leagues' => $allLeaguesData
];


// 5. Prepare data for the JS/HTML template
$jsonDatabase = json_encode($database, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$timestamp    = date('Y-m-d H:i:s');

// Build the dropdown options, defaulting to Chicago, and appending "All Cities" at the end
$optionsHtml = '';
foreach ($CITIES as $key => $city) {
    $selected = ($key === 'chicago') ? ' selected' : '';
    $optionsHtml .= '                <option value="' . htmlspecialchars($key) . '"' . $selected . '>' . htmlspecialchars($city['label']) . '</option>' . "\n";
}
$optionsHtml .= '                <option value="all">All Cities</option>' . "\n";

// 6. Generate the raw code for index2.php
$indexTemplate = <<<HTML
<?php
// AUTO-GENERATED BY cron.php at {$timestamp}
// Force browsers and proxies to discard the cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
<meta http-equiv="Pragma" content="no-cache" />
<meta http-equiv="Expires" content="0" />
<title>Fake Sports Fan Report</title>
<style>
    :root {
        --bg-color: #f8fafc;
        --card-bg: #ffffff;
        --text-primary: #0f172a;
        --text-secondary: #475569;
        --border: #e2e8f0;
        --radius: 8px;
        --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
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

    h2 { font-size: 1.75rem; border-bottom: 2px solid var(--border); padding-bottom: 0.5rem; margin: 2rem 0 1rem 0; }
    h3 {
        font-size: 0.7rem; color: var(--text-secondary); margin: 0.6rem 0 0.3rem 0;
        text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--border);
        padding-bottom: 0.15rem;
    }
    h3:first-child { margin-top: 0; }
    .game-card {
        background: var(--card-bg); padding: 0.35rem 0.6rem; border-radius: 6px;
        border: 1px solid var(--border); margin-bottom: 0.3rem;
        display: flex; flex-wrap: wrap; align-items: baseline; gap: 0 0.4rem;
        font-size: 0.85rem;
    }
    .game-card strong { color: var(--text-primary); font-weight: 600; }
    .game-details { color: var(--text-secondary); }
    .no-results { color: var(--text-secondary); font-style: italic; padding: 0.15rem 0; margin: 0 0 0.3rem 0; font-size: 0.85rem; }
    .last-updated { font-size: 0.7rem; color: var(--text-secondary); text-align: center; margin-top: 1rem; }

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

    <div class="selector-wrapper">
        <label for="city">City</label>
        <select id="city">
{$optionsHtml}
        </select>
    </div>

    <div id="results-container">
        </div>
    
    <div class="last-updated">Scores last updated: {$timestamp}</div>
</main>

<script>
    // Load precomputed PHP data into JS
    const sportsData = {$jsonDatabase};
    const citySelect = document.getElementById('city');
    const resultsContainer = document.getElementById('results-container');

    // Escape HTML function to prevent XSS
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

    // Function to handle rendering based on selection
    function renderResults() {
        const cityId = citySelect.value;
        resultsContainer.innerHTML = ''; 

        if (!cityId || !sportsData[cityId]) return;

        const cityData = sportsData[cityId];
        let html = '';

        for (const [league, data] of Object.entries(cityData.leagues)) {
            html += `<h3>\${escapeHTML(league)}</h3>`;

            if (data.games.length === 0) {
                html += `<p class="no-results">No recent results available</p>`;
            } else {
                data.games.forEach(game => {
                    const title = `\${game.team_name} \${game.outcome} \${game.label}`;
                    const details = `— \${game.team_score}-\${game.opp_score} \${game.vsAt} \${game.opponent} (\${game.date_str})`;
                    
                    html += `
                        <div class="game-card">
                            <strong>\${escapeHTML(title)}</strong>
                            <span class="game-details">\${escapeHTML(details)}</span>
                        </div>
                    `;
                });
            }
        }

        resultsContainer.innerHTML = html;
    }

    // Listen for dropdown changes
    citySelect.addEventListener('change', renderResults);

    // Initial render on page load
    renderResults();
</script>

</body>
</html>
HTML;

// 7. Write to index2.php
$targetFile = __DIR__ . '/index2.php';
file_put_contents($targetFile, $indexTemplate);

// 8. Bust the PHP OPcache for the newly generated file
if (function_exists('opcache_invalidate')) {
    opcache_invalidate($targetFile, true);
}

echo "Successfully generated index2.php at " . date('Y-m-d H:i:s') . "\n";