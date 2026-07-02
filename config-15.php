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
    'nfl' => ['sport' => 'football',   'label' => 'Football'],
    'nba' => ['sport' => 'basketball', 'label' => 'Basketball'],
    'mlb' => ['sport' => 'baseball',   'label' => 'Baseball'],
    'nhl' => ['sport' => 'hockey',     'label' => 'Hockey'],
];

$CITIES = [
    'newyork_newengland' => [
        'label' => 'New York & New England',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Patriots', 'abbr' => 'ne'],
            ['league' => 'nba', 'name' => 'Celtics',  'abbr' => 'bos'],
            ['league' => 'mlb', 'name' => 'Red Sox',  'abbr' => 'bos'],
            ['league' => 'nhl', 'name' => 'Bruins',   'abbr' => 'bos'],
            ['league' => 'nfl', 'name' => 'Bills',    'abbr' => 'buf'],
            ['league' => 'nhl', 'name' => 'Sabres',   'abbr' => 'buf'],
            ['league' => 'nfl', 'name' => 'Giants',    'abbr' => 'nyg'],
            ['league' => 'nfl', 'name' => 'Jets',      'abbr' => 'nyj'],
            ['league' => 'nba', 'name' => 'Knicks',    'abbr' => 'ny'],
            ['league' => 'nba', 'name' => 'Nets',      'abbr' => 'bkn'],
            ['league' => 'mlb', 'name' => 'Yankees',   'abbr' => 'nyy'],
            ['league' => 'mlb', 'name' => 'Mets',      'abbr' => 'nym'],
            ['league' => 'nhl', 'name' => 'Rangers',   'abbr' => 'nyr'],
            ['league' => 'nhl', 'name' => 'Islanders', 'abbr' => 'nyi'],
        ],
    ],
    'mid_atlantic' => [
        'label' => 'Mid-Atlantic',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Ravens',  'abbr' => 'bal'],
            ['league' => 'mlb', 'name' => 'Orioles', 'abbr' => 'bal'],
            ['league' => 'nfl', 'name' => 'Eagles',   'abbr' => 'phi'],
            ['league' => 'nba', 'name' => '76ers',    'abbr' => 'phi'],
            ['league' => 'mlb', 'name' => 'Phillies', 'abbr' => 'phi'],
            ['league' => 'nhl', 'name' => 'Flyers',   'abbr' => 'phi'],
            ['league' => 'nfl', 'name' => 'Steelers', 'abbr' => 'pit'],
            ['league' => 'mlb', 'name' => 'Pirates',  'abbr' => 'pit'],
            ['league' => 'nhl', 'name' => 'Penguins', 'abbr' => 'pit'],
            ['league' => 'nfl', 'name' => 'Commanders', 'abbr' => 'was'],
            ['league' => 'nba', 'name' => 'Wizards',    'abbr' => 'was'],
            ['league' => 'mlb', 'name' => 'Nationals',  'abbr' => 'wsh'],
            ['league' => 'nhl', 'name' => 'Capitals',   'abbr' => 'wsh'],
        ],
    ],
    'southeast' => [
        'label' => 'Southeast',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Falcons', 'abbr' => 'atl'],
            ['league' => 'nba', 'name' => 'Hawks',   'abbr' => 'atl'],
            ['league' => 'mlb', 'name' => 'Braves',  'abbr' => 'atl'],
            ['league' => 'nfl', 'name' => 'Panthers',   'abbr' => 'car'],
            ['league' => 'nba', 'name' => 'Hornets',    'abbr' => 'cha'],
            ['league' => 'nhl', 'name' => 'Hurricanes', 'abbr' => 'car'],
            ['league' => 'nfl', 'name' => 'Titans',    'abbr' => 'ten'],
            ['league' => 'nhl', 'name' => 'Predators', 'abbr' => 'nsh'],
        ],
    ],
    'florida' => [
        'label' => 'Florida',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Dolphins', 'abbr' => 'mia'],
            ['league' => 'nba', 'name' => 'Heat',     'abbr' => 'mia'],
            ['league' => 'mlb', 'name' => 'Marlins',  'abbr' => 'mia'],
            ['league' => 'nhl', 'name' => 'Panthers', 'abbr' => 'fla'],
            ['league' => 'nfl', 'name' => 'Jaguars', 'abbr' => 'jax'],
            ['league' => 'nba', 'name' => 'Magic', 'abbr' => 'orl'],
            ['league' => 'nfl', 'name' => 'Buccaneers', 'abbr' => 'tb'],
            ['league' => 'mlb', 'name' => 'Rays',       'abbr' => 'tb'],
            ['league' => 'nhl', 'name' => 'Lightning',  'abbr' => 'tb'],
        ],
    ],
    'great_lakes' => [
        'label' => 'Great Lakes & Ohio Valley',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Bengals', 'abbr' => 'cin'],
            ['league' => 'mlb', 'name' => 'Reds',    'abbr' => 'cin'],
            ['league' => 'nfl', 'name' => 'Browns',     'abbr' => 'cle'],
            ['league' => 'nba', 'name' => 'Cavaliers',  'abbr' => 'cle'],
            ['league' => 'mlb', 'name' => 'Guardians',  'abbr' => 'cle'],
            ['league' => 'nhl', 'name' => 'Blue Jackets', 'abbr' => 'cbj'],
            ['league' => 'nfl', 'name' => 'Lions',     'abbr' => 'det'],
            ['league' => 'nba', 'name' => 'Pistons',   'abbr' => 'det'],
            ['league' => 'mlb', 'name' => 'Tigers',    'abbr' => 'det'],
            ['league' => 'nhl', 'name' => 'Red Wings', 'abbr' => 'det'],
            ['league' => 'nfl', 'name' => 'Colts',  'abbr' => 'ind'],
            ['league' => 'nba', 'name' => 'Pacers', 'abbr' => 'ind'],
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
            ['league' => 'nfl', 'name' => 'Packers', 'abbr' => 'gb'],
            ['league' => 'nba', 'name' => 'Bucks',   'abbr' => 'mil'],
            ['league' => 'mlb', 'name' => 'Brewers', 'abbr' => 'mil'],
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
        ],
    ],
    'midwest_central' => [
        'label' => 'Mid-Central Plains',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Chiefs', 'abbr' => 'kc'],
            ['league' => 'mlb', 'name' => 'Royals', 'abbr' => 'kc'],
            ['league' => 'nba', 'name' => 'Grizzlies', 'abbr' => 'mem'],
            ['league' => 'nfl', 'name' => 'Saints',   'abbr' => 'no'],
            ['league' => 'nba', 'name' => 'Pelicans', 'abbr' => 'no'],
            ['league' => 'mlb', 'name' => 'Cardinals', 'abbr' => 'stl'],
            ['league' => 'nhl', 'name' => 'Blues',     'abbr' => 'stl'],
        ],
    ],
    'texas_oklahoma' => [
        'label' => 'Texas & Oklahoma',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Cowboys',   'abbr' => 'dal'],
            ['league' => 'nba', 'name' => 'Mavericks', 'abbr' => 'dal'],
            ['league' => 'mlb', 'name' => 'Rangers',   'abbr' => 'tex'],
            ['league' => 'nhl', 'name' => 'Stars',     'abbr' => 'dal'],
            ['league' => 'nfl', 'name' => 'Texans',  'abbr' => 'hou'],
            ['league' => 'nba', 'name' => 'Rockets', 'abbr' => 'hou'],
            ['league' => 'mlb', 'name' => 'Astros',  'abbr' => 'hou'],
            ['league' => 'nba', 'name' => 'Thunder', 'abbr' => 'okc'],
            ['league' => 'nba', 'name' => 'Spurs', 'abbr' => 'sas'],
        ],
    ],
    'mountain_west' => [
        'label' => 'Mountain West & Southwest',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Cardinals',    'abbr' => 'ari'],
            ['league' => 'nba', 'name' => 'Suns',         'abbr' => 'phx'],
            ['league' => 'mlb', 'name' => 'Diamondbacks', 'abbr' => 'ari'],
            ['league' => 'nfl', 'name' => 'Broncos',   'abbr' => 'den'],
            ['league' => 'nba', 'name' => 'Nuggets',   'abbr' => 'den'],
            ['league' => 'mlb', 'name' => 'Rockies',   'abbr' => 'col'],
            ['league' => 'nhl', 'name' => 'Avalanche', 'abbr' => 'col'],
            ['league' => 'nfl', 'name' => 'Raiders',        'abbr' => 'lv'],
            ['league' => 'nhl', 'name' => 'Golden Knights', 'abbr' => 'vgk'],
            ['league' => 'nba', 'name' => 'Jazz',             'abbr' => 'uta'],
            ['league' => 'nhl', 'name' => 'Utah Hockey Club', 'abbr' => 'uta'],
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
        ],
    ],
    'northern_california' => [
        'label' => 'Northern California',
        'teams' => [
            ['league' => 'nba', 'name' => 'Kings', 'abbr' => 'sac'],
            ['league' => 'nfl', 'name' => '49ers',     'abbr' => 'sf'],
            ['league' => 'nba', 'name' => 'Warriors',  'abbr' => 'gsw'],
            ['league' => 'mlb', 'name' => 'Giants',    'abbr' => 'sf'],
            ['league' => 'mlb', 'name' => 'Athletics', 'abbr' => 'oak'],
            ['league' => 'nhl', 'name' => 'Sharks',    'abbr' => 'sjs'],
        ],
    ],
    'pacific_northwest' => [
        'label' => 'Pacific Northwest',
        'teams' => [
            ['league' => 'nba', 'name' => 'Trail Blazers', 'abbr' => 'por'],
            ['league' => 'nfl', 'name' => 'Seahawks', 'abbr' => 'sea'],
            ['league' => 'mlb', 'name' => 'Mariners', 'abbr' => 'sea'],
            ['league' => 'nhl', 'name' => 'Kraken',   'abbr' => 'sea'],
            ['league' => 'nhl', 'name' => 'Canucks', 'abbr' => 'van'],
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
            ['league' => 'nhl', 'name' => 'Canadiens', 'abbr' => 'mtl'],
            ['league' => 'nhl', 'name' => 'Senators', 'abbr' => 'ott'],
            ['league' => 'nba', 'name' => 'Raptors',     'abbr' => 'tor'],
            ['league' => 'mlb', 'name' => 'Blue Jays',   'abbr' => 'tor'],
            ['league' => 'nhl', 'name' => 'Maple Leafs', 'abbr' => 'tor'],
        ],
    ],
];