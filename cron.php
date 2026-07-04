<?php
declare(strict_types=1);
date_default_timezone_set('America/Chicago');

set_time_limit(0);
ini_set('memory_limit', '256M');

require_once __DIR__ . '/config-15.php';
require_once __DIR__ . '/api.php';

$database = [];
$allLeaguesData = [];
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
            $cityUrls["{$cityKey}_{$i}"] = schedule_url($sportInfo['sport'], $team['league'], $team['abbr']);
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
            $allLeaguesData[$leagueKey]['games'][] = $gameRecord; // Add to global list immediately

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
            $allLeaguesData[$leagueKey]['upcoming'][] = $upcomingRecord; // Add to global list immediately
        }
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

    // *** THE MEMORY SAVER ***
    // Wipe the massive raw JSON strings from memory before the next city iteration
    unset($scheduleResponses);
    unset($cityUrls);
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

echo "Building index.php...\n";

// 5. Prepare data for the JS/HTML template
$jsonDatabase = json_encode($database, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$timestamp    = date('l, F j, Y g:i:s A T');

// Build the dropdown options
$optionsHtml = '';
foreach ($CITIES as $key => $city) {
    $selected = ($key === 'chicago') ? ' selected' : '';
    $optionsHtml .= '                <option value="' . htmlspecialchars($key) . '"' . $selected . '>' . htmlspecialchars($city['label']) . '</option>' . "\n";
}
$optionsHtml .= '                <option value="all">All Cities</option>' . "\n";

// 6. Generate the raw code for index.php
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

    <div id="results-container"></div>
    
    <div class="last-updated">Scores last updated: {$timestamp}</div>

</main>

<script>
    const sportsData = {$jsonDatabase};
    
    const citySelect = document.getElementById('city');
    const resultsContainer = document.getElementById('results-container');
    const CITY_STORAGE_KEY = 'sportsfan_selected_city';

    function getUrlCity() {
        const params = new URLSearchParams(window.location.search);
        return params.get('city');
    }

    function getStoredCity() {
        try {
            return window.localStorage.getItem(CITY_STORAGE_KEY);
        } catch (e) {
            // localStorage unavailable (private browsing, disabled, etc.) — fail quietly
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
        if (urlCity && sportsData[urlCity]) return urlCity;

        const storedCity = getStoredCity();
        if (storedCity && sportsData[storedCity]) return storedCity;

        return citySelect.value; // server-rendered default (Chicago)
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

    function renderResults() {
        const cityId = citySelect.value;
        resultsContainer.innerHTML = ''; 

        if (!cityId || !sportsData[cityId]) return;

        const cityData = sportsData[cityId];
        let html = '';

        for (const [league, data] of Object.entries(cityData.leagues)) {
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
            if (data.games.length === 0 && data.upcoming.length === 0) {
                html += `<p class="no-results">No recent or upcoming results available</p>`;
            } else {
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
        }

        resultsContainer.innerHTML = html;
    }

    citySelect.addEventListener('change', () => {
        storeCity(citySelect.value);
        updateUrl(citySelect.value);
        renderResults();
    });

    citySelect.value = resolveInitialCity();
    updateUrl(citySelect.value);
    renderResults();

    // --- Auto-refresh if the page hasn't been loaded since the most recent 2am Central ---
    const PAGE_LOAD_STORAGE_KEY = 'sportsfan_page_loaded_at';
    const pageLoadedAt = Date.now();

    try {
        window.localStorage.setItem(PAGE_LOAD_STORAGE_KEY, String(pageLoadedAt));
    } catch (e) {
        // localStorage unavailable — staleness check below still works via pageLoadedAt
    }

    // Returns what the wall-clock time in America/Chicago reads for a given
    // instant, as {year, month, day, hour, minute, second}. Using Intl here
    // (rather than trusting the visitor's own local timezone) means this is
    // correct no matter where the browser is physically located, and it
    // automatically accounts for CST/CDT daylight saving changes.
    function getChicagoParts(date) {
        const parts = new Intl.DateTimeFormat('en-US', {
            timeZone: 'America/Chicago',
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', second: '2-digit',
            hour12: false
        }).formatToParts(date);
        const map = {};
        parts.forEach(p => { map[p.type] = p.value; });
        // hour12: false can render midnight as "24" in some browsers — normalize to 0
        if (map.hour === '24') map.hour = '0';
        return {
            year: Number(map.year), month: Number(map.month), day: Number(map.day),
            hour: Number(map.hour), minute: Number(map.minute), second: Number(map.second)
        };
    }

    // Computes the timestamp (as a real UTC instant) of the most recent 2am
    // Central relative to "now". Works by taking today's Chicago calendar date,
    // asking what UTC instant "that date at 2am Chicago time" corresponds to,
    // and stepping back a day if that instant hasn't happened yet.
    function mostRecent2amCentral() {
        const now = new Date();
        const chicagoNow = getChicagoParts(now);

        function chicagoWallClockToUtc(year, month, day, hour) {
            // Find the UTC instant whose Chicago wall-clock reads year-month-day hour:00:00.
            // Start with a naive guess treating the wall clock as UTC, then correct for offset.
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
        // It's not yet 2am Chicago time today — the most recent 2am was yesterday.
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

    // Catches the common case: laptop closed/reopened, or tab switched back to
    // after a long time. Browsers throttle timers in background tabs, but the
    // visibilitychange event still fires reliably when a tab becomes active again.
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') checkStaleness();
    });

    // Catches a tab left open and visible/foregrounded continuously.
    setInterval(checkStaleness, 5 * 60 * 1000); // check every 5 minutes

</script>

</body>
</html>
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