# fakefanreport

Local sports recap for Chicago, Los Angeles, and New York.

## Pages

| File | Purpose |
|------|---------|
| `index.php` | Live ESPN scores (original) |
| `index2.php` | Pre-built scores + optional AI reports (**generated — do not edit by hand**) |
| `cron.php` | Fetches ESPN data and regenerates `index2.php` |

## Local development

Requires PHP 8+ with the cURL extension (default on macOS).

```bash
cd fakefanreport
php -S localhost:8080
```

Open http://localhost:8080/index2.php

### Regenerate index2.php (manual — no cron required)

```bash
php cron.php
```

This fetches ESPN schedules for all cities and writes a fresh `index2.php`. Run it whenever you want updated scores. On production, point a cron job at this script (e.g. every few hours).

AI reports are loaded via `summary.php`, which fetches from Backblaze on the server (avoids browser CORS issues).

### Config

- `config.php` — cities, teams, `$SUMMARY_BASE_URL` for AI JSON files

## Setup

This repo is a fork of [jefe317/fakefanreport](https://github.com/jefe317/fakefanreport) with AI report integration.

| Remote | URL |
|--------|-----|
| `origin` | `git@github.com:jantznick/fakefanreport.git` (your fork) |
| `upstream` | `https://github.com/jefe317/fakefanreport.git` (original) |

### First-time fork push

If the GitHub fork does not exist yet, create it at [github.com/jefe317/fakefanreport/fork](https://github.com/jefe317/fakefanreport/fork), then:

```bash
git push -u origin main
```

To pull upstream changes later:

```bash
git fetch upstream
git merge upstream/main
        # or rebase, as you prefer
```
