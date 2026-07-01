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
 *   http://site.api.espn.com/apis/site/v2/sports/{sport}/{league}/teams/{abbr}/schedule
 */

// Base URL for daily AI summary JSON (one file per city slug, e.g. chicago.json)
$SUMMARY_BASE_URL = 'https://f004.backblazeb2.com/file/sports-summaries/summaries/';

$SPORT_LABELS = [
    'nfl' => ['sport' => 'football',   'label' => 'Football'],
    'nba' => ['sport' => 'basketball', 'label' => 'Basketball'],
    'mlb' => ['sport' => 'baseball',   'label' => 'Baseball'],
    'nhl' => ['sport' => 'hockey',     'label' => 'Hockey'],
];

$CITIES = [
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
];
