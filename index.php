<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api.php';

$selectedCity = isset($_GET['city']) ? trim((string) $_GET['city']) : '';
$cityData     = $CITIES[$selectedCity] ?? null;

// Build one schedule URL per team in the selected city, then fetch all
// of them in parallel so the page doesn't wait on each team one by one.
$scheduleResponses = [];
if ($cityData) {
    $urls = [];
    foreach ($cityData['teams'] as $i => $team) {
        $sportInfo = $SPORT_LABELS[$team['league']] ?? null;
        if (!$sportInfo) {
            continue;
        }
        $urls[$i] = schedule_url($sportInfo['sport'], $team['league'], $team['abbr']);
    }
    $scheduleResponses = fetch_json_multi($urls);
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Local Sports Recap</title>
<style>
    :root {
        --bg-color: #f8fafc;
        --card-bg: #ffffff;
        --text-primary: #0f172a;
        --text-secondary: #475569;
        --primary: #1e40af;
        --primary-hover: #1e3a8a;
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

    .container {
        max-width: 650px;
        margin: 0 auto;
    }

    h1 {
        font-size: 2.25rem;
        font-weight: 800;
        letter-spacing: -0.025em;
        margin: 0 0 0.5rem 0;
    }

    .lead {
        color: var(--text-secondary);
        font-size: 1.125rem;
        margin: 0 0 2rem 0;
    }

    form {
        background: var(--card-bg);
        padding: 1.5rem;
        border-radius: var(--radius);
        border: 1px solid var(--border);
        box-shadow: var(--shadow);
        display: flex;
        gap: 1rem;
        align-items: flex-end;
        flex-wrap: wrap;
        margin-bottom: 2.5rem;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        flex-grow: 1;
        min-width: 200px;
    }

    label {
        font-weight: 600;
        font-size: 0.875rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    select {
        padding: 0.625rem;
        border-radius: 6px;
        border: 1px solid var(--border);
        background-color: var(--card-bg);
        font-size: 1rem;
        color: var(--text-primary);
        width: 100%;
        cursor: pointer;
    }

    button {
        background-color: var(--primary);
        color: #ffffff;
        padding: 0.625rem 1.25rem;
        border-radius: 6px;
        border: none;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.2s;
        height: 42px;
    }

    button:hover {
        background-color: var(--primary-hover);
    }

    .alert {
        background-color: #fee2e2;
        color: #991b1b;
        padding: 1rem;
        border-radius: var(--radius);
        margin-bottom: 1.5rem;
        border: 1px solid #fca5a5;
    }

    h2 {
        font-size: 1.75rem;
        border-bottom: 2px solid var(--border);
        padding-bottom: 0.5rem;
        margin: 2rem 0 1rem 0;
    }

    h3 {
        font-size: 1.125rem;
        color: var(--text-secondary);
        margin: 1.5rem 0 0.75rem 0;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .game-card {
        background: var(--card-bg);
        padding: 1rem 1.25rem;
        border-radius: var(--radius);
        border: 1px solid var(--border);
        box-shadow: var(--shadow);
        margin-bottom: 0.75rem;
    }

    .game-card strong {
        color: var(--text-primary);
    }

    .game-details {
        color: var(--text-secondary);
    }

    .no-results {
        color: var(--text-secondary);
        font-style: italic;
        padding: 0.5rem 0;
    }

    @media (max-width: 480px) {
        form {
            flex-direction: column;
            align-items: stretch;
        }
        button {
            width: 100%;
        }
    }
</style>
</head>
<body>

<main class="container">
    <h1>Local Sports Recap</h1>
    <p class="lead">Pick your city to see how your teams did in their last two games.</p>

    <form method="get" action="index.php">
        <div class="form-group">
            <label for="city">City</label>
            <select name="city" id="city">
                <option value="">-- Select a city --</option>
                <?php foreach ($CITIES as $key => $city): ?>
                    <option value="<?= htmlspecialchars($key) ?>"<?= $key === $selectedCity ? ' selected' : '' ?>>
                        <?= htmlspecialchars($city['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit">Show Recent Results</button>
    </form>

    <?php if ($selectedCity !== '' && !$cityData): ?>
        <div class="alert">
            <p style="margin:0;">Sorry, that city isn't set up yet.</p>
        </div>
    <?php endif; ?>

    <?php if ($cityData): ?>
        <h2><?= htmlspecialchars($cityData['label']) ?></h2>
        <?php
        $leaguesData = [];
        $oneMonthAgo = strtotime('-1 month');

        // Initialize all unique leagues present for this city's setup
        foreach ($cityData['teams'] as $team) {
            $leagueKey = strtoupper($team['league']);
            if (!isset($leaguesData[$leagueKey])) {
                $leaguesData[$leagueKey] = [
                    'latest_timestamp' => 0,
                    'games'            => []
                ];
            }
        }

        // Process and filter the raw responses
        foreach ($cityData['teams'] as $i => $team) {
            $leagueKey = strtoupper($team['league']);
            $sportInfo = $SPORT_LABELS[$team['league']] ?? null;
            if (!$sportInfo) {
                continue;
            }

            $games = last_completed_games($scheduleResponses[$i] ?? null, 2);

            foreach ($games as $event) {
                $gameTimestamp = isset($event['date']) ? strtotime($event['date']) : 0;

                // Ignore games older than 1 month
                if ($gameTimestamp < $oneMonthAgo) {
                    continue;
                }

                $result = summarize_game($event, $team['abbr']);
                if (!$result) {
                    continue;
                }

                // Generate relative dates
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

                // Store game data details
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

                // Track the overall most recent game timestamp for this specific league
                if ($gameTimestamp > $leaguesData[$leagueKey]['latest_timestamp']) {
                    $leaguesData[$leagueKey]['latest_timestamp'] = $gameTimestamp;
                }
            }
        }

        // Sort leagues globally by their freshest game timestamp descending
        // Leagues with a timestamp of 0 (no recent games) go to the bottom
        uasort($leaguesData, function ($a, $b) {
            return $b['latest_timestamp'] <=> $a['latest_timestamp'];
        });

        // Sort games inside each individual league by timestamp descending
        foreach ($leaguesData as $leagueKey => &$data) {
            usort($data['games'], function ($a, $b) {
                return $b['timestamp'] <=> $a['timestamp'];
            });
        }
        unset($data);

        // Output structural results
        foreach ($leaguesData as $league => $data) {
            echo "<h3>" . htmlspecialchars($league) . "</h3>\n";

            if (empty($data['games'])) {
                echo "<p class=\"no-results\">No recent results available</p>\n";
                continue;
            }

            foreach ($data['games'] as $game) {
                echo "<div class=\"game-card\">";
                echo "<strong>" . htmlspecialchars("{$game['team_name']} {$game['outcome']} {$game['label']}") . "</strong>";
                echo "<span class=\"game-details\">" . htmlspecialchars(
                    " — {$game['team_score']}-{$game['opp_score']} {$game['vsAt']} {$game['opponent']} ({$game['date_str']})"
                ) . "</span>";
                echo "</div>\n";
            }
        }
        ?>
    <?php endif; ?>
</main>

</body>
</html>