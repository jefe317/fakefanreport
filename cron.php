<?php
date_default_timezone_set('America/Chicago');
declare(strict_types=1);

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
                'game_id'    => $event['id'] ?? null,
                'team_name'  => $team['name'],
                'abbr'       => $team['abbr'],
                'league_key' => $team['league'],
                'label'      => $sportInfo['label'],
                'outcome'    => $outcome,
                'vsAt'       => $vsAt,
                'opponent'   => $result['opponent'],
                'team_score' => $result['team_score'],
                'opp_score'  => $result['opp_score'],
                'date_str'   => $relativeDate,
                'date_iso'   => $gameDateStr,
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

$summaryBaseUrlJs = json_encode(rtrim($SUMMARY_BASE_URL, '/') . '/');

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
<title>Local Sports Recap</title>
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
        line-height: 1.5;
        margin: 0;
        padding: 2rem 1rem;
    }
    .container { max-width: 650px; margin: 0 auto; }
    h1 { font-size: 2.25rem; font-weight: 800; letter-spacing: -0.025em; margin: 0 0 0.5rem 0; }
    .lead { color: var(--text-secondary); font-size: 1.125rem; margin: 0 0 2rem 0; }
    
    .selector-wrapper {
        background: var(--card-bg); padding: 1.5rem; border-radius: var(--radius);
        border: 1px solid var(--border); box-shadow: var(--shadow); 
        margin-bottom: 2.5rem; display: flex; flex-direction: column; gap: 0.5rem;
    }
    label { font-weight: 600; font-size: 0.875rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; }
    select {
        padding: 0.625rem; border-radius: 6px; border: 1px solid var(--border);
        background-color: var(--card-bg); font-size: 1rem; color: var(--text-primary);
        width: 100%; cursor: pointer;
    }
    
    h2 { font-size: 1.75rem; border-bottom: 2px solid var(--border); padding-bottom: 0.5rem; margin: 2rem 0 1rem 0; }
    h3 { font-size: 1.125rem; color: var(--text-secondary); margin: 0; text-transform: uppercase; letter-spacing: 0.05em; }
    .section-head {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        justify-content: space-between;
        gap: 0.5rem 1rem;
        margin: 1.5rem 0 0.75rem 0;
    }
    .section-head--team {
        margin: 0.75rem 0 0.5rem 0;
    }
    .section-head h4 {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: var(--text-primary);
        text-transform: none;
        letter-spacing: normal;
    }
    .section-head > h3,
    .section-head > h4 {
        flex: 0 1 auto;
        margin: 0;
        order: 1;
    }
    .section-head > .ai-accordion--head {
        flex: 0 0 auto;
        margin-left: auto;
        order: 1;
    }
    .section-head > .ai-accordion--head[open] {
        flex: 1 0 100%;
        order: 2;
        width: 100%;
        margin-left: 0;
    }
    .section-head > .ai-accordion--head[open] > summary {
        display: block;
        text-align: right;
        margin-bottom: 0.25rem;
    }
    .section-head > .ai-accordion--head[open] > .ai-accordion-body,
    .section-head > .ai-accordion--head[open] > .ai-sources {
        width: 100%;
    }
    .team-group {
        margin-bottom: 0.25rem;
    }
    .game-card {
        background: var(--card-bg); padding: 1rem 1.25rem; border-radius: var(--radius);
        border: 1px solid var(--border); box-shadow: var(--shadow); margin-bottom: 0.75rem;
    }
    .game-card strong { color: var(--text-primary); }
    .game-details { color: var(--text-secondary); }
    .no-results { color: var(--text-secondary); font-style: italic; padding: 0.5rem 0; }
    .last-updated { font-size: 0.8rem; color: var(--text-secondary); text-align: center; margin-top: 3rem; }

    .report-date {
        color: var(--text-secondary);
        font-size: 0.95rem;
        margin: -0.5rem 0 1.25rem 0;
    }

    .ai-section {
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px solid var(--border);
    }
    .ai-accordion > summary {
        cursor: pointer;
        font-weight: 600;
        font-size: 0.875rem;
        color: var(--text-secondary);
        list-style: none;
    }
    .ai-accordion > summary::-webkit-details-marker { display: none; }
    .ai-accordion > summary::before { content: '▸ '; }
    .ai-accordion[open] > summary::before { content: '▾ '; }
    .ai-accordion-body {
        padding: 0.5rem 0 0;
        color: var(--text-primary);
        font-size: 0.925rem;
        line-height: 1.55;
    }
    .ai-sources {
        margin-top: 0.5rem;
        padding-top: 0.5rem;
        border-top: 1px dashed var(--border);
    }
    .ai-sources > summary {
        padding: 0.5rem 0.75rem;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-secondary);
        list-style: none;
    }
    .ai-sources > summary::-webkit-details-marker { display: none; }
    .ai-sources-list { padding: 0 0.75rem 0.75rem 0.75rem; margin: 0; list-style: none; }
    .ai-sources-list li { margin-bottom: 0.5rem; }
    .ai-sources-list a {
        color: var(--text-primary);
        text-decoration: none;
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
        line-height: 1.4;
    }
    .ai-sources-list a:hover { text-decoration: underline; }
    .source-badge {
        flex-shrink: 0;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        padding: 0.15rem 0.35rem;
        border-radius: 4px;
        text-transform: uppercase;
    }
    .source-espn { background: #dc2626; color: #fff; }
    .source-reddit { background: #ff4500; color: #fff; }
    .ai-error {
        color: var(--text-secondary);
        font-size: 0.9rem;
        font-style: italic;
        margin: 0 0 0.75rem 0;
    }
</style>
</head>
<body>

<main class="container">
    <h1>Local Sports Recap</h1>
    <p class="lead">Pick your city to see how your teams did in their last two games.</p>

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
    const SUMMARY_BASE_URL = {$summaryBaseUrlJs};
    const citySelect = document.getElementById('city');
    const resultsContainer = document.getElementById('results-container');
    const summaryCache = {};

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

    function formatReportDate(dateStr) {
        if (!dateStr) return '';
        const parts = dateStr.split('-');
        if (parts.length !== 3) return dateStr;
        const date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        return date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    }

    async function fetchCitySummary(citySlug) {
        if (citySlug === 'all') return null;
        if (Object.prototype.hasOwnProperty.call(summaryCache, citySlug)) {
            return summaryCache[citySlug];
        }
        try {
            const response = await fetch(SUMMARY_BASE_URL + encodeURIComponent(citySlug) + '.json');
            if (!response.ok) throw new Error('fetch failed');
            summaryCache[citySlug] = await response.json();
        } catch (err) {
            summaryCache[citySlug] = null;
        }
        return summaryCache[citySlug];
    }

    function getLeagueBlock(summary, leagueUpper) {
        if (!summary || !summary.leagues) return null;
        return summary.leagues[leagueUpper.toLowerCase()] || null;
    }

    function getTeamBlock(summary, leagueUpper, abbr) {
        const league = getLeagueBlock(summary, leagueUpper);
        if (!league || !league.teams) return null;
        return league.teams[abbr] || null;
    }

    function normalizeMatchText(value) {
        return String(value || '').toLowerCase().replace(/[^a-z0-9]/g, '');
    }

    function findGameReport(teamBlock, game, usedGameIds) {
        if (!teamBlock || !Array.isArray(teamBlock.recentGames)) return null;

        const candidates = teamBlock.recentGames.filter(entry => !entry.error);

        function takeMatch(match) {
            if (!match) return null;
            const id = match.gameId || (match.date + '|' + match.opponent + '|' + match.result);
            if (usedGameIds.has(id)) return null;
            usedGameIds.add(id);
            return match;
        }

        if (game.game_id) {
            const byId = candidates.find(entry => String(entry.gameId) === String(game.game_id));
            const matched = takeMatch(byId);
            if (matched) return matched;
        }

        const gameOpp = normalizeMatchText(game.opponent);
        const byDateOpp = candidates.find(entry =>
            entry.date === game.date_iso &&
            (normalizeMatchText(entry.opponent) === gameOpp ||
                normalizeMatchText(entry.opponent).includes(gameOpp) ||
                gameOpp.includes(normalizeMatchText(entry.opponent)))
        );
        const matchedOpp = takeMatch(byDateOpp);
        if (matchedOpp) return matchedOpp;

        const resultKey = game.team_score + '-' + game.opp_score;
        const byDateScore = candidates.find(entry =>
            entry.date === game.date_iso && entry.result === resultKey
        );
        const matchedScore = takeMatch(byDateScore);
        if (matchedScore) return matchedScore;

        const byDateOnly = candidates.find(entry => entry.date === game.date_iso);
        return takeMatch(byDateOnly);
    }

    function buildSourcesList(articles, redditPosts) {
        const items = [];
        (articles || []).forEach(article => {
            if (!article || !article.headline) return;
            items.push({
                type: 'espn',
                label: article.headline,
                url: null
            });
        });
        (redditPosts || []).forEach(post => {
            if (!post || !post.title) return;
            items.push({
                type: 'reddit',
                label: post.title,
                url: post.permalink || null
            });
        });
        if (items.length === 0) return '';
        let html = '<ul class="ai-sources-list">';
        items.forEach(item => {
            const badge = item.type === 'reddit'
                ? '<span class="source-badge source-reddit">Reddit</span>'
                : '<span class="source-badge source-espn">ESPN</span>';
            const label = escapeHTML(item.label);
            if (item.url) {
                html += '<li><a href="' + escapeHTML(item.url) + '" target="_blank" rel="noopener noreferrer">'
                    + badge + '<span>' + label + '</span></a></li>';
            } else {
                html += '<li>' + badge + '<span>' + label + '</span></li>';
            }
        });
        html += '</ul>';
        return html;
    }

    function buildReportAccordion(title, bodyText, articles, redditPosts, extraClass) {
        if (!bodyText) return '';
        const cls = 'ai-accordion' + (extraClass ? ' ' + extraClass : '');
        let html = '<details class="' + cls + '">';
        html += '<summary>' + escapeHTML(title) + '</summary>';
        html += '<div class="ai-accordion-body">' + escapeHTML(bodyText) + '</div>';
        const sources = buildSourcesList(articles, redditPosts);
        if (sources) {
            html += '<details class="ai-sources"><summary>Sources</summary>' + sources + '</details>';
        }
        html += '</details>';
        return html;
    }

    function groupGamesByTeam(games) {
        const groups = [];
        const seen = new Map();
        games.forEach(game => {
            if (!seen.has(game.abbr)) {
                const group = { abbr: game.abbr, team_name: game.team_name, games: [] };
                seen.set(game.abbr, group);
                groups.push(group);
            }
            seen.get(game.abbr).games.push(game);
        });
        return groups;
    }

    function espnGamesForTeam(espnLeagueData, abbr) {
        return (espnLeagueData?.games || []).filter(game => game.abbr === abbr);
    }

    function teamHasAiContent(teamBlock) {
        if (!teamBlock || teamBlock.error) return false;
        if (teamBlock.newsSummary) return true;
        return (teamBlock.recentGames || []).some(entry => entry.summary && !entry.error);
    }

    function formatAiGameLine(gameReport) {
        if (gameReport.matchup) return gameReport.matchup;
        const parts = [];
        if (gameReport.opponent) parts.push('vs ' + gameReport.opponent);
        if (gameReport.result) parts.push(gameReport.result);
        if (gameReport.date) parts.push(gameReport.date);
        return parts.join(' — ') || 'Recent game';
    }

    function aiGameKey(gameReport) {
        return String(gameReport.gameId || (gameReport.date + '|' + gameReport.opponent + '|' + gameReport.result));
    }

    function renderAiGameCard(gameReport) {
        let html = '<div class="game-card">';
        html += '<strong>' + escapeHTML(formatAiGameLine(gameReport)) + '</strong>';
        html += '<div class="ai-section">';
        html += buildReportAccordion('Game recap', gameReport.summary);
        html += '</div></div>';
        return html;
    }

    function renderTeamSection(teamName, teamBlock, espnGames) {
        let html = '';

        let teamToggle = '';
        if (teamBlock && teamBlock.newsSummary) {
            teamToggle = buildReportAccordion(
                'Team roundup',
                teamBlock.newsSummary,
                teamBlock.sourceArticles,
                teamBlock.sourceRedditPosts,
                'ai-accordion--head'
            );
        }

        html += '<div class="team-group">';
        html += '<div class="section-head section-head--team">';
        html += '<h4>' + escapeHTML(teamName) + '</h4>';
        html += teamToggle;
        html += '</div>';

        const usedGameIds = new Set();

        espnGames.forEach(game => {
            const title = game.team_name + ' ' + game.outcome + ' ' + game.label;
            const details = '— ' + game.team_score + '-' + game.opp_score + ' '
                + game.vsAt + ' ' + game.opponent + ' (' + game.date_str + ')';

            html += '<div class="game-card">';
            html += '<strong>' + escapeHTML(title) + '</strong>';
            html += '<span class="game-details">' + escapeHTML(details) + '</span>';

            if (teamBlock) {
                const gameReport = findGameReport(teamBlock, game, usedGameIds);
                if (gameReport && gameReport.summary) {
                    html += '<div class="ai-section">';
                    html += buildReportAccordion('Game recap', gameReport.summary);
                    html += '</div>';
                }
            }

            html += '</div>';
        });

        if (teamBlock) {
            (teamBlock.recentGames || []).forEach(gameReport => {
                if (!gameReport.summary || gameReport.error) return;
                const key = aiGameKey(gameReport);
                if (usedGameIds.has(key)) return;
                usedGameIds.add(key);
                html += renderAiGameCard(gameReport);
            });
        }

        html += '</div>';
        return html;
    }

    async function renderResults() {
        const cityId = citySelect.value;
        resultsContainer.innerHTML = ''; 

        if (!cityId || !sportsData[cityId]) return;

        const cityData = sportsData[cityId];
        const showAi = cityId !== 'all';
        let summary = null;

        if (showAi) {
            summary = await fetchCitySummary(cityId);
        }

        let html = '';

        if (!cityData.is_all) {
            html += '<h2>' + escapeHTML(cityData.label) + '</h2>';
        }

        if (showAi && summary && summary.date) {
            html += '<p class="report-date">AI reports for ' + escapeHTML(formatReportDate(summary.date)) + '</p>';
        }

        if (showAi && summary && summary.leagues) {
            for (const [leagueKey, leagueBlock] of Object.entries(summary.leagues)) {
                const league = leagueKey.toUpperCase();
                const espnLeague = cityData.leagues[league] || { games: [] };

                let leagueToggle = '';
                if (leagueBlock.newsSummary) {
                    leagueToggle = buildReportAccordion(
                        'League roundup',
                        leagueBlock.newsSummary,
                        leagueBlock.sourceArticles,
                        leagueBlock.sourceRedditPosts,
                        'ai-accordion--head'
                    );
                }

                const teams = leagueBlock.teams || {};
                const hasTeams = Object.values(teams).some(teamHasAiContent);
                const hasScores = espnLeague.games.length > 0;
                if (!leagueToggle && !hasTeams && !hasScores) {
                    continue;
                }

                html += '<div class="section-head">';
                html += '<h3>' + escapeHTML(league) + '</h3>';
                html += leagueToggle;
                html += '</div>';

                let renderedTeam = false;
                for (const [abbr, teamBlock] of Object.entries(teams)) {
                    const espnGames = espnGamesForTeam(espnLeague, abbr);
                    if (!teamHasAiContent(teamBlock) && espnGames.length === 0) {
                        continue;
                    }
                    renderedTeam = true;
                    const teamName = teamBlock.displayName || teamBlock.name || abbr;
                    html += renderTeamSection(teamName, teamBlock, espnGames);
                }

                if (!renderedTeam && !hasScores) {
                    html += '<p class="no-results">No recent results available</p>';
                }
            }
        } else {
            for (const [league, data] of Object.entries(cityData.leagues)) {
                html += '<div class="section-head">';
                html += '<h3>' + escapeHTML(league) + '</h3>';
                html += '</div>';

                if (data.games.length === 0) {
                    html += '<p class="no-results">No recent results available</p>';
                    continue;
                }

                groupGamesByTeam(data.games).forEach(group => {
                    html += renderTeamSection(group.team_name, null, group.games);
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