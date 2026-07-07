<?php
/**
 * builder-core.php
 *
 * Shared logic for turning raw ESPN API responses into the site's
 * $database array and the final index.php HTML.
 */

require_once __DIR__ . '/config-15.php';
require_once __DIR__ . '/api.php';

function build_step_list(array $CITIES, array $MAJOR_EVENTS, array $SPORT_LABELS): array
{
    $steps = [];

    // 1. USA World Cup
    $steps[] = [
        'key'   => 'worldcup',
        'type'  => 'worldcup',
        'label' => 'Fetching USA World Cup schedule',
        'url'   => 'https://site.api.espn.com/apis/site/v2/sports/soccer/fifa.world/scoreboard?limit=100&dates=20260601-20260731',
    ];

    // 2. Every team's schedule, grouped by city (order matches config)
    foreach ($CITIES as $cityKey => $cityData) {
        foreach ($cityData['teams'] as $i => $team) {
            $sportInfo = $SPORT_LABELS[$team['league']] ?? null;
            if (!$sportInfo) {
                continue;
            }
            $steps[] = [
                'key'        => "{$cityKey}_{$i}",
                'type'       => 'team',
                'label'      => "Fetching {$cityData['label']}: {$team['name']} (" . strtoupper($team['league']) . ') schedule',
                'url'        => schedule_url($sportInfo['sport'], $team['league'], $team['abbr']),
                'city_key'   => $cityKey,
                'team_index' => $i,
            ];
        }
    }

    // 3. Major events
    foreach ($MAJOR_EVENTS as $i => $evt) {
        $datesRange = major_event_dates_range($evt['window_months'], $evt['spans_new_year']);
        $steps[] = [
            'key'         => "major_{$i}",
            'type'        => 'major_event',
            'label'       => "Fetching {$evt['label']}",
            'url'         => scoreboard_url($evt['sport'], $evt['league'], $datesRange),
            'event_index' => $i,
        ];
    }

    return $steps;
}

/**
 * Build the abbreviated step list for Level 2 live updates.
 */
function build_live_step_list(): array
{
    return [
        ['key' => 'live_nfl', 'label' => 'NFL Live', 'url' => 'http://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard?limit=100'],
        ['key' => 'live_mlb', 'label' => 'MLB Live', 'url' => 'http://site.api.espn.com/apis/site/v2/sports/baseball/mlb/scoreboard?limit=100'],
        ['key' => 'live_nhl', 'label' => 'NHL Live', 'url' => 'http://site.api.espn.com/apis/site/v2/sports/hockey/nhl/scoreboard?limit=100'],
        ['key' => 'live_nba', 'label' => 'NBA Live', 'url' => 'http://site.api.espn.com/apis/site/v2/sports/basketball/nba/scoreboard?limit=100'],
        ['key' => 'live_fifa', 'label' => 'FIFA Live', 'url' => 'http://site.api.espn.com/apis/site/v2/sports/soccer/fifa.world/scoreboard?limit=100'],
    ];
}

function fetch_step(array $step, ?callable $log = null): ?array
{
    if ($log) {
        $log("fetch_step key={$step['key']} url={$step['url']}");
    }
    $result = fetch_json_multi([$step['key'] => $step['url']], $log);
    return $result[$step['key']] ?? null;
}

function aggregate_database(array $rawByKey, array $CITIES, array $MAJOR_EVENTS, array $SPORT_LABELS): array
{
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
                    'live'             => [],
                    'games'            => [],
                    'upcoming'         => []
                ];
            }
        }
    }

    // --- USA World Cup ---
    $wcData = $rawByKey['worldcup'] ?? null;
    $usaEvents = [];
    if ($wcData && !empty($wcData['events'])) {
        foreach ($wcData['events'] as $event) {
            $competition = $event['competitions'][0] ?? null;
            if ($competition && !empty($competition['competitors'])) {
                foreach ($competition['competitors'] as $comp) {
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

    $games = last_completed_games($usaData, 2);
    foreach ($games as $event) {
        $gameTimestamp = isset($event['date']) ? strtotime($event['date']) : 0;
        if ($gameTimestamp < $oneMonthAgo) continue;

        $result = summarize_game($event, 'usa');
        if (!$result) continue;

        $altNote = $event['competitions'][0]['altGameNote'] ?? '';
        $stageLabel = 'World Cup';
        if ($altNote) {
            $stageLabel = str_replace('FIFA World Cup, ', 'World Cup ', $altNote);
            $stageLabel = str_replace('FIFA ', '', $stageLabel);
        }

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

        $outcomeLabels = ['win' => 'Won', 'loss' => 'Lost', 'tie' => 'Tied', 'postponed' => 'Postponed'];
        $outcome = $outcomeLabels[$result['status']] ?? 'Final';
        $vsAt    = $result['is_home'] ? 'vs' : '@';

        $fifaGames[] = [
            'timestamp'  => $gameTimestamp,
            'team_name'  => 'USA',
            'label'      => $stageLabel,
            'outcome'    => $outcome,
            'vsAt'       => $vsAt,
            'opponent'   => $result['opponent'],
            'team_score' => $result['team_score'],
            'opp_score'  => $result['opp_score'],
            'date_str'   => $relativeDate,
            'date_raw'   => $result['date_raw'],
        ];
        if ($gameTimestamp > $fifaLatestTimestamp) $fifaLatestTimestamp = $gameTimestamp;
    }

    $upcomingResult = get_upcoming_game($usaData, 'usa');
    if ($upcomingResult) {
        $altNote = '';
        foreach ($usaData['events'] as $evt) {
            if (isset($evt['date']) && $evt['date'] === $upcomingResult['date_raw']) {
                $altNote = $evt['competitions'][0]['altGameNote'] ?? '';
                break;
            }
        }
        $stageLabel = 'World Cup';
        if ($altNote) {
            $stageLabel = str_replace('FIFA World Cup, ', 'World Cup ', $altNote);
            $stageLabel = str_replace('FIFA ', '', $stageLabel);
        }

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
            'label'     => $stageLabel,
            'vsAt'      => $upcomingResult['is_home'] ? 'vs' : '@',
            'opponent'  => $upcomingResult['opponent'],
            'date_str'  => $relativeUpcoming,
            'date_raw'  => $upcomingResult['date_raw'],
        ];
    }

    // Process city by city
    foreach ($CITIES as $cityKey => $cityData) {
        $leaguesData = [];

        foreach ($cityData['teams'] as $i => $team) {
            $leagueKey = strtoupper($team['league']);
            if (!isset($leaguesData[$leagueKey])) {
                $leaguesData[$leagueKey] = [
                    'latest_timestamp' => 0,
                    'live'             => [],
                    'games'            => [],
                    'upcoming'         => []
                ];
            }
        }

        foreach ($cityData['teams'] as $i => $team) {
            $leagueKey  = strtoupper($team['league']);
            $sportInfo  = $SPORT_LABELS[$team['league']] ?? null;
            $requestKey = "{$cityKey}_{$i}";

            if (!$sportInfo || empty($rawByKey[$requestKey])) continue;
            $rawSchedule = $rawByKey[$requestKey];

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

                if ($gameDateStr === $todayStr) $relativeDate = 'Today';
                elseif ($gameDateStr === $yesterdayStr) $relativeDate = 'Yesterday';
                elseif ($gameDateStr === $twoDaysAgoStr) $relativeDate = '2 days ago';
                else $relativeDate = date('M j', $gameTimestamp);

                $outcomeLabels = ['win' => 'Won', 'loss' => 'Lost', 'tie' => 'Tied', 'postponed' => 'Postponed'];
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

        if (!empty($fifaGames) || !empty($fifaUpcoming)) {
            $leaguesData['FIFA'] = [
                'latest_timestamp' => $fifaLatestTimestamp,
                'live'             => [],
                'games'            => $fifaGames,
                'upcoming'         => $fifaUpcoming
            ];
        }

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
    }

    if (!empty($fifaGames) || !empty($fifaUpcoming)) {
        $allLeaguesData['FIFA'] = [
            'latest_timestamp' => $fifaLatestTimestamp,
            'live'             => [],
            'games'            => $fifaGames,
            'upcoming'         => $fifaUpcoming
        ];
    }

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

    $majorEventsOut = [];
    foreach ($MAJOR_EVENTS as $i => $evt) {
        $scoreboardData = $rawByKey["major_{$i}"] ?? null;
        $championshipEvent = find_championship_game(
            $scoreboardData,
            $evt['keywords'],
            $evt['requires_postseason_flag']
        );
        if (!$championshipEvent) continue;
        $summary = summarize_championship_game($championshipEvent, $evt['label']);
        if (!$summary) continue;

        $eventTimestamp = isset($summary['date_raw']) ? strtotime($summary['date_raw']) : 0;
        if ($eventTimestamp < $oneMonthAgo) continue;
        $majorEventsOut[] = $summary;
    }

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
    if (!empty($fifaGames) || !empty($fifaUpcoming)) {
        $allTeamsOut[] = [
            'name' => 'USA', 'league' => 'FIFA', 'abbr' => 'usa',
            'city_key' => 'all', 'city_label' => 'National',
        ];
    }
    $database['_all_teams'] = $allTeamsOut;

    return $database;
}

/**
 * Mutates a provided database in-place, adding live games extracted from scoreboards
 * while removing those same games from the upcoming array to prevent duplication.
 */
function apply_live_scoreboards(array &$database, array $liveRawByKey, array $CITIES, ?callable $log = null): void
{
    // Which league each build_live_step_list() key represents. We need this
    // because ESPN abbreviations are NOT unique across leagues — e.g. "phi"
    // is reused by the Eagles (NFL), 76ers (NBA), Phillies (MLB), and Flyers
    // (NHL). A single global abbr->team lookup would let later teams silently
    // overwrite earlier ones sharing the same city abbreviation. Since each
    // scoreboard fetch is already scoped to one league, we build a lookup
    // PER LEAGUE instead, so "phi" in the MLB scoreboard only ever resolves
    // against MLB teams.
    $keyToLeague = [
        'live_nfl'  => 'NFL',
        'live_mlb'  => 'MLB',
        'live_nhl'  => 'NHL',
        'live_nba'  => 'NBA',
        'live_fifa' => 'FIFA',
    ];

    $teamLookupByLeague = [];
    foreach ($CITIES as $cKey => $cData) {
        foreach ($cData['teams'] as $team) {
            $leagueKey = strtoupper($team['league']);
            $teamLookupByLeague[$leagueKey][strtolower($team['abbr'])] = [
                'city_key' => $cKey,
                'league'   => $leagueKey,
                'name'     => $team['name']
            ];
        }
    }
    // FIFA isn't in $CITIES (it's the national team, not a city team), and we
    // only ever care about the USA's match, so this scoping also naturally
    // filters the FIFA scoreboard down to just USA's game.
    $teamLookupByLeague['FIFA']['usa'] = ['city_key' => 'all', 'league' => 'FIFA', 'name' => 'USA'];

    foreach ($liveRawByKey as $key => $scoreboardData) {
        if (!$scoreboardData || empty($scoreboardData['events'])) {
            if ($log) $log("apply key=$key: no scoreboard data or no events");
            continue;
        }

        $league = $keyToLeague[$key] ?? null;
        if (!$league || empty($teamLookupByLeague[$league])) {
            if ($log) $log("apply key=$key: unknown league mapping — skipping");
            continue;
        }
        $teamLookup = $teamLookupByLeague[$league];

        $eventTotal = count($scoreboardData['events']);
        $inCount = 0;
        $matched = 0;
        $unmatched = [];

        foreach ($scoreboardData['events'] as $event) {
            $state = $event['status']['type']['state'] ?? '';
            if ($state !== 'in') continue; // only process live games
            $inCount++;

            $competition = $event['competitions'][0] ?? null;
            if (!$competition || empty($competition['competitors'])) continue;

            $comps = $competition['competitors'];
            if (count($comps) < 2) continue;

            // Evaluate for each competitor in our tracked list
            foreach ($comps as $idx => $competitor) {
                $abbr = strtolower($competitor['team']['abbreviation'] ?? '');
                if (!isset($teamLookup[$abbr])) {
                    $unmatched[$abbr] = true;
                    continue;
                }
                $matched++;

                $info = $teamLookup[$abbr];
                $opponent = $idx === 0 ? $comps[1] : $comps[0];

                $teamScore = (int) extract_score($competitor['score'] ?? 0);
                $oppScore  = (int) extract_score($opponent['score'] ?? 0);

                if ($teamScore > $oppScore) $liveStatus = 'Winning';
                elseif ($teamScore < $oppScore) $liveStatus = 'Losing';
                else $liveStatus = 'Tied';

                $progress = $competition['status']['type']['detail'] ?? 'In Progress';

                $record = [
                    'timestamp'  => strtotime($event['date']),
                    'team_name'  => $info['name'],
                    'label'      => $info['league'],
                    'outcome'    => 'live',
                    'live_status'=> $liveStatus,
                    'team_score' => $teamScore,
                    'opp_score'  => $oppScore,
                    'vsAt'       => ($competitor['homeAway'] ?? '') === 'home' ? 'vs' : '@',
                    'opponent'   => $opponent['team']['displayName'] ?? ($opponent['team']['name'] ?? 'Opponent'),
                    'progress'   => $progress,
                    'date_raw'   => $event['date']
                ];

                $applyLiveToBucket = function(&$leagueBucket) use ($record) {
                    if (!isset($leagueBucket['live'])) $leagueBucket['live'] = [];
                    $leagueBucket['live'][] = $record;
                    
                    // Drop from upcoming if it's currently live
                    if (isset($leagueBucket['upcoming'])) {
                        foreach ($leagueBucket['upcoming'] as $k => $up) {
                            if (strtolower($up['opponent']) === strtolower($record['opponent'])) {
                                unset($leagueBucket['upcoming'][$k]);
                            }
                        }
                        $leagueBucket['upcoming'] = array_values($leagueBucket['upcoming']);
                    }
                };

                // National teams (city_key === 'all', e.g. USA at the World
                // Cup) aren't tied to one region — aggregate_database() copies
                // their completed/upcoming games into EVERY city's FIFA bucket,
                // so a live national game must fan out the same way. City teams
                // write only to their own region.
                $isNational = ($info['city_key'] === 'all');
                $wroteCityCount = 0;

                if ($isNational) {
                    foreach (array_keys($database) as $dbKey) {
                        if ($dbKey === 'all') continue; // 'all' handled separately below
                        if (!isset($database[$dbKey]['leagues'][$info['league']])) continue;
                        $applyLiveToBucket($database[$dbKey]['leagues'][$info['league']]);
                        $wroteCityCount++;
                    }
                } elseif (isset($database[$info['city_key']]['leagues'][$info['league']])) {
                    $applyLiveToBucket($database[$info['city_key']]['leagues'][$info['league']]);
                    $wroteCityCount = 1;
                }

                $wroteAll = false;
                if (isset($database['all']['leagues'][$info['league']])) {
                    $applyLiveToBucket($database['all']['leagues'][$info['league']]);
                    $wroteAll = true;
                }

                if ($log) {
                    $log(sprintf(
                        'apply key=%s matched %s (%s) abbr=%s %s %d-%d vs %s [%s] -> %s region bucket(s): %d written, all bucket: %s',
                        $key,
                        $info['name'],
                        $info['league'],
                        $abbr,
                        $liveStatus,
                        $teamScore,
                        $oppScore,
                        $record['opponent'],
                        $progress,
                        $isNational ? 'national (all regions)' : $info['city_key'],
                        $wroteCityCount,
                        $wroteAll ? 'written' : 'MISSING BUCKET'
                    ));
                }
            }
        }

        if ($log) {
            $log(sprintf(
                'apply key=%s summary: league=%s events=%d live(in)=%d competitorsMatched=%d unmatchedAbbrs=[%s]',
                $key,
                $league,
                $eventTotal,
                $inCount,
                $matched,
                implode(',', array_keys($unmatched))
            ));
        }
    }
}

function render_index_html(array $database, array $CITIES, string $timestamp): string
{
    $jsonDatabase = json_encode($database, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

    $optionsHtml = '';
    foreach ($CITIES as $key => $city) {
        $selected = ($key === 'chicago') ? ' selected' : '';
        $optionsHtml .= '                <option value="' . htmlspecialchars($key) . '"' . $selected . '>' . htmlspecialchars($city['label']) . '</option>' . "\n";
    }
    $optionsHtml .= '                <option value="all">All Cities</option>' . "\n";
    $optionsHtml .= '                <option value="__custom__">Customize&hellip;</option>' . "\n";

    $indexTemplate = <<<HTML
<?php
// AUTO-GENERATED at {$timestamp}
header("Cache-Control: public, max-age=300");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Catch up on your local pro teams in seconds. See recent game results and upcoming games over the next two weeks—all in one place.">
<title>Casual Fan Sports Report</title>
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
    
    /* Live Game Styles */
    .game-card.live { background: #fff1f2; border-color: #fecdd3; border-left: 3px solid #e11d48; }
    @media (prefers-color-scheme: dark) {
        .game-card.live { background: #4c0519; border-color: #881337; border-left: 3px solid #f43f5e; }
    }
    .live-indicator { 
        color: #e11d48; font-weight: 700; font-size: 0.72rem; text-transform: uppercase; 
        letter-spacing: 0.05em; animation: pulse 2s infinite; display: inline-block; margin-right: 0.35rem;
    }
    @media (prefers-color-scheme: dark) { .live-indicator { color: #f43f5e; } }
    @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
    
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
    <h1>Casual Fan Sports Report</h1>

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
                leagues[team.league] = { latest_timestamp: 0, live: [], games: [], upcoming: [] };
            }

            const teamLive = (leagueData.live || []).filter(g => g.team_name === team.name);
            const teamGames = (leagueData.games || []).filter(g => g.team_name === team.name);
            const teamUpcoming = (leagueData.upcoming || []).filter(g => g.team_name === team.name);

            leagues[team.league].live.push(...teamLive);
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
        try { return window.localStorage.getItem(CITY_STORAGE_KEY); } catch (e) { return null; }
    }

    function storeCity(cityId) {
        try { window.localStorage.setItem(CITY_STORAGE_KEY, cityId); } catch (e) { }
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
        try { return window.localStorage.getItem(CUSTOM_EDITOR_COLLAPSED_KEY) === '1'; } catch (e) { return false; }
    }

    function setEditorCollapsed(collapsed) {
        try { window.localStorage.setItem(CUSTOM_EDITOR_COLLAPSED_KEY, collapsed ? '1' : '0'); } catch (e) { }
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
            tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
        );
    }

    function renderMajorEventsHtml() {
        if (!majorEvents.length) return '';

        let html = '<h2>Championships &amp; Major Events</h2><div class="major-events">';
        majorEvents.forEach(evt => {
            const aClass = evt.team_a_winner ? ' winner' : '';
            const bClass = evt.team_b_winner ? ' winner' : '';

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
            const hasLive = data.live && data.live.length > 0;
            const hasGames = data.games && data.games.length > 0;
            const hasUpcoming = data.upcoming && data.upcoming.length > 0;

            if (!hasLive && !hasGames && !hasUpcoming) {
                if (league !== 'FIFA') emptyLeagues.push(league);
                continue;
            }

            html += `<h2>\${escapeHTML(league)}</h2>`;

            // 1. Live Games
            if (hasLive) {
                data.live.forEach(game => {
                    const title = `\${game.team_name} — \${game.live_status}`;
                    const details = `\${game.team_score}-\${game.opp_score} \${game.vsAt} \${game.opponent} (\${game.progress})`;
                    
                    html += `
                        <div class="game-card live">
                            <span class="live-indicator">● LIVE</span>
                            <strong>\${escapeHTML(title)}</strong>
                            <span class="game-details">\${escapeHTML(details)}</span>
                        </div>
                    `;
                });
            }
            
            // 2. Upcoming Games
            if (hasUpcoming) {
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

            // 3. Completed Games
            if (hasGames) {
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
    toggleCustomizeEditorBtn.addEventListener('click', () => setEditorCollapsed(!isEditorCollapsed()));

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

    try { window.localStorage.setItem(PAGE_LOAD_STORAGE_KEY, String(pageLoadedAt)); } catch (e) { }

    const pageLoadedLine = document.getElementById('page-loaded-line');
    if (pageLoadedLine) {
        const loadedDate = new Date(pageLoadedAt);
        const formatted = new Intl.DateTimeFormat('en-US', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
            hour: 'numeric', minute: '2-digit', second: '2-digit', timeZoneName: 'short'
        }).format(loadedDate);
        pageLoadedLine.textContent = `Page loaded: \${formatted}`;
    }

    function getChicagoParts(date) {
        const parts = new Intl.DateTimeFormat('en-US', {
            timeZone: 'America/Chicago', year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false
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
            return guess + (guess - guessAsUtc);
        }

        const todayAt2amUtc = chicagoWallClockToUtc(chicagoNow.year, chicagoNow.month, chicagoNow.day, 2);

        if (todayAt2amUtc <= now.getTime()) return todayAt2amUtc;
        
        const yesterday = new Date(now.getTime() - 24 * 60 * 60 * 1000);
        const chicagoYesterday = getChicagoParts(yesterday);
        return chicagoWallClockToUtc(chicagoYesterday.year, chicagoYesterday.month, chicagoYesterday.day, 2);
    }

    const staleBanner = document.getElementById('stale-banner');
    const staleRefreshLink = document.getElementById('stale-refresh-link');

    function checkStaleness() {
        if (pageLoadedAt < mostRecent2amCentral()) staleBanner.hidden = false;
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
HTML;

    return $indexTemplate;
}

function write_index_file(string $html, string $targetFile): void
{
    file_put_contents($targetFile, $html);
    if (function_exists('opcache_invalidate')) {
        opcache_invalidate($targetFile, true);
    }
}