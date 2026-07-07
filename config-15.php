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
            ['league' => 'nfl', 'name' => 'Patriots',  'abbr' => 'ne'],
            ['league' => 'nba', 'name' => 'Celtics',   'abbr' => 'bos'],
            ['league' => 'mlb', 'name' => 'Red Sox',   'abbr' => 'bos'],
            ['league' => 'nhl', 'name' => 'Bruins',    'abbr' => 'bos'],
            ['league' => 'nfl', 'name' => 'Bills',     'abbr' => 'buf'],
            ['league' => 'nhl', 'name' => 'Sabres',    'abbr' => 'buf'],
            ['league' => 'nfl', 'name' => 'Giants',    'abbr' => 'nyg'],
            ['league' => 'nfl', 'name' => 'Jets',      'abbr' => 'nyj'],
            ['league' => 'nba', 'name' => 'Knicks',    'abbr' => 'ny'],
            ['league' => 'nba', 'name' => 'Nets',      'abbr' => 'bkn'],
            ['league' => 'mlb', 'name' => 'Yankees',   'abbr' => 'nyy'],
            ['league' => 'mlb', 'name' => 'Mets',      'abbr' => 'nym'],
            ['league' => 'nhl', 'name' => 'Rangers',   'abbr' => 'nyr'],
            ['league' => 'nhl', 'name' => 'Islanders', 'abbr' => 'nyi'],
            ['league' => 'nhl', 'name' => 'Devils',    'abbr' => 'nj'],
            ['league' => 'wnba', 'name' => 'Liberty',   'abbr' => 'ny'],
            ['league' => 'wnba', 'name' => 'Sun',       'abbr' => 'con'],
            ['league' => 'ncaam', 'name' => 'UConn',    'abbr' => 'conn', 'id' => '41'],
            ['league' => 'ncaaw', 'name' => 'UConn',    'abbr' => 'conn', 'id' => '41'],
        ],
    ],
    'mid_atlantic' => [
        'label' => 'Mid-Atlantic',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Ravens',     'abbr' => 'bal'],
            ['league' => 'mlb', 'name' => 'Orioles',    'abbr' => 'bal'],
            ['league' => 'nfl', 'name' => 'Eagles',     'abbr' => 'phi'],
            ['league' => 'nba', 'name' => '76ers',      'abbr' => 'phi'],
            ['league' => 'mlb', 'name' => 'Phillies',   'abbr' => 'phi'],
            ['league' => 'nhl', 'name' => 'Flyers',     'abbr' => 'phi'],
            ['league' => 'nfl', 'name' => 'Steelers',   'abbr' => 'pit'],
            ['league' => 'mlb', 'name' => 'Pirates',    'abbr' => 'pit'],
            ['league' => 'nhl', 'name' => 'Penguins',   'abbr' => 'pit'],
            ['league' => 'nfl', 'name' => 'Commanders', 'abbr' => 'wsh'],
            ['league' => 'nba', 'name' => 'Wizards',    'abbr' => 'wsh'],
            ['league' => 'mlb', 'name' => 'Nationals',  'abbr' => 'wsh'],
            ['league' => 'nhl', 'name' => 'Capitals',   'abbr' => 'wsh'],
            ['league' => 'wnba', 'name' => 'Mystics',    'abbr' => 'wsh'],
            ['league' => 'ncaam', 'name' => 'Villanova',  'abbr' => 'vill', 'id' => '222'],
            ['league' => 'ncaaw', 'name' => 'Villanova',  'abbr' => 'vill', 'id' => '222'],
            ['league' => 'ncaam', 'name' => 'Georgetown', 'abbr' => 'gtwn', 'id' => '46'],
            ['league' => 'ncaaw', 'name' => 'Georgetown', 'abbr' => 'gtwn', 'id' => '46'],
            ['league' => 'ncaam', 'name' => 'Maryland',   'abbr' => 'md', 'id' => '120'],
            ['league' => 'ncaaw', 'name' => 'Maryland',   'abbr' => 'md', 'id' => '120'],
        ],
    ],
    'southeast' => [
        'label' => 'Southeast',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Falcons',    'abbr' => 'atl'],
            ['league' => 'nba', 'name' => 'Hawks',      'abbr' => 'atl'],
            ['league' => 'mlb', 'name' => 'Braves',     'abbr' => 'atl'],
            ['league' => 'nfl', 'name' => 'Panthers',   'abbr' => 'car'],
            ['league' => 'nba', 'name' => 'Hornets',    'abbr' => 'cha'],
            ['league' => 'nhl', 'name' => 'Hurricanes', 'abbr' => 'car'],
            ['league' => 'nfl', 'name' => 'Titans',     'abbr' => 'ten'],
            ['league' => 'nhl', 'name' => 'Predators',  'abbr' => 'nsh'],
            ['league' => 'wnba', 'name' => 'Dream',      'abbr' => 'atl'],
            ['league' => 'ncaam', 'name' => 'Duke',           'abbr' => 'duke', 'id' => '150'],
            ['league' => 'ncaaw', 'name' => 'Duke',           'abbr' => 'duke', 'id' => '150'],
            ['league' => 'ncaam', 'name' => 'North Carolina', 'abbr' => 'unc', 'id' => '153'],
            ['league' => 'ncaaw', 'name' => 'North Carolina', 'abbr' => 'unc', 'id' => '153'],
            ['league' => 'ncaam', 'name' => 'Tennessee',      'abbr' => 'tenn', 'id' => '2633'],
            ['league' => 'ncaaw', 'name' => 'Tennessee',      'abbr' => 'tenn', 'id' => '2633'],
            ['league' => 'ncaam', 'name' => 'South Carolina', 'abbr' => 'sc', 'id' => '2579'],
            ['league' => 'ncaaw', 'name' => 'South Carolina', 'abbr' => 'sc', 'id' => '2579'],
        ],
    ],
    'florida' => [
        'label' => 'Florida',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Dolphins',   'abbr' => 'mia'],
            ['league' => 'nba', 'name' => 'Heat',       'abbr' => 'mia'],
            ['league' => 'mlb', 'name' => 'Marlins',    'abbr' => 'mia'],
            ['league' => 'nhl', 'name' => 'Panthers',   'abbr' => 'fla'],
            ['league' => 'nfl', 'name' => 'Jaguars',    'abbr' => 'jax'],
            ['league' => 'nba', 'name' => 'Magic',      'abbr' => 'orl'],
            ['league' => 'nfl', 'name' => 'Buccaneers', 'abbr' => 'tb'],
            ['league' => 'mlb', 'name' => 'Rays',       'abbr' => 'tb'],
            ['league' => 'nhl', 'name' => 'Lightning',  'abbr' => 'tb'],
            ['league' => 'ncaam', 'name' => 'Florida',  'abbr' => 'fla', 'id' => '57'],
            ['league' => 'ncaaw', 'name' => 'Florida',  'abbr' => 'fla', 'id' => '57'],
            ['league' => 'ncaam', 'name' => 'Miami',    'abbr' => 'mia', 'id' => '2390'],
            ['league' => 'ncaaw', 'name' => 'Miami',    'abbr' => 'mia', 'id' => '2390'],
        ],
    ],
    'great_lakes' => [
        'label' => 'Great Lakes & Ohio Valley',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Bengals',        'abbr' => 'cin'],
            ['league' => 'mlb', 'name' => 'Reds',           'abbr' => 'cin'],
            ['league' => 'nfl', 'name' => 'Browns',         'abbr' => 'cle'],
            ['league' => 'nba', 'name' => 'Cavaliers',      'abbr' => 'cle'],
            ['league' => 'mlb', 'name' => 'Guardians',      'abbr' => 'cle'],
            ['league' => 'nhl', 'name' => 'Blue Jackets',   'abbr' => 'cbj'],
            ['league' => 'nfl', 'name' => 'Lions',          'abbr' => 'det'],
            ['league' => 'nba', 'name' => 'Pistons',        'abbr' => 'det'],
            ['league' => 'mlb', 'name' => 'Tigers',         'abbr' => 'det'],
            ['league' => 'nhl', 'name' => 'Red Wings',      'abbr' => 'det'],
            ['league' => 'nfl', 'name' => 'Colts',          'abbr' => 'ind'],
            ['league' => 'nba', 'name' => 'Pacers',         'abbr' => 'ind'],
            ['league' => 'wnba', 'name' => 'Fever',         'abbr' => 'ind'],
            ['league' => 'ncaam', 'name' => 'Indiana',      'abbr' => 'iu', 'id' => '84'],
            ['league' => 'ncaaw', 'name' => 'Indiana',      'abbr' => 'iu', 'id' => '84'],
            ['league' => 'ncaam', 'name' => 'Ohio State',   'abbr' => 'osu', 'id' => '194'],
            ['league' => 'ncaaw', 'name' => 'Ohio State',   'abbr' => 'osu', 'id' => '194'],
            ['league' => 'ncaam', 'name' => 'Michigan St',  'abbr' => 'msu', 'id' => '127'],
            ['league' => 'ncaaw', 'name' => 'Michigan St',  'abbr' => 'msu', 'id' => '127'],
            ['league' => 'ncaam', 'name' => 'Michigan',     'abbr' => 'mich', 'id' => '130'],
            ['league' => 'ncaaw', 'name' => 'Michigan',     'abbr' => 'mich', 'id' => '130'],
        ],
    ],
    'chicago_wisconsin' => [
        'label' => 'Chicago & Wisconsin',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Bears',      'abbr' => 'chi'],
            ['league' => 'nba', 'name' => 'Bulls',      'abbr' => 'chi'],
            ['league' => 'mlb', 'name' => 'Cubs',       'abbr' => 'chc'],
            ['league' => 'mlb', 'name' => 'White Sox',  'abbr' => 'chw'],
            ['league' => 'nhl', 'name' => 'Blackhawks', 'abbr' => 'chi'],
            ['league' => 'nfl', 'name' => 'Packers',    'abbr' => 'gb'],
            ['league' => 'nba', 'name' => 'Bucks',      'abbr' => 'mil'],
            ['league' => 'mlb', 'name' => 'Brewers',    'abbr' => 'mil'],
            ['league' => 'wnba', 'name' => 'Sky',       'abbr' => 'chi'],
            ['league' => 'ncaam', 'name' => 'Illinois',  'abbr' => 'ill', 'id' => '356'],
            ['league' => 'ncaaw', 'name' => 'Illinois',  'abbr' => 'ill', 'id' => '356'],
            ['league' => 'ncaam', 'name' => 'Wisconsin', 'abbr' => 'wis', 'id' => '275'],
            ['league' => 'ncaaw', 'name' => 'Wisconsin', 'abbr' => 'wis', 'id' => '275'],
            ['league' => 'ncaam', 'name' => 'Marquette', 'abbr' => 'marq', 'id' => '269'],
            ['league' => 'ncaaw', 'name' => 'Marquette', 'abbr' => 'marq', 'id' => '269'],
        ],
    ],
    'upper_midwest' => [
        'label' => 'Upper Midwest',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Vikings',      'abbr' => 'min'],
            ['league' => 'nba', 'name' => 'Timberwolves', 'abbr' => 'min'],
            ['league' => 'mlb', 'name' => 'Twins',        'abbr' => 'min'],
            ['league' => 'nhl', 'name' => 'Wild',         'abbr' => 'min'],
            ['league' => 'nhl', 'name' => 'Jets',         'abbr' => 'wpg'],
            ['league' => 'wnba', 'name' => 'Lynx',        'abbr' => 'min'],
            ['league' => 'ncaam', 'name' => 'Minnesota',  'abbr' => 'minn', 'id' => '135'],
            ['league' => 'ncaaw', 'name' => 'Minnesota',  'abbr' => 'minn', 'id' => '135'],
        ],
    ],
    'midwest_central' => [
        'label' => 'Mid-Central Plains',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Chiefs',     'abbr' => 'kc'],
            ['league' => 'mlb', 'name' => 'Royals',     'abbr' => 'kc'],
            ['league' => 'nba', 'name' => 'Grizzlies',  'abbr' => 'mem'],
            ['league' => 'nfl', 'name' => 'Saints',     'abbr' => 'no'],
            ['league' => 'nba', 'name' => 'Pelicans',   'abbr' => 'no'],
            ['league' => 'mlb', 'name' => 'Cardinals',  'abbr' => 'stl'],
            ['league' => 'nhl', 'name' => 'Blues',      'abbr' => 'stl'],
            ['league' => 'ncaam', 'name' => 'Kansas',   'abbr' => 'ku', 'id' => '2305'],
            ['league' => 'ncaaw', 'name' => 'Kansas',   'abbr' => 'ku', 'id' => '2305'],
            ['league' => 'ncaam', 'name' => 'Memphis',  'abbr' => 'mem', 'id' => '235'],
            ['league' => 'ncaaw', 'name' => 'Memphis',  'abbr' => 'mem', 'id' => '235'],
        ],
    ],
    'texas_oklahoma' => [
        'label' => 'Texas & Oklahoma',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Cowboys',    'abbr' => 'dal'],
            ['league' => 'nba', 'name' => 'Mavericks',  'abbr' => 'dal'],
            ['league' => 'mlb', 'name' => 'Rangers',    'abbr' => 'tex'],
            ['league' => 'nhl', 'name' => 'Stars',      'abbr' => 'dal'],
            ['league' => 'nfl', 'name' => 'Texans',     'abbr' => 'hou'],
            ['league' => 'nba', 'name' => 'Rockets',    'abbr' => 'hou'],
            ['league' => 'mlb', 'name' => 'Astros',     'abbr' => 'hou'],
            ['league' => 'nba', 'name' => 'Thunder',    'abbr' => 'okc'],
            ['league' => 'nba', 'name' => 'Spurs',      'abbr' => 'sa'],
            ['league' => 'wnba', 'name' => 'Wings',      'abbr' => 'dal'],
            ['league' => 'ncaam', 'name' => 'Houston',   'abbr' => 'hou', 'id' => '248'],
            ['league' => 'ncaaw', 'name' => 'Houston',   'abbr' => 'hou', 'id' => '248'],
            ['league' => 'ncaam', 'name' => 'Baylor',    'abbr' => 'bay', 'id' => '239'],
            ['league' => 'ncaaw', 'name' => 'Baylor',    'abbr' => 'bay', 'id' => '239'],
            ['league' => 'ncaam', 'name' => 'Texas',     'abbr' => 'tex', 'id' => '251'],
            ['league' => 'ncaaw', 'name' => 'Texas',     'abbr' => 'tex', 'id' => '251'],
            ['league' => 'ncaam', 'name' => 'Oklahoma',  'abbr' => 'ou', 'id' => '201'],
            ['league' => 'ncaaw', 'name' => 'Oklahoma',  'abbr' => 'ou', 'id' => '201'],
        ],
    ],
    'mountain_west' => [
        'label' => 'Mountain West & Southwest',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Cardinals',      'abbr' => 'ari'],
            ['league' => 'nba', 'name' => 'Suns',           'abbr' => 'phx'],
            ['league' => 'mlb', 'name' => 'Diamondbacks',   'abbr' => 'ari'],
            ['league' => 'nfl', 'name' => 'Broncos',        'abbr' => 'den'],
            ['league' => 'nba', 'name' => 'Nuggets',        'abbr' => 'den'],
            ['league' => 'mlb', 'name' => 'Rockies',        'abbr' => 'col'],
            ['league' => 'nhl', 'name' => 'Avalanche',      'abbr' => 'col'],
            ['league' => 'nfl', 'name' => 'Raiders',        'abbr' => 'lv'],
            ['league' => 'nhl', 'name' => 'Golden Knights', 'abbr' => 'vgk'],
            ['league' => 'nba', 'name' => 'Jazz',           'abbr' => 'utah'],
            ['league' => 'nhl', 'name' => 'Mammoth',        'abbr' => 'utah'],
            ['league' => 'wnba', 'name' => 'Aces',          'abbr' => 'lv'],
            ['league' => 'wnba', 'name' => 'Mercury',       'abbr' => 'phx'],
            ['league' => 'ncaam', 'name' => 'Arizona',      'abbr' => 'ariz', 'id' => '12'],
            ['league' => 'ncaaw', 'name' => 'Arizona',      'abbr' => 'ariz', 'id' => '12'],
        ],
    ],
    'southern_california' => [
        'label' => 'Southern California',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Rams',     'abbr' => 'lar'],
            ['league' => 'nfl', 'name' => 'Chargers', 'abbr' => 'lac'],
            ['league' => 'nba', 'name' => 'Lakers',   'abbr' => 'lal'],
            ['league' => 'nba', 'name' => 'Clippers', 'abbr' => 'lac'],
            ['league' => 'mlb', 'name' => 'Dodgers',  'abbr' => 'lad'],
            ['league' => 'mlb', 'name' => 'Angels',   'abbr' => 'laa'],
            ['league' => 'nhl', 'name' => 'Kings',    'abbr' => 'la'],
            ['league' => 'mlb', 'name' => 'Padres',   'abbr' => 'sd'],
            ['league' => 'nhl', 'name' => 'Ducks',    'abbr' => 'ana'],
            ['league' => 'wnba', 'name' => 'Sparks',  'abbr' => 'la'],
            ['league' => 'ncaam', 'name' => 'UCLA',   'abbr' => 'ucla', 'id' => '26'],
            ['league' => 'ncaaw', 'name' => 'UCLA',   'abbr' => 'ucla', 'id' => '26'],
            ['league' => 'ncaam', 'name' => 'USC',    'abbr' => 'usc', 'id' => '30'],
            ['league' => 'ncaaw', 'name' => 'USC',    'abbr' => 'usc', 'id' => '30'],
        ],
    ],
    'northern_california' => [
        'label' => 'Northern California',
        'teams' => [
            ['league' => 'nba', 'name' => 'Kings',     'abbr' => 'sac'],
            ['league' => 'nfl', 'name' => '49ers',     'abbr' => 'sf'],
            ['league' => 'nba', 'name' => 'Warriors',  'abbr' => 'gs'],
            ['league' => 'mlb', 'name' => 'Giants',    'abbr' => 'sf'],
            ['league' => 'mlb', 'name' => 'Athletics', 'abbr' => 'ath'],
            ['league' => 'nhl', 'name' => 'Sharks',    'abbr' => 'sj'],
            ['league' => 'wnba', 'name' => 'Valkyries', 'abbr' => 'gs'],
            ['league' => 'ncaam', 'name' => 'Stanford',  'abbr' => 'stan', 'id' => '24'],
            ['league' => 'ncaaw', 'name' => 'Stanford',  'abbr' => 'stan', 'id' => '24'],
            ['league' => 'ncaam', 'name' => 'California', 'abbr' => 'cal', 'id' => '25'],
            ['league' => 'ncaaw', 'name' => 'California', 'abbr' => 'cal', 'id' => '25'],
        ],
    ],
    'pacific_northwest' => [
        'label' => 'Pacific Northwest',
        'teams' => [
            ['league' => 'nba', 'name' => 'Trail Blazers',  'abbr' => 'por'],
            ['league' => 'nfl', 'name' => 'Seahawks',       'abbr' => 'sea'],
            ['league' => 'mlb', 'name' => 'Mariners',       'abbr' => 'sea'],
            ['league' => 'nhl', 'name' => 'Kraken',         'abbr' => 'sea'],
            ['league' => 'nhl', 'name' => 'Canucks',        'abbr' => 'van'],
            ['league' => 'wnba', 'name' => 'Storm',         'abbr' => 'sea'],
            ['league' => 'wnba', 'name' => 'Fire',          'abbr' => 'por'],
            ['league' => 'ncaam', 'name' => 'Gonzaga',      'abbr' => 'gonz', 'id' => '2250'],
            ['league' => 'ncaaw', 'name' => 'Gonzaga',      'abbr' => 'gonz', 'id' => '2250'],
            ['league' => 'ncaam', 'name' => 'Oregon',       'abbr' => 'ore', 'id' => '2483'],
            ['league' => 'ncaaw', 'name' => 'Oregon',       'abbr' => 'ore', 'id' => '2483'],
        ],
    ],
    'western_canada' => [
        'label' => 'Western Canada',
        'teams' => [
            ['league' => 'nhl', 'name' => 'Flames', 'abbr' => 'cgy'],
            ['league' => 'nhl', 'name' => 'Oilers', 'abbr' => 'edm'],
        ],
    ],
    'eastern_canada' => [
        'label' => 'Eastern Canada',
        'teams' => [
            ['league' => 'nhl', 'name' => 'Canadiens',   'abbr' => 'mtl'],
            ['league' => 'nhl', 'name' => 'Senators',    'abbr' => 'ott'],
            ['league' => 'nba', 'name' => 'Raptors',     'abbr' => 'tor'],
            ['league' => 'mlb', 'name' => 'Blue Jays',   'abbr' => 'tor'],
            ['league' => 'nhl', 'name' => 'Maple Leafs', 'abbr' => 'tor'],
            ['league' => 'wnba', 'name' => 'Tempo',       'abbr' => 'tor'],
        ],
    ],
];