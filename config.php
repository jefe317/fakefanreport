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
    'arizona' => [
        'label' => 'Arizona / Phoenix',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Cardinals',    'abbr' => 'ari'],
            ['league' => 'nba', 'name' => 'Suns',         'abbr' => 'phx'],
            ['league' => 'mlb', 'name' => 'Diamondbacks', 'abbr' => 'ari'],
        ],
    ],
    'atlanta' => [
        'label' => 'Atlanta',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Falcons', 'abbr' => 'atl'],
            ['league' => 'nba', 'name' => 'Hawks',   'abbr' => 'atl'],
            ['league' => 'mlb', 'name' => 'Braves',  'abbr' => 'atl'],
        ],
    ],
    'baltimore' => [
        'label' => 'Baltimore',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Ravens',  'abbr' => 'bal'],
            ['league' => 'mlb', 'name' => 'Orioles', 'abbr' => 'bal'],
        ],
    ],
    'boston' => [
        'label' => 'Boston / New England',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Patriots', 'abbr' => 'ne'],
            ['league' => 'nba', 'name' => 'Celtics',  'abbr' => 'bos'],
            ['league' => 'mlb', 'name' => 'Red Sox',  'abbr' => 'bos'],
            ['league' => 'nhl', 'name' => 'Bruins',   'abbr' => 'bos'],
        ],
    ],
    'buffalo' => [
        'label' => 'Buffalo',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Bills',  'abbr' => 'buf'],
            ['league' => 'nhl', 'name' => 'Sabres', 'abbr' => 'buf'],
        ],
    ],
    'calgary' => [
        'label' => 'Calgary',
        'teams' => [
            ['league' => 'nhl', 'name' => 'Flames', 'abbr' => 'cgy'],
        ],
    ],
    'carolina' => [
        'label' => 'Carolina / Charlotte',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Panthers',   'abbr' => 'car'],
            ['league' => 'nba', 'name' => 'Hornets',    'abbr' => 'cha'],
            ['league' => 'nhl', 'name' => 'Hurricanes', 'abbr' => 'car'],
        ],
    ],
    'chicago' => [
        'label' => 'Chicago',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Bears',      'abbr' => 'chi'],
            ['league' => 'nba', 'name' => 'Bulls',      'abbr' => 'chi'],
            ['league' => 'mlb', 'name' => 'Cubs',       'abbr' => 'chc'],
            ['league' => 'mlb', 'name' => 'White Sox',  'abbr' => 'chw'],
            ['league' => 'nhl', 'name' => 'Blackhawks', 'abbr' => 'chi'],
        ],
    ],
    'cincinnati' => [
        'label' => 'Cincinnati',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Bengals', 'abbr' => 'cin'],
            ['league' => 'mlb', 'name' => 'Reds',    'abbr' => 'cin'],
        ],
    ],
    'cleveland' => [
        'label' => 'Cleveland',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Browns',     'abbr' => 'cle'],
            ['league' => 'nba', 'name' => 'Cavaliers',  'abbr' => 'cle'],
            ['league' => 'mlb', 'name' => 'Guardians',  'abbr' => 'cle'],
        ],
    ],
    'colorado' => [
        'label' => 'Colorado / Denver',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Broncos',   'abbr' => 'den'],
            ['league' => 'nba', 'name' => 'Nuggets',   'abbr' => 'den'],
            ['league' => 'mlb', 'name' => 'Rockies',   'abbr' => 'col'],
            ['league' => 'nhl', 'name' => 'Avalanche', 'abbr' => 'col'],
        ],
    ],
    'columbus' => [
        'label' => 'Columbus',
        'teams' => [
            ['league' => 'nhl', 'name' => 'Blue Jackets', 'abbr' => 'cbj'],
        ],
    ],
    'dallas' => [
        'label' => 'Dallas / Texas',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Cowboys',   'abbr' => 'dal'],
            ['league' => 'nba', 'name' => 'Mavericks', 'abbr' => 'dal'],
            ['league' => 'mlb', 'name' => 'Rangers',   'abbr' => 'tex'],
            ['league' => 'nhl', 'name' => 'Stars',     'abbr' => 'dal'],
        ],
    ],
    'detroit' => [
        'label' => 'Detroit',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Lions',     'abbr' => 'det'],
            ['league' => 'nba', 'name' => 'Pistons',   'abbr' => 'det'],
            ['league' => 'mlb', 'name' => 'Tigers',    'abbr' => 'det'],
            ['league' => 'nhl', 'name' => 'Red Wings', 'abbr' => 'det'],
        ],
    ],
    'edmonton' => [
        'label' => 'Edmonton',
        'teams' => [
            ['league' => 'nhl', 'name' => 'Oilers', 'abbr' => 'edm'],
        ],
    ],
    'florida' => [
        'label' => 'Miami / South Florida',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Dolphins', 'abbr' => 'mia'],
            ['league' => 'nba', 'name' => 'Heat',     'abbr' => 'mia'],
            ['league' => 'mlb', 'name' => 'Marlins',  'abbr' => 'mia'],
            ['league' => 'nhl', 'name' => 'Panthers', 'abbr' => 'fla'],
        ],
    ],
    'greenbay' => [
        'label' => 'Green Bay',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Packers', 'abbr' => 'gb'],
        ],
    ],
    'houston' => [
        'label' => 'Houston',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Texans',  'abbr' => 'hou'],
            ['league' => 'nba', 'name' => 'Rockets', 'abbr' => 'hou'],
            ['league' => 'mlb', 'name' => 'Astros',  'abbr' => 'hou'],
        ],
    ],
    'indiana' => [
        'label' => 'Indianapolis / Indiana',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Colts',  'abbr' => 'ind'],
            ['league' => 'nba', 'name' => 'Pacers', 'abbr' => 'ind'],
        ],
    ],
    'jacksonville' => [
        'label' => 'Jacksonville',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Jaguars', 'abbr' => 'jax'],
        ],
    ],
    'kansascity' => [
        'label' => 'Kansas City',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Chiefs', 'abbr' => 'kc'],
            ['league' => 'mlb', 'name' => 'Royals', 'abbr' => 'kc'],
        ],
    ],
    'lasvegas' => [
        'label' => 'Las Vegas',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Raiders',        'abbr' => 'lv'],
            ['league' => 'nhl', 'name' => 'Golden Knights', 'abbr' => 'vgk'],
        ],
    ],
    'losangeles' => [
        'label' => 'Los Angeles',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Rams',     'abbr' => 'lar'],
            ['league' => 'nfl', 'name' => 'Chargers', 'abbr' => 'lac'],
            ['league' => 'nba', 'name' => 'Lakers',   'abbr' => 'lal'],
            ['league' => 'nba', 'name' => 'Clippers', 'abbr' => 'lac'],
            ['league' => 'mlb', 'name' => 'Dodgers',  'abbr' => 'lad'],
            ['league' => 'mlb', 'name' => 'Angels',   'abbr' => 'laa'],
            ['league' => 'nhl', 'name' => 'Kings',    'abbr' => 'la'],
        ],
    ],
    'memphis' => [
        'label' => 'Memphis',
        'teams' => [
            ['league' => 'nba', 'name' => 'Grizzlies', 'abbr' => 'mem'],
        ],
    ],
    'milwaukee' => [
        'label' => 'Milwaukee',
        'teams' => [
            ['league' => 'nba', 'name' => 'Bucks',   'abbr' => 'mil'],
            ['league' => 'mlb', 'name' => 'Brewers', 'abbr' => 'mil'],
        ],
    ],
    'minnesota' => [
        'label' => 'Minnesota / Minneapolis',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Vikings',      'abbr' => 'min'],
            ['league' => 'nba', 'name' => 'Timberwolves', 'abbr' => 'min'],
            ['league' => 'mlb', 'name' => 'Twins',        'abbr' => 'min'],
            ['league' => 'nhl', 'name' => 'Wild',         'abbr' => 'min'],
        ],
    ],
    'montreal' => [
        'label' => 'Montreal',
        'teams' => [
            ['league' => 'nhl', 'name' => 'Canadiens', 'abbr' => 'mtl'],
        ],
    ],
    'nashville' => [
        'label' => 'Nashville / Tennessee',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Titans',    'abbr' => 'ten'],
            ['league' => 'nhl', 'name' => 'Predators', 'abbr' => 'nsh'],
        ],
    ],
    'neworleans' => [
        'label' => 'New Orleans',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Saints',   'abbr' => 'no'],
            ['league' => 'nba', 'name' => 'Pelicans', 'abbr' => 'no'],
        ],
    ],
    'newyork' => [
        'label' => 'New York',
        'teams' => [
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
    'oklahomacity' => [
        'label' => 'Oklahoma City',
        'teams' => [
            ['league' => 'nba', 'name' => 'Thunder', 'abbr' => 'okc'],
        ],
    ],
    'orlando' => [
        'label' => 'Orlando',
        'teams' => [
            ['league' => 'nba', 'name' => 'Magic', 'abbr' => 'orl'],
        ],
    ],
    'ottawa' => [
        'label' => 'Ottawa',
        'teams' => [
            ['league' => 'nhl', 'name' => 'Senators', 'abbr' => 'ott'],
        ],
    ],
    'philadelphia' => [
        'label' => 'Philadelphia',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Eagles',   'abbr' => 'phi'],
            ['league' => 'nba', 'name' => '76ers',    'abbr' => 'phi'],
            ['league' => 'mlb', 'name' => 'Phillies', 'abbr' => 'phi'],
            ['league' => 'nhl', 'name' => 'Flyers',   'abbr' => 'phi'],
        ],
    ],
    'pittsburgh' => [
        'label' => 'Pittsburgh',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Steelers', 'abbr' => 'pit'],
            ['league' => 'mlb', 'name' => 'Pirates',  'abbr' => 'pit'],
            ['league' => 'nhl', 'name' => 'Penguins', 'abbr' => 'pit'],
        ],
    ],
    'portland' => [
        'label' => 'Portland',
        'teams' => [
            ['league' => 'nba', 'name' => 'Trail Blazers', 'abbr' => 'por'],
        ],
    ],
    'sacramento' => [
        'label' => 'Sacramento',
        'teams' => [
            ['league' => 'nba', 'name' => 'Kings', 'abbr' => 'sac'],
        ],
    ],
    'sanantonio' => [
        'label' => 'San Antonio',
        'teams' => [
            ['league' => 'nba', 'name' => 'Spurs', 'abbr' => 'sas'],
        ],
    ],
    'sandiego' => [
        'label' => 'San Diego',
        'teams' => [
            ['league' => 'mlb', 'name' => 'Padres', 'abbr' => 'sd'],
        ],
    ],
    'sanfrancisco' => [
        'label' => 'San Francisco / Bay Area',
        'teams' => [
            ['league' => 'nfl', 'name' => '49ers',     'abbr' => 'sf'],
            ['league' => 'nba', 'name' => 'Warriors',  'abbr' => 'gsw'],
            ['league' => 'mlb', 'name' => 'Giants',    'abbr' => 'sf'],
            ['league' => 'mlb', 'name' => 'Athletics', 'abbr' => 'oak'],
            ['league' => 'nhl', 'name' => 'Sharks',    'abbr' => 'sjs'],
        ],
    ],
    'seattle' => [
        'label' => 'Seattle',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Seahawks', 'abbr' => 'sea'],
            ['league' => 'mlb', 'name' => 'Mariners', 'abbr' => 'sea'],
            ['league' => 'nhl', 'name' => 'Kraken',   'abbr' => 'sea'],
        ],
    ],
    'stlouis' => [
        'label' => 'St. Louis',
        'teams' => [
            ['league' => 'mlb', 'name' => 'Cardinals', 'abbr' => 'stl'],
            ['league' => 'nhl', 'name' => 'Blues',     'abbr' => 'stl'],
        ],
    ],
    'tampabay' => [
        'label' => 'Tampa Bay',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Buccaneers', 'abbr' => 'tb'],
            ['league' => 'mlb', 'name' => 'Rays',       'abbr' => 'tb'],
            ['league' => 'nhl', 'name' => 'Lightning',  'abbr' => 'tb'],
        ],
    ],
    'toronto' => [
        'label' => 'Toronto',
        'teams' => [
            ['league' => 'nba', 'name' => 'Raptors',     'abbr' => 'tor'],
            ['league' => 'mlb', 'name' => 'Blue Jays',   'abbr' => 'tor'],
            ['league' => 'nhl', 'name' => 'Maple Leafs', 'abbr' => 'tor'],
        ],
    ],
    'utah' => [
        'label' => 'Utah / Salt Lake City',
        'teams' => [
            ['league' => 'nba', 'name' => 'Jazz',             'abbr' => 'uta'],
            ['league' => 'nhl', 'name' => 'Utah Hockey Club', 'abbr' => 'uta'],
        ],
    ],
    'vancouver' => [
        'label' => 'Vancouver',
        'teams' => [
            ['league' => 'nhl', 'name' => 'Canucks', 'abbr' => 'van'],
        ],
    ],
    'washington' => [
        'label' => 'Washington D.C.',
        'teams' => [
            ['league' => 'nfl', 'name' => 'Commanders', 'abbr' => 'was'],
            ['league' => 'nba', 'name' => 'Wizards',    'abbr' => 'was'],
            ['league' => 'mlb', 'name' => 'Nationals',  'abbr' => 'wsh'],
            ['league' => 'nhl', 'name' => 'Capitals',   'abbr' => 'wsh'],
        ],
    ],
    'winnipeg' => [
        'label' => 'Winnipeg',
        'teams' => [
            ['league' => 'nhl', 'name' => 'Jets', 'abbr' => 'wpg'],
        ],
    ],
];