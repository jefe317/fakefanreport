<?php
/**
 * config.php
 *
 * All city/team/league data lives here. To add a new city, add a new
 * entry to $CITIES. To add a new league, add it to $SPORT_LABELS and
 * reference its key from a team entry.
 *
 * 'abbr' must match the team abbreviation ESPN's site API expects in a
 * URL like:
 * http://site.api.espn.com/apis/site/v2/sports/{sport}/{league}/teams/{abbr}/schedule
 *
 * Optional per-team fields used only by the "Customize your teams" picker:
 *   'city'   — the team's branding city, shown as a prefix in the picker
 *              label (e.g. 'Los Angeles' → "Los Angeles Angels"). Omit for
 *              college teams whose name is already a place ("Duke").
 *   'state'  — the state/province (province for Canadian teams), so a state
 *              query like "Florida" matches every team there. Set on all teams.
 *   'search' — extra space-separated search aliases for when the searchable
 *              home city differs from 'city'/'name' (e.g. Utah Jazz →
 *              'Salt Lake City', Angels → 'Anaheim'). Optional.
 */

$SPORT_LABELS = [
    'nfl'  => ['sport' => 'football',   'label' => 'Football'],
    'nba'  => ['sport' => 'basketball', 'label' => 'Basketball'],
    'wnba' => ['sport' => 'basketball', 'label' => 'Basketball'],
    'mlb'  => ['sport' => 'baseball',   'label' => 'Baseball'],
    'nhl'  => ['sport' => 'hockey',     'label' => 'Hockey'],
    // College basketball: the ESPN URL slug differs from the short league
    // key we use as the section header, so these carry an explicit 'slug'
    // (schedule_url uses it via a fallback in build_step_list()).
    'ncaam' => ['sport' => 'basketball', 'slug' => 'mens-college-basketball',   'label' => 'Basketball'],
    'ncaaw' => ['sport' => 'basketball', 'slug' => 'womens-college-basketball', 'label' => 'Basketball'],
];

/**
 * Major national/international "everyone watches this one" events —
 * not tied to any single city. Each entry describes where to look on
 * ESPN's scoreboard for that league/competition and which title
 * keywords mark the actual championship game (vs. an earlier round
 * that happens to still be completed and in the same window).
 *
 * 'window_months' is a list of month numbers (1-12) this championship
 * is typically played in. cron.php turns this into an actual
 * YYYYMMDD-YYYYMMDD date range for the scoreboard fetch — without a
 * range, ESPN's scoreboard endpoint only returns *today's* games, so a
 * plain "any completed game right now" search can wander into a
 * regular-season game outside of finals week and mislabel it as the
 * championship. Being generous here (a full month or two either side)
 * is safe: the postseason/keyword checks in find_championship_game()
 * still do the real narrowing.
 *
 * 'spans_new_year' handles championships whose window crosses into
 * January of the following year relative to when the season "started"
 * (e.g. Super Bowl in Feb belongs to the season that began the
 * previous fall) — cron.php uses this to pick the right year for each
 * month in the window.
 *
 * 'requires_postseason_flag' controls whether ESPN's season.type/slug
 * postseason check is required. Traditional US leagues (NFL/NBA/MLB/NHL)
 * always set this true. Single-elimination international tournaments
 * (World Cup, Champions League) don't reliably expose the same
 * season-type semantics in ESPN's soccer payloads, so for those we
 * instead rely purely on keyword matching against "final" — every
 * match in these competitions is inherently part of a knockout/group
 * stage, so the keyword is the meaningful signal, not the season type.
 */
$MAJOR_EVENTS = [
    [
        'key'                      => 'nfl_superbowl',
        'label'                    => 'Super Bowl',
        'sport'                    => 'football',
        'league'                   => 'nfl',
        'window_months'            => [1, 2],
        'spans_new_year'           => true,
        'requires_postseason_flag' => true,
        'keywords'                 => ['super bowl'],
    ],
    [
        'key'                      => 'nba_finals',
        'label'                    => 'NBA Finals',
        'sport'                    => 'basketball',
        'league'                   => 'nba',
        'window_months'            => [5, 6],
        'spans_new_year'           => false,
        'requires_postseason_flag' => true,
        'keywords'                 => ['nba finals', 'finals'],
    ],
    [
        'key'                      => 'wnba_finals',
        'label'                    => 'WNBA Finals',
        'sport'                    => 'basketball',
        'league'                   => 'wnba',
        'window_months'            => [9, 10],
        'spans_new_year'           => false,
        'requires_postseason_flag' => true,
        'keywords'                 => ['wnba finals', 'finals'],
    ],
    [
        'key'                      => 'ncaam_championship',
        'label'                    => "NCAA Men's Championship",
        'sport'                    => 'basketball',
        'league'                   => 'mens-college-basketball',
        'window_months'            => [3, 4],
        'spans_new_year'           => false,
        'requires_postseason_flag' => true,
        'keywords'                 => ['national championship'],
        // College scoreboard 404s on a dates range without groups=50.
        'scoreboard_params'        => ['groups' => '50'],
    ],
    [
        'key'                      => 'ncaaw_championship',
        'label'                    => "NCAA Women's Championship",
        'sport'                    => 'basketball',
        'league'                   => 'womens-college-basketball',
        'window_months'            => [3, 4],
        'spans_new_year'           => false,
        'requires_postseason_flag' => true,
        'keywords'                 => ['national championship'],
        // College scoreboard 404s on a dates range without groups=50.
        'scoreboard_params'        => ['groups' => '50'],
    ],
    [
        'key'                      => 'mlb_worldseries',
        'label'                    => 'World Series',
        'sport'                    => 'baseball',
        'league'                   => 'mlb',
        'window_months'            => [10, 11],
        'spans_new_year'           => false,
        'requires_postseason_flag' => true,
        'keywords'                 => ['world series'],
    ],
    [
        'key'                      => 'nhl_stanleycup',
        'label'                    => 'Stanley Cup Final',
        'sport'                    => 'hockey',
        'league'                   => 'nhl',
        'window_months'            => [5, 6, 7],
        'spans_new_year'           => false,
        'requires_postseason_flag' => true,
        'keywords'                 => ['stanley cup'],
    ],
    [
        'key'                      => 'fifa_worldcup',
        'label'                    => 'FIFA World Cup Final',
        'sport'                    => 'soccer',
        'league'                   => 'fifa.world',
        'window_months'            => [6, 7],
        'spans_new_year'           => false,
        'requires_postseason_flag' => false,
        'keywords'                 => ['world cup final', 'final'],
    ],
    [
        'key'                      => 'uefa_championsleague',
        'label'                    => 'UEFA Champions League Final',
        'sport'                    => 'soccer',
        'league'                   => 'uefa.champions',
        'window_months'            => [5, 6],
        'spans_new_year'           => false,
        'requires_postseason_flag' => false,
        'keywords'                 => ['champions league final', 'final'],
    ],
];

$CITIES = [
    'newyork_newengland' => [
        'label' => 'New York & New England',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Patriots',  'abbr' => 'ne',  'city' => 'New England', 'state' => 'Massachusetts', 'search' => 'Foxborough Boston'],
            ['league' => 'nba', 'name' => 'Celtics',   'abbr' => 'bos', 'city' => 'Boston',      'state' => 'Massachusetts'],
            ['league' => 'mlb', 'name' => 'Red Sox',   'abbr' => 'bos', 'city' => 'Boston',      'state' => 'Massachusetts'],
            ['league' => 'nhl', 'name' => 'Bruins',    'abbr' => 'bos', 'city' => 'Boston',      'state' => 'Massachusetts'],
            ['league' => 'nfl', 'name' => 'Bills',     'abbr' => 'buf', 'city' => 'Buffalo',     'state' => 'New York'],
            ['league' => 'nhl', 'name' => 'Sabres',    'abbr' => 'buf', 'city' => 'Buffalo',     'state' => 'New York'],
            ['league' => 'nfl', 'name' => 'Giants',    'abbr' => 'nyg', 'city' => 'New York',    'state' => 'New York', 'search' => 'New Jersey East Rutherford'],
            ['league' => 'nfl', 'name' => 'Jets',      'abbr' => 'nyj', 'city' => 'New York',    'state' => 'New York', 'search' => 'New Jersey East Rutherford'],
            ['league' => 'nba', 'name' => 'Knicks',    'abbr' => 'ny',  'city' => 'New York',    'state' => 'New York'],
            ['league' => 'nba', 'name' => 'Nets',      'abbr' => 'bkn', 'city' => 'Brooklyn',    'state' => 'New York', 'search' => 'New York'],
            ['league' => 'mlb', 'name' => 'Yankees',   'abbr' => 'nyy', 'city' => 'New York',    'state' => 'New York', 'search' => 'Bronx'],
            ['league' => 'mlb', 'name' => 'Mets',      'abbr' => 'nym', 'city' => 'New York',    'state' => 'New York', 'search' => 'Queens'],
            ['league' => 'nhl', 'name' => 'Rangers',   'abbr' => 'nyr', 'city' => 'New York',    'state' => 'New York'],
            ['league' => 'nhl', 'name' => 'Islanders', 'abbr' => 'nyi', 'city' => 'New York',    'state' => 'New York', 'search' => 'Long Island Elmont'],
            ['league' => 'nhl', 'name' => 'Devils',    'abbr' => 'nj',  'city' => 'New Jersey',  'state' => 'New Jersey', 'search' => 'Newark New York'],
            ['league' => 'wnba', 'name' => 'Liberty',   'abbr' => 'ny',  'city' => 'New York',   'state' => 'New York', 'search' => 'Brooklyn'],
            ['league' => 'wnba', 'name' => 'Sun',       'abbr' => 'con', 'city' => 'Connecticut','state' => 'Connecticut', 'search' => 'Uncasville'],
            ['league' => 'ncaam', 'name' => 'UConn',    'abbr' => 'conn', 'id' => '41', 'state' => 'Connecticut', 'search' => 'Storrs'],
            ['league' => 'ncaaw', 'name' => 'UConn',    'abbr' => 'conn', 'id' => '41', 'state' => 'Connecticut', 'search' => 'Storrs'],
        ],
    ],
    'mid_atlantic' => [
        'label' => 'Mid-Atlantic',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Ravens',     'abbr' => 'bal', 'city' => 'Baltimore',    'state' => 'Maryland'],
            ['league' => 'mlb', 'name' => 'Orioles',    'abbr' => 'bal', 'city' => 'Baltimore',    'state' => 'Maryland'],
            ['league' => 'nfl', 'name' => 'Eagles',     'abbr' => 'phi', 'city' => 'Philadelphia', 'state' => 'Pennsylvania'],
            ['league' => 'nba', 'name' => '76ers',      'abbr' => 'phi', 'city' => 'Philadelphia', 'state' => 'Pennsylvania'],
            ['league' => 'mlb', 'name' => 'Phillies',   'abbr' => 'phi', 'city' => 'Philadelphia', 'state' => 'Pennsylvania'],
            ['league' => 'nhl', 'name' => 'Flyers',     'abbr' => 'phi', 'city' => 'Philadelphia', 'state' => 'Pennsylvania'],
            ['league' => 'nfl', 'name' => 'Steelers',   'abbr' => 'pit', 'city' => 'Pittsburgh',   'state' => 'Pennsylvania'],
            ['league' => 'mlb', 'name' => 'Pirates',    'abbr' => 'pit', 'city' => 'Pittsburgh',   'state' => 'Pennsylvania'],
            ['league' => 'nhl', 'name' => 'Penguins',   'abbr' => 'pit', 'city' => 'Pittsburgh',   'state' => 'Pennsylvania'],
            ['league' => 'nfl', 'name' => 'Commanders', 'abbr' => 'wsh', 'city' => 'Washington',   'state' => 'D.C.', 'search' => 'DC Landover Maryland'],
            ['league' => 'nba', 'name' => 'Wizards',    'abbr' => 'wsh', 'city' => 'Washington',   'state' => 'D.C.', 'search' => 'DC'],
            ['league' => 'mlb', 'name' => 'Nationals',  'abbr' => 'wsh', 'city' => 'Washington',   'state' => 'D.C.', 'search' => 'DC'],
            ['league' => 'nhl', 'name' => 'Capitals',   'abbr' => 'wsh', 'city' => 'Washington',   'state' => 'D.C.', 'search' => 'DC'],
            ['league' => 'wnba', 'name' => 'Mystics',    'abbr' => 'wsh', 'city' => 'Washington',  'state' => 'D.C.', 'search' => 'DC'],
            ['league' => 'ncaam', 'name' => 'Villanova',  'abbr' => 'vill', 'id' => '222', 'state' => 'Pennsylvania', 'search' => 'Philadelphia'],
            ['league' => 'ncaaw', 'name' => 'Villanova',  'abbr' => 'vill', 'id' => '222', 'state' => 'Pennsylvania', 'search' => 'Philadelphia'],
            ['league' => 'ncaam', 'name' => 'Georgetown', 'abbr' => 'gtwn', 'id' => '46', 'state' => 'D.C.', 'search' => 'Washington DC'],
            ['league' => 'ncaaw', 'name' => 'Georgetown', 'abbr' => 'gtwn', 'id' => '46', 'state' => 'D.C.', 'search' => 'Washington DC'],
            ['league' => 'ncaam', 'name' => 'Maryland',   'abbr' => 'md', 'id' => '120', 'state' => 'Maryland', 'search' => 'College Park'],
            ['league' => 'ncaaw', 'name' => 'Maryland',   'abbr' => 'md', 'id' => '120', 'state' => 'Maryland', 'search' => 'College Park'],
        ],
    ],
    'southeast' => [
        'label' => 'Southeast',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Falcons',    'abbr' => 'atl', 'city' => 'Atlanta',   'state' => 'Georgia'],
            ['league' => 'nba', 'name' => 'Hawks',      'abbr' => 'atl', 'city' => 'Atlanta',   'state' => 'Georgia'],
            ['league' => 'mlb', 'name' => 'Braves',     'abbr' => 'atl', 'city' => 'Atlanta',   'state' => 'Georgia'],
            ['league' => 'nfl', 'name' => 'Panthers',   'abbr' => 'car', 'city' => 'Carolina',  'state' => 'North Carolina', 'search' => 'Charlotte'],
            ['league' => 'nba', 'name' => 'Hornets',    'abbr' => 'cha', 'city' => 'Charlotte', 'state' => 'North Carolina'],
            ['league' => 'nhl', 'name' => 'Hurricanes', 'abbr' => 'car', 'city' => 'Carolina',  'state' => 'North Carolina', 'search' => 'Raleigh'],
            ['league' => 'nfl', 'name' => 'Titans',     'abbr' => 'ten', 'city' => 'Tennessee', 'state' => 'Tennessee', 'search' => 'Nashville'],
            ['league' => 'nhl', 'name' => 'Predators',  'abbr' => 'nsh', 'city' => 'Nashville', 'state' => 'Tennessee'],
            ['league' => 'wnba', 'name' => 'Dream',      'abbr' => 'atl', 'city' => 'Atlanta',  'state' => 'Georgia'],
            ['league' => 'ncaam', 'name' => 'Duke',           'abbr' => 'duke', 'id' => '150', 'state' => 'North Carolina', 'search' => 'Durham'],
            ['league' => 'ncaaw', 'name' => 'Duke',           'abbr' => 'duke', 'id' => '150', 'state' => 'North Carolina', 'search' => 'Durham'],
            ['league' => 'ncaam', 'name' => 'North Carolina', 'abbr' => 'unc', 'id' => '153', 'state' => 'North Carolina', 'search' => 'Chapel Hill'],
            ['league' => 'ncaaw', 'name' => 'North Carolina', 'abbr' => 'unc', 'id' => '153', 'state' => 'North Carolina', 'search' => 'Chapel Hill'],
            ['league' => 'ncaam', 'name' => 'Tennessee',      'abbr' => 'tenn', 'id' => '2633', 'state' => 'Tennessee', 'search' => 'Knoxville'],
            ['league' => 'ncaaw', 'name' => 'Tennessee',      'abbr' => 'tenn', 'id' => '2633', 'state' => 'Tennessee', 'search' => 'Knoxville'],
            ['league' => 'ncaam', 'name' => 'South Carolina', 'abbr' => 'sc', 'id' => '2579', 'state' => 'South Carolina', 'search' => 'Columbia'],
            ['league' => 'ncaaw', 'name' => 'South Carolina', 'abbr' => 'sc', 'id' => '2579', 'state' => 'South Carolina', 'search' => 'Columbia'],
        ],
    ],
    'florida' => [
        'label' => 'Florida',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Dolphins',   'abbr' => 'mia', 'city' => 'Miami',     'state' => 'Florida'],
            ['league' => 'nba', 'name' => 'Heat',       'abbr' => 'mia', 'city' => 'Miami',     'state' => 'Florida'],
            ['league' => 'mlb', 'name' => 'Marlins',    'abbr' => 'mia', 'city' => 'Miami',     'state' => 'Florida'],
            ['league' => 'nhl', 'name' => 'Panthers',   'abbr' => 'fla', 'city' => 'Florida',   'state' => 'Florida', 'search' => 'Sunrise Miami'],
            ['league' => 'nfl', 'name' => 'Jaguars',    'abbr' => 'jax', 'city' => 'Jacksonville', 'state' => 'Florida'],
            ['league' => 'nba', 'name' => 'Magic',      'abbr' => 'orl', 'city' => 'Orlando',   'state' => 'Florida'],
            ['league' => 'nfl', 'name' => 'Buccaneers', 'abbr' => 'tb',  'city' => 'Tampa Bay', 'state' => 'Florida', 'search' => 'Tampa'],
            ['league' => 'mlb', 'name' => 'Rays',       'abbr' => 'tb',  'city' => 'Tampa Bay', 'state' => 'Florida', 'search' => 'Tampa St. Petersburg'],
            ['league' => 'nhl', 'name' => 'Lightning',  'abbr' => 'tb',  'city' => 'Tampa Bay', 'state' => 'Florida', 'search' => 'Tampa'],
            ['league' => 'ncaam', 'name' => 'Florida',  'abbr' => 'fla', 'id' => '57', 'state' => 'Florida', 'search' => 'Gainesville'],
            ['league' => 'ncaaw', 'name' => 'Florida',  'abbr' => 'fla', 'id' => '57', 'state' => 'Florida', 'search' => 'Gainesville'],
            ['league' => 'ncaam', 'name' => 'Miami',    'abbr' => 'mia', 'id' => '2390', 'state' => 'Florida', 'search' => 'Coral Gables'],
            ['league' => 'ncaaw', 'name' => 'Miami',    'abbr' => 'mia', 'id' => '2390', 'state' => 'Florida', 'search' => 'Coral Gables'],
        ],
    ],
    'great_lakes' => [
        'label' => 'Great Lakes & Ohio Valley',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Bengals',        'abbr' => 'cin', 'city' => 'Cincinnati', 'state' => 'Ohio'],
            ['league' => 'mlb', 'name' => 'Reds',           'abbr' => 'cin', 'city' => 'Cincinnati', 'state' => 'Ohio'],
            ['league' => 'nfl', 'name' => 'Browns',         'abbr' => 'cle', 'city' => 'Cleveland',  'state' => 'Ohio'],
            ['league' => 'nba', 'name' => 'Cavaliers',      'abbr' => 'cle', 'city' => 'Cleveland',  'state' => 'Ohio'],
            ['league' => 'mlb', 'name' => 'Guardians',      'abbr' => 'cle', 'city' => 'Cleveland',  'state' => 'Ohio'],
            ['league' => 'nhl', 'name' => 'Blue Jackets',   'abbr' => 'cbj', 'city' => 'Columbus',   'state' => 'Ohio'],
            ['league' => 'nfl', 'name' => 'Lions',          'abbr' => 'det', 'city' => 'Detroit',    'state' => 'Michigan'],
            ['league' => 'nba', 'name' => 'Pistons',        'abbr' => 'det', 'city' => 'Detroit',    'state' => 'Michigan'],
            ['league' => 'mlb', 'name' => 'Tigers',         'abbr' => 'det', 'city' => 'Detroit',    'state' => 'Michigan'],
            ['league' => 'nhl', 'name' => 'Red Wings',      'abbr' => 'det', 'city' => 'Detroit',    'state' => 'Michigan'],
            ['league' => 'nfl', 'name' => 'Colts',          'abbr' => 'ind', 'city' => 'Indianapolis', 'state' => 'Indiana'],
            ['league' => 'nba', 'name' => 'Pacers',         'abbr' => 'ind', 'city' => 'Indianapolis', 'state' => 'Indiana'],
            ['league' => 'wnba', 'name' => 'Fever',         'abbr' => 'ind', 'city' => 'Indianapolis', 'state' => 'Indiana'],
            ['league' => 'ncaam', 'name' => 'Indiana',      'abbr' => 'iu', 'id' => '84', 'state' => 'Indiana', 'search' => 'Bloomington'],
            ['league' => 'ncaaw', 'name' => 'Indiana',      'abbr' => 'iu', 'id' => '84', 'state' => 'Indiana', 'search' => 'Bloomington'],
            ['league' => 'ncaam', 'name' => 'Ohio State',   'abbr' => 'osu', 'id' => '194', 'state' => 'Ohio', 'search' => 'Columbus'],
            ['league' => 'ncaaw', 'name' => 'Ohio State',   'abbr' => 'osu', 'id' => '194', 'state' => 'Ohio', 'search' => 'Columbus'],
            ['league' => 'ncaam', 'name' => 'Michigan St',  'abbr' => 'msu', 'id' => '127', 'state' => 'Michigan', 'search' => 'East Lansing'],
            ['league' => 'ncaaw', 'name' => 'Michigan St',  'abbr' => 'msu', 'id' => '127', 'state' => 'Michigan', 'search' => 'East Lansing'],
            ['league' => 'ncaam', 'name' => 'Michigan',     'abbr' => 'mich', 'id' => '130', 'state' => 'Michigan', 'search' => 'Ann Arbor'],
            ['league' => 'ncaaw', 'name' => 'Michigan',     'abbr' => 'mich', 'id' => '130', 'state' => 'Michigan', 'search' => 'Ann Arbor'],
        ],
    ],
    'chicago_wisconsin' => [
        'label' => 'Chicago & Wisconsin',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Bears',      'abbr' => 'chi', 'city' => 'Chicago',   'state' => 'Illinois'],
            ['league' => 'nba', 'name' => 'Bulls',      'abbr' => 'chi', 'city' => 'Chicago',   'state' => 'Illinois'],
            ['league' => 'mlb', 'name' => 'Cubs',       'abbr' => 'chc', 'city' => 'Chicago',   'state' => 'Illinois'],
            ['league' => 'mlb', 'name' => 'White Sox',  'abbr' => 'chw', 'city' => 'Chicago',   'state' => 'Illinois'],
            ['league' => 'nhl', 'name' => 'Blackhawks', 'abbr' => 'chi', 'city' => 'Chicago',   'state' => 'Illinois'],
            ['league' => 'nfl', 'name' => 'Packers',    'abbr' => 'gb',  'city' => 'Green Bay', 'state' => 'Wisconsin'],
            ['league' => 'nba', 'name' => 'Bucks',      'abbr' => 'mil', 'city' => 'Milwaukee', 'state' => 'Wisconsin'],
            ['league' => 'mlb', 'name' => 'Brewers',    'abbr' => 'mil', 'city' => 'Milwaukee', 'state' => 'Wisconsin'],
            ['league' => 'wnba', 'name' => 'Sky',       'abbr' => 'chi', 'city' => 'Chicago',   'state' => 'Illinois'],
            ['league' => 'ncaam', 'name' => 'Illinois',  'abbr' => 'ill', 'id' => '356', 'state' => 'Illinois', 'search' => 'Champaign Urbana'],
            ['league' => 'ncaaw', 'name' => 'Illinois',  'abbr' => 'ill', 'id' => '356', 'state' => 'Illinois', 'search' => 'Champaign Urbana'],
            ['league' => 'ncaam', 'name' => 'Wisconsin', 'abbr' => 'wis', 'id' => '275', 'state' => 'Wisconsin', 'search' => 'Madison'],
            ['league' => 'ncaaw', 'name' => 'Wisconsin', 'abbr' => 'wis', 'id' => '275', 'state' => 'Wisconsin', 'search' => 'Madison'],
            ['league' => 'ncaam', 'name' => 'Marquette', 'abbr' => 'marq', 'id' => '269', 'state' => 'Wisconsin', 'search' => 'Milwaukee'],
            ['league' => 'ncaaw', 'name' => 'Marquette', 'abbr' => 'marq', 'id' => '269', 'state' => 'Wisconsin', 'search' => 'Milwaukee'],
        ],
    ],
    'upper_midwest' => [
        'label' => 'Upper Midwest',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Vikings',      'abbr' => 'min', 'city' => 'Minnesota', 'state' => 'Minnesota', 'search' => 'Minneapolis'],
            ['league' => 'nba', 'name' => 'Timberwolves', 'abbr' => 'min', 'city' => 'Minnesota', 'state' => 'Minnesota', 'search' => 'Minneapolis'],
            ['league' => 'mlb', 'name' => 'Twins',        'abbr' => 'min', 'city' => 'Minnesota', 'state' => 'Minnesota', 'search' => 'Minneapolis'],
            ['league' => 'nhl', 'name' => 'Wild',         'abbr' => 'min', 'city' => 'Minnesota', 'state' => 'Minnesota', 'search' => 'Saint Paul'],
            ['league' => 'nhl', 'name' => 'Jets',         'abbr' => 'wpg', 'city' => 'Winnipeg',  'state' => 'Manitoba', 'search' => 'Canada'],
            ['league' => 'wnba', 'name' => 'Lynx',        'abbr' => 'min', 'city' => 'Minnesota', 'state' => 'Minnesota', 'search' => 'Minneapolis'],
            ['league' => 'ncaam', 'name' => 'Minnesota',  'abbr' => 'minn', 'id' => '135', 'state' => 'Minnesota', 'search' => 'Minneapolis'],
            ['league' => 'ncaaw', 'name' => 'Minnesota',  'abbr' => 'minn', 'id' => '135', 'state' => 'Minnesota', 'search' => 'Minneapolis'],
        ],
    ],
    'midwest_central' => [
        'label' => 'Mid-Central Plains',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Chiefs',     'abbr' => 'kc',  'city' => 'Kansas City', 'state' => 'Missouri'],
            ['league' => 'mlb', 'name' => 'Royals',     'abbr' => 'kc',  'city' => 'Kansas City', 'state' => 'Missouri'],
            ['league' => 'nba', 'name' => 'Grizzlies',  'abbr' => 'mem', 'city' => 'Memphis',     'state' => 'Tennessee'],
            ['league' => 'nfl', 'name' => 'Saints',     'abbr' => 'no',  'city' => 'New Orleans', 'state' => 'Louisiana'],
            ['league' => 'nba', 'name' => 'Pelicans',   'abbr' => 'no',  'city' => 'New Orleans', 'state' => 'Louisiana'],
            ['league' => 'mlb', 'name' => 'Cardinals',  'abbr' => 'stl', 'city' => 'St. Louis',   'state' => 'Missouri', 'search' => 'Saint Louis'],
            ['league' => 'nhl', 'name' => 'Blues',      'abbr' => 'stl', 'city' => 'St. Louis',   'state' => 'Missouri', 'search' => 'Saint Louis'],
            ['league' => 'ncaam', 'name' => 'Kansas',   'abbr' => 'ku', 'id' => '2305', 'state' => 'Kansas', 'search' => 'Lawrence'],
            ['league' => 'ncaaw', 'name' => 'Kansas',   'abbr' => 'ku', 'id' => '2305', 'state' => 'Kansas', 'search' => 'Lawrence'],
            ['league' => 'ncaam', 'name' => 'Memphis',  'abbr' => 'mem', 'id' => '235', 'state' => 'Tennessee', 'search' => 'Memphis'],
            ['league' => 'ncaaw', 'name' => 'Memphis',  'abbr' => 'mem', 'id' => '235', 'state' => 'Tennessee', 'search' => 'Memphis'],
        ],
    ],
    'texas_oklahoma' => [
        'label' => 'Texas & Oklahoma',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Cowboys',    'abbr' => 'dal', 'city' => 'Dallas',     'state' => 'Texas', 'search' => 'Arlington'],
            ['league' => 'nba', 'name' => 'Mavericks',  'abbr' => 'dal', 'city' => 'Dallas',     'state' => 'Texas'],
            ['league' => 'mlb', 'name' => 'Rangers',    'abbr' => 'tex', 'city' => 'Texas',      'state' => 'Texas', 'search' => 'Arlington Dallas'],
            ['league' => 'nhl', 'name' => 'Stars',      'abbr' => 'dal', 'city' => 'Dallas',     'state' => 'Texas'],
            ['league' => 'nfl', 'name' => 'Texans',     'abbr' => 'hou', 'city' => 'Houston',    'state' => 'Texas'],
            ['league' => 'nba', 'name' => 'Rockets',    'abbr' => 'hou', 'city' => 'Houston',    'state' => 'Texas'],
            ['league' => 'mlb', 'name' => 'Astros',     'abbr' => 'hou', 'city' => 'Houston',    'state' => 'Texas'],
            ['league' => 'nba', 'name' => 'Thunder',    'abbr' => 'okc', 'city' => 'Oklahoma City', 'state' => 'Oklahoma'],
            ['league' => 'nba', 'name' => 'Spurs',      'abbr' => 'sa',  'city' => 'San Antonio', 'state' => 'Texas'],
            ['league' => 'wnba', 'name' => 'Wings',      'abbr' => 'dal', 'city' => 'Dallas',    'state' => 'Texas', 'search' => 'Arlington'],
            ['league' => 'ncaam', 'name' => 'Houston',   'abbr' => 'hou', 'id' => '248', 'state' => 'Texas', 'search' => 'Houston'],
            ['league' => 'ncaaw', 'name' => 'Houston',   'abbr' => 'hou', 'id' => '248', 'state' => 'Texas', 'search' => 'Houston'],
            ['league' => 'ncaam', 'name' => 'Baylor',    'abbr' => 'bay', 'id' => '239', 'state' => 'Texas', 'search' => 'Waco'],
            ['league' => 'ncaaw', 'name' => 'Baylor',    'abbr' => 'bay', 'id' => '239', 'state' => 'Texas', 'search' => 'Waco'],
            ['league' => 'ncaam', 'name' => 'Texas',     'abbr' => 'tex', 'id' => '251', 'state' => 'Texas', 'search' => 'Austin'],
            ['league' => 'ncaaw', 'name' => 'Texas',     'abbr' => 'tex', 'id' => '251', 'state' => 'Texas', 'search' => 'Austin'],
            ['league' => 'ncaam', 'name' => 'Oklahoma',  'abbr' => 'ou', 'id' => '201', 'state' => 'Oklahoma', 'search' => 'Norman'],
            ['league' => 'ncaaw', 'name' => 'Oklahoma',  'abbr' => 'ou', 'id' => '201', 'state' => 'Oklahoma', 'search' => 'Norman'],
        ],
    ],
    'mountain_west' => [
        'label' => 'Mountain West & Southwest',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Cardinals',      'abbr' => 'ari', 'city' => 'Arizona',   'state' => 'Arizona', 'search' => 'Glendale Phoenix'],
            ['league' => 'nba', 'name' => 'Suns',           'abbr' => 'phx', 'city' => 'Phoenix',   'state' => 'Arizona'],
            ['league' => 'mlb', 'name' => 'Diamondbacks',   'abbr' => 'ari', 'city' => 'Arizona',   'state' => 'Arizona', 'search' => 'Phoenix'],
            ['league' => 'nfl', 'name' => 'Broncos',        'abbr' => 'den', 'city' => 'Denver',    'state' => 'Colorado'],
            ['league' => 'nba', 'name' => 'Nuggets',        'abbr' => 'den', 'city' => 'Denver',    'state' => 'Colorado'],
            ['league' => 'mlb', 'name' => 'Rockies',        'abbr' => 'col', 'city' => 'Colorado',  'state' => 'Colorado', 'search' => 'Denver'],
            ['league' => 'nhl', 'name' => 'Avalanche',      'abbr' => 'col', 'city' => 'Colorado',  'state' => 'Colorado', 'search' => 'Denver'],
            ['league' => 'nfl', 'name' => 'Raiders',        'abbr' => 'lv',  'city' => 'Las Vegas', 'state' => 'Nevada'],
            ['league' => 'nhl', 'name' => 'Golden Knights', 'abbr' => 'vgk', 'city' => 'Vegas',     'state' => 'Nevada', 'search' => 'Las Vegas'],
            ['league' => 'nba', 'name' => 'Jazz',           'abbr' => 'utah', 'city' => 'Utah',     'state' => 'Utah', 'search' => 'Salt Lake City'],
            ['league' => 'nhl', 'name' => 'Mammoth',        'abbr' => 'utah', 'city' => 'Utah',     'state' => 'Utah', 'search' => 'Salt Lake City'],
            ['league' => 'wnba', 'name' => 'Aces',          'abbr' => 'lv',  'city' => 'Las Vegas', 'state' => 'Nevada'],
            ['league' => 'wnba', 'name' => 'Mercury',       'abbr' => 'phx', 'city' => 'Phoenix',   'state' => 'Arizona'],
            ['league' => 'ncaam', 'name' => 'Arizona',      'abbr' => 'ariz', 'id' => '12', 'state' => 'Arizona', 'search' => 'Tucson'],
            ['league' => 'ncaaw', 'name' => 'Arizona',      'abbr' => 'ariz', 'id' => '12', 'state' => 'Arizona', 'search' => 'Tucson'],
        ],
    ],
    'southern_california' => [
        'label' => 'Southern California',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Rams',     'abbr' => 'lar', 'city' => 'Los Angeles', 'state' => 'California', 'search' => 'Inglewood'],
            ['league' => 'nfl', 'name' => 'Chargers', 'abbr' => 'lac', 'city' => 'Los Angeles', 'state' => 'California', 'search' => 'Inglewood'],
            ['league' => 'nba', 'name' => 'Lakers',   'abbr' => 'lal', 'city' => 'Los Angeles', 'state' => 'California'],
            ['league' => 'nba', 'name' => 'Clippers', 'abbr' => 'lac', 'city' => 'Los Angeles', 'state' => 'California', 'search' => 'Inglewood'],
            ['league' => 'mlb', 'name' => 'Dodgers',  'abbr' => 'lad', 'city' => 'Los Angeles', 'state' => 'California'],
            ['league' => 'mlb', 'name' => 'Angels',   'abbr' => 'laa', 'city' => 'Los Angeles', 'state' => 'California', 'search' => 'Anaheim'],
            ['league' => 'nhl', 'name' => 'Kings',    'abbr' => 'la',  'city' => 'Los Angeles', 'state' => 'California'],
            ['league' => 'mlb', 'name' => 'Padres',   'abbr' => 'sd',  'city' => 'San Diego',   'state' => 'California'],
            ['league' => 'nhl', 'name' => 'Ducks',    'abbr' => 'ana', 'city' => 'Anaheim',     'state' => 'California', 'search' => 'Los Angeles'],
            ['league' => 'wnba', 'name' => 'Sparks',  'abbr' => 'la',  'city' => 'Los Angeles', 'state' => 'California'],
            ['league' => 'ncaam', 'name' => 'UCLA',   'abbr' => 'ucla', 'id' => '26', 'state' => 'California', 'search' => 'Los Angeles Westwood'],
            ['league' => 'ncaaw', 'name' => 'UCLA',   'abbr' => 'ucla', 'id' => '26', 'state' => 'California', 'search' => 'Los Angeles Westwood'],
            ['league' => 'ncaam', 'name' => 'USC',    'abbr' => 'usc', 'id' => '30', 'state' => 'California', 'search' => 'Los Angeles'],
            ['league' => 'ncaaw', 'name' => 'USC',    'abbr' => 'usc', 'id' => '30', 'state' => 'California', 'search' => 'Los Angeles'],
        ],
    ],
    'northern_california' => [
        'label' => 'Northern California',
        'teams' => [
            ['league' => 'nba', 'name' => 'Kings',     'abbr' => 'sac', 'city' => 'Sacramento',    'state' => 'California'],
            ['league' => 'nfl', 'name' => '49ers',     'abbr' => 'sf',  'city' => 'San Francisco', 'state' => 'California', 'search' => 'Santa Clara'],
            ['league' => 'nba', 'name' => 'Warriors',  'abbr' => 'gs',  'city' => 'Golden State',  'state' => 'California', 'search' => 'San Francisco Oakland'],
            ['league' => 'mlb', 'name' => 'Giants',    'abbr' => 'sf',  'city' => 'San Francisco', 'state' => 'California'],
            ['league' => 'mlb', 'name' => 'Athletics', 'abbr' => 'ath', 'state' => 'California', 'search' => 'Oakland Sacramento West Sacramento'],
            ['league' => 'nhl', 'name' => 'Sharks',    'abbr' => 'sj',  'city' => 'San Jose',      'state' => 'California'],
            ['league' => 'wnba', 'name' => 'Valkyries', 'abbr' => 'gs', 'city' => 'Golden State',  'state' => 'California', 'search' => 'San Francisco'],
            ['league' => 'ncaam', 'name' => 'Stanford',  'abbr' => 'stan', 'id' => '24', 'state' => 'California', 'search' => 'Palo Alto'],
            ['league' => 'ncaaw', 'name' => 'Stanford',  'abbr' => 'stan', 'id' => '24', 'state' => 'California', 'search' => 'Palo Alto'],
            ['league' => 'ncaam', 'name' => 'California', 'abbr' => 'cal', 'id' => '25', 'state' => 'California', 'search' => 'Berkeley'],
            ['league' => 'ncaaw', 'name' => 'California', 'abbr' => 'cal', 'id' => '25', 'state' => 'California', 'search' => 'Berkeley'],
        ],
    ],
    'pacific_northwest' => [
        'label' => 'Pacific Northwest',
        'teams' => [
            ['league' => 'nba', 'name' => 'Trail Blazers',  'abbr' => 'por', 'city' => 'Portland',  'state' => 'Oregon'],
            ['league' => 'nfl', 'name' => 'Seahawks',       'abbr' => 'sea', 'city' => 'Seattle',   'state' => 'Washington'],
            ['league' => 'mlb', 'name' => 'Mariners',       'abbr' => 'sea', 'city' => 'Seattle',   'state' => 'Washington'],
            ['league' => 'nhl', 'name' => 'Kraken',         'abbr' => 'sea', 'city' => 'Seattle',   'state' => 'Washington'],
            ['league' => 'nhl', 'name' => 'Canucks',        'abbr' => 'van', 'city' => 'Vancouver', 'state' => 'British Columbia', 'search' => 'Canada'],
            ['league' => 'wnba', 'name' => 'Storm',         'abbr' => 'sea', 'city' => 'Seattle',   'state' => 'Washington'],
            ['league' => 'wnba', 'name' => 'Fire',          'abbr' => 'por', 'city' => 'Portland',  'state' => 'Oregon'],
            ['league' => 'ncaam', 'name' => 'Gonzaga',      'abbr' => 'gonz', 'id' => '2250', 'state' => 'Washington', 'search' => 'Spokane'],
            ['league' => 'ncaaw', 'name' => 'Gonzaga',      'abbr' => 'gonz', 'id' => '2250', 'state' => 'Washington', 'search' => 'Spokane'],
            ['league' => 'ncaam', 'name' => 'Oregon',       'abbr' => 'ore', 'id' => '2483', 'state' => 'Oregon', 'search' => 'Eugene'],
            ['league' => 'ncaaw', 'name' => 'Oregon',       'abbr' => 'ore', 'id' => '2483', 'state' => 'Oregon', 'search' => 'Eugene'],
        ],
    ],
    'western_canada' => [
        'label' => 'Western Canada',
        'teams' => [
            ['league' => 'nhl', 'name' => 'Flames', 'abbr' => 'cgy', 'city' => 'Calgary',  'state' => 'Alberta', 'search' => 'Canada'],
            ['league' => 'nhl', 'name' => 'Oilers', 'abbr' => 'edm', 'city' => 'Edmonton', 'state' => 'Alberta', 'search' => 'Canada'],
        ],
    ],
    'eastern_canada' => [
        'label' => 'Eastern Canada',
        'teams' => [
            ['league' => 'nhl', 'name' => 'Canadiens',   'abbr' => 'mtl', 'city' => 'Montreal', 'state' => 'Quebec', 'search' => 'Canada'],
            ['league' => 'nhl', 'name' => 'Senators',    'abbr' => 'ott', 'city' => 'Ottawa',   'state' => 'Ontario', 'search' => 'Canada'],
            ['league' => 'nba', 'name' => 'Raptors',     'abbr' => 'tor', 'city' => 'Toronto',  'state' => 'Ontario', 'search' => 'Canada'],
            ['league' => 'mlb', 'name' => 'Blue Jays',   'abbr' => 'tor', 'city' => 'Toronto',  'state' => 'Ontario', 'search' => 'Canada'],
            ['league' => 'nhl', 'name' => 'Maple Leafs', 'abbr' => 'tor', 'city' => 'Toronto',  'state' => 'Ontario', 'search' => 'Canada'],
            ['league' => 'wnba', 'name' => 'Tempo',       'abbr' => 'tor', 'city' => 'Toronto', 'state' => 'Ontario', 'search' => 'Canada'],
        ],
    ],
];