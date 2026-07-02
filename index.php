<?php
// AUTO-GENERATED at Thursday, July 2, 2026 8:32:26 AM CDT
header("Cache-Control: public, max-age=3600");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Stay updated with the latest sports scores, upcoming game schedules, and team updates across major cities.">
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
                <option value="chicago" selected>Chicago</option>
                <option value="losangeles">Los Angeles</option>
                <option value="newyork">New York</option>
                <option value="all">All Cities</option>

        </select>
    </div>

    <div id="results-container"></div>
    
    <div class="last-updated">Scores last updated: Thursday, July 2, 2026 8:32:26 AM CDT</div>

</main>

<script>
    const sportsData = {"chicago":{"label":"Chicago","is_all":false,"leagues":{"MLB":{"latest_timestamp":1782930000,"games":[{"timestamp":1782930000,"team_name":"Cubs","label":"Baseball","outcome":"Won","vsAt":"vs","opponent":"San Diego Padres","team_score":"23","opp_score":"3","date_str":"Yesterday","date_raw":"2026-07-01T18:20Z"},{"timestamp":1782923700,"team_name":"White Sox","label":"Baseball","outcome":"Lost","vsAt":"@","opponent":"Baltimore Orioles","team_score":"1","opp_score":"6","date_str":"Yesterday","date_raw":"2026-07-01T16:35Z"},{"timestamp":1782864300,"team_name":"Cubs","label":"Baseball","outcome":"Won","vsAt":"vs","opponent":"San Diego Padres","team_score":"9","opp_score":"7","date_str":"2 days ago","date_raw":"2026-07-01T00:05Z"},{"timestamp":1782858900,"team_name":"White Sox","label":"Baseball","outcome":"Won","vsAt":"@","opponent":"Baltimore Orioles","team_score":"9","opp_score":"3","date_str":"2 days ago","date_raw":"2026-06-30T22:35Z"}],"upcoming":[{"timestamp":1783032000,"team_name":"White Sox","label":"Baseball","vsAt":"@","opponent":"Cleveland Guardians","date_str":"Today, 5:40 PM","date_raw":"2026-07-02T22:40Z"},{"timestamp":1783109100,"team_name":"Cubs","label":"Baseball","vsAt":"vs","opponent":"St. Louis Cardinals","date_str":"Tomorrow, 3:05 PM","date_raw":"2026-07-03T20:05Z"}]},"NFL":{"latest_timestamp":0,"games":[],"upcoming":[]},"NBA":{"latest_timestamp":0,"games":[],"upcoming":[]},"NHL":{"latest_timestamp":0,"games":[],"upcoming":[]}}},"losangeles":{"label":"Los Angeles","is_all":false,"leagues":{"MLB":{"latest_timestamp":1782956400,"games":[{"timestamp":1782956400,"team_name":"Dodgers","label":"Baseball","outcome":"Lost","vsAt":"@","opponent":"Athletics","team_score":"1","opp_score":"7","date_str":"Yesterday","date_raw":"2026-07-02T01:40Z"},{"timestamp":1782870000,"team_name":"Dodgers","label":"Baseball","outcome":"Won","vsAt":"@","opponent":"Athletics","team_score":"9","opp_score":"3","date_str":"2 days ago","date_raw":"2026-07-01T01:40Z"},{"timestamp":1782870000,"team_name":"Angels","label":"Baseball","outcome":"Lost","vsAt":"@","opponent":"Seattle Mariners","team_score":"3","opp_score":"8","date_str":"2 days ago","date_raw":"2026-07-01T01:40Z"},{"timestamp":1782783600,"team_name":"Angels","label":"Baseball","outcome":"Lost","vsAt":"@","opponent":"Seattle Mariners","team_score":"2","opp_score":"6","date_str":"Jun 29","date_raw":"2026-06-30T01:40Z"}],"upcoming":[{"timestamp":1783042800,"team_name":"Angels","label":"Baseball","vsAt":"@","opponent":"Seattle Mariners","date_str":"Today, 8:40 PM","date_raw":"2026-07-03T01:40Z"},{"timestamp":1783044600,"team_name":"Dodgers","label":"Baseball","vsAt":"vs","opponent":"San Diego Padres","date_str":"Today, 9:10 PM","date_raw":"2026-07-03T02:10Z"}]},"NFL":{"latest_timestamp":0,"games":[],"upcoming":[]},"NBA":{"latest_timestamp":0,"games":[],"upcoming":[]},"NHL":{"latest_timestamp":0,"games":[],"upcoming":[]}}},"newyork":{"label":"New York","is_all":false,"leagues":{"MLB":{"latest_timestamp":1782932820,"games":[{"timestamp":1782932820,"team_name":"Mets","label":"Baseball","outcome":"Lost","vsAt":"@","opponent":"Toronto Blue Jays","team_score":"3","opp_score":"9","date_str":"Yesterday","date_raw":"2026-07-01T19:07Z"},{"timestamp":1782927300,"team_name":"Yankees","label":"Baseball","outcome":"Lost","vsAt":"vs","opponent":"Detroit Tigers","team_score":"2","opp_score":"6","date_str":"Yesterday","date_raw":"2026-07-01T17:35Z"},{"timestamp":1782860820,"team_name":"Mets","label":"Baseball","outcome":"Won","vsAt":"@","opponent":"Toronto Blue Jays","team_score":"3","opp_score":"0","date_str":"2 days ago","date_raw":"2026-06-30T23:07Z"},{"timestamp":1782860700,"team_name":"Yankees","label":"Baseball","outcome":"Lost","vsAt":"vs","opponent":"Detroit Tigers","team_score":"3","opp_score":"9","date_str":"2 days ago","date_raw":"2026-06-30T23:05Z"}],"upcoming":[{"timestamp":1783119900,"team_name":"Yankees","label":"Baseball","vsAt":"vs","opponent":"Minnesota Twins","date_str":"Tomorrow, 6:05 PM","date_raw":"2026-07-03T23:05Z"},{"timestamp":1783120500,"team_name":"Mets","label":"Baseball","vsAt":"@","opponent":"Atlanta Braves","date_str":"Tomorrow, 6:15 PM","date_raw":"2026-07-03T23:15Z"}]},"NBA":{"latest_timestamp":1781397000,"games":[{"timestamp":1781397000,"team_name":"Knicks","label":"Basketball","outcome":"Won","vsAt":"@","opponent":"San Antonio Spurs","team_score":"94","opp_score":"90","date_str":"Jun 13","date_raw":"2026-06-14T00:30Z"},{"timestamp":1781137800,"team_name":"Knicks","label":"Basketball","outcome":"Won","vsAt":"vs","opponent":"San Antonio Spurs","team_score":"107","opp_score":"106","date_str":"Jun 10","date_raw":"2026-06-11T00:30Z"}],"upcoming":[]},"NFL":{"latest_timestamp":0,"games":[],"upcoming":[]},"NHL":{"latest_timestamp":0,"games":[],"upcoming":[]}}},"all":{"label":"All Cities","is_all":true,"leagues":{"MLB":{"latest_timestamp":1782956400,"games":[{"timestamp":1782956400,"team_name":"Dodgers","label":"Baseball","outcome":"Lost","vsAt":"@","opponent":"Athletics","team_score":"1","opp_score":"7","date_str":"Yesterday","date_raw":"2026-07-02T01:40Z"},{"timestamp":1782932820,"team_name":"Mets","label":"Baseball","outcome":"Lost","vsAt":"@","opponent":"Toronto Blue Jays","team_score":"3","opp_score":"9","date_str":"Yesterday","date_raw":"2026-07-01T19:07Z"},{"timestamp":1782930000,"team_name":"Cubs","label":"Baseball","outcome":"Won","vsAt":"vs","opponent":"San Diego Padres","team_score":"23","opp_score":"3","date_str":"Yesterday","date_raw":"2026-07-01T18:20Z"},{"timestamp":1782927300,"team_name":"Yankees","label":"Baseball","outcome":"Lost","vsAt":"vs","opponent":"Detroit Tigers","team_score":"2","opp_score":"6","date_str":"Yesterday","date_raw":"2026-07-01T17:35Z"},{"timestamp":1782923700,"team_name":"White Sox","label":"Baseball","outcome":"Lost","vsAt":"@","opponent":"Baltimore Orioles","team_score":"1","opp_score":"6","date_str":"Yesterday","date_raw":"2026-07-01T16:35Z"},{"timestamp":1782870000,"team_name":"Dodgers","label":"Baseball","outcome":"Won","vsAt":"@","opponent":"Athletics","team_score":"9","opp_score":"3","date_str":"2 days ago","date_raw":"2026-07-01T01:40Z"},{"timestamp":1782870000,"team_name":"Angels","label":"Baseball","outcome":"Lost","vsAt":"@","opponent":"Seattle Mariners","team_score":"3","opp_score":"8","date_str":"2 days ago","date_raw":"2026-07-01T01:40Z"},{"timestamp":1782864300,"team_name":"Cubs","label":"Baseball","outcome":"Won","vsAt":"vs","opponent":"San Diego Padres","team_score":"9","opp_score":"7","date_str":"2 days ago","date_raw":"2026-07-01T00:05Z"},{"timestamp":1782860820,"team_name":"Mets","label":"Baseball","outcome":"Won","vsAt":"@","opponent":"Toronto Blue Jays","team_score":"3","opp_score":"0","date_str":"2 days ago","date_raw":"2026-06-30T23:07Z"},{"timestamp":1782860700,"team_name":"Yankees","label":"Baseball","outcome":"Lost","vsAt":"vs","opponent":"Detroit Tigers","team_score":"3","opp_score":"9","date_str":"2 days ago","date_raw":"2026-06-30T23:05Z"},{"timestamp":1782858900,"team_name":"White Sox","label":"Baseball","outcome":"Won","vsAt":"@","opponent":"Baltimore Orioles","team_score":"9","opp_score":"3","date_str":"2 days ago","date_raw":"2026-06-30T22:35Z"},{"timestamp":1782783600,"team_name":"Angels","label":"Baseball","outcome":"Lost","vsAt":"@","opponent":"Seattle Mariners","team_score":"2","opp_score":"6","date_str":"Jun 29","date_raw":"2026-06-30T01:40Z"}],"upcoming":[{"timestamp":1783032000,"team_name":"White Sox","label":"Baseball","vsAt":"@","opponent":"Cleveland Guardians","date_str":"Today, 5:40 PM","date_raw":"2026-07-02T22:40Z"},{"timestamp":1783042800,"team_name":"Angels","label":"Baseball","vsAt":"@","opponent":"Seattle Mariners","date_str":"Today, 8:40 PM","date_raw":"2026-07-03T01:40Z"},{"timestamp":1783044600,"team_name":"Dodgers","label":"Baseball","vsAt":"vs","opponent":"San Diego Padres","date_str":"Today, 9:10 PM","date_raw":"2026-07-03T02:10Z"},{"timestamp":1783109100,"team_name":"Cubs","label":"Baseball","vsAt":"vs","opponent":"St. Louis Cardinals","date_str":"Tomorrow, 3:05 PM","date_raw":"2026-07-03T20:05Z"},{"timestamp":1783119900,"team_name":"Yankees","label":"Baseball","vsAt":"vs","opponent":"Minnesota Twins","date_str":"Tomorrow, 6:05 PM","date_raw":"2026-07-03T23:05Z"},{"timestamp":1783120500,"team_name":"Mets","label":"Baseball","vsAt":"@","opponent":"Atlanta Braves","date_str":"Tomorrow, 6:15 PM","date_raw":"2026-07-03T23:15Z"}]},"NBA":{"latest_timestamp":1781397000,"games":[{"timestamp":1781397000,"team_name":"Knicks","label":"Basketball","outcome":"Won","vsAt":"@","opponent":"San Antonio Spurs","team_score":"94","opp_score":"90","date_str":"Jun 13","date_raw":"2026-06-14T00:30Z"},{"timestamp":1781137800,"team_name":"Knicks","label":"Basketball","outcome":"Won","vsAt":"vs","opponent":"San Antonio Spurs","team_score":"107","opp_score":"106","date_str":"Jun 10","date_raw":"2026-06-11T00:30Z"}],"upcoming":[]},"NFL":{"latest_timestamp":0,"games":[],"upcoming":[]},"NHL":{"latest_timestamp":0,"games":[],"upcoming":[]}}}};
    
    const citySelect = document.getElementById('city');
    const resultsContainer = document.getElementById('results-container');

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
            html += `<h2>${escapeHTML(league)}</h2>`;
            
            // Upcoming Games
            if (data.upcoming.length > 0) {
                data.upcoming.forEach(game => {
                    const title = `${game.team_name} ${game.label}`;
                    const details = `${game.vsAt} ${game.opponent} (${game.date_str})`;
                    
                    html += `
                        <div class="game-card upcoming" title="Raw date: ${escapeHTML(game.date_raw)}">
                            <strong>[Upcoming] ${escapeHTML(title)}</strong>
                            <span class="game-details">${escapeHTML(details)}</span>
                        </div>
                    `;
                });
            }

            // Completed Games
            if (data.games.length === 0 && data.upcoming.length === 0) {
                html += `<p class="no-results">No recent or upcoming results available</p>`;
            } else {
                data.games.forEach(game => {
                    const title = `${game.team_name} ${game.outcome} ${game.label}`;
                    const details = `— ${game.team_score}-${game.opp_score} ${game.vsAt} ${game.opponent} (${game.date_str})`;
                    
                    let outcomeClass = '';
                    if (game.outcome === 'Won') outcomeClass = ' win';
                    if (game.outcome === 'Lost') outcomeClass = ' loss';
                    
                    html += `
                        <div class="game-card${outcomeClass}" title="Raw date: ${escapeHTML(game.date_raw)}">
                            <strong>${escapeHTML(title)}</strong>
                            <span class="game-details">${escapeHTML(details)}</span>
                        </div>
                    `;
                });
            }
        }

        resultsContainer.innerHTML = html;
    }

    citySelect.addEventListener('change', renderResults);
    renderResults();
</script>

</body>
</html>