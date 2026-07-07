<?php
/**
 * generate.php
 *
 * todo: add all major sports like wnba, college bball for march madness
 * todo: remove "sport" part of Brewers Won Baseball?
 * Interactive replacement for running cron.php blind on a schedule.
 */
declare(strict_types=1);
date_default_timezone_set('America/Chicago');
ini_set('memory_limit', '256M');

require_once __DIR__ . '/builder-core.php';

define('GENERATE_SECRET', '8JccoC7_9rrR@.UePf!!4_.6r@ds7kT');
if (defined('GENERATE_SECRET') && ($_GET['key'] ?? '') !== GENERATE_SECRET) {
    http_response_code(403);
    exit('Forbidden');
}

define('BUILD_DIR', __DIR__ . '/tmp_builds');

// --- Live-update debug logging ------------------------------------------
// Writes a timestamped line to live-debug.log next to this script. Enabled
// automatically for any level=live request, and for anything when ?debug=1
// is present. To turn it off, set LIVE_DEBUG to false below (or delete the
// live-debug.log file to clear it — it's append-only).
define('LIVE_DEBUG', false);
define('LIVE_DEBUG_FILE', __DIR__ . '/live-debug.log');

function live_debug_enabled(): bool {
    if (!LIVE_DEBUG) return false;
    $level = $_GET['level'] ?? 'full';
    return $level === 'live' || isset($_GET['debug']);
}

function live_log(string $msg): void {
    if (!live_debug_enabled()) return;
    $line = '[' . date('Y-m-d H:i:s') . '] '
        . ($_GET['action'] ?? '?') . '/' . ($_GET['level'] ?? '?')
        . ' ' . $msg . "\n";
    @file_put_contents(LIVE_DEBUG_FILE, $line, FILE_APPEND | LOCK_EX);
}

// A callable wrapper we can hand to fetch/apply helpers.
function live_logger(): callable {
    return function (string $msg): void { live_log($msg); };
}

function ensure_build_dir(): void {
    if (!is_dir(BUILD_DIR)) {
        mkdir(BUILD_DIR, 0700, true);
    }
}

function build_file_path(string $token): string {
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) throw new InvalidArgumentException('Invalid token');
    return BUILD_DIR . "/{$token}.json";
}

function cleanup_stale_builds(): void {
    foreach ((glob(BUILD_DIR . '/*.json') ?: []) as $file) {
        if (filemtime($file) < time() - 2 * 3600) @unlink($file);
    }
}

function read_build(string $token): array {
    $path = build_file_path($token);
    if (!file_exists($path)) return [];
    
    $fh = fopen($path, 'r');
    flock($fh, LOCK_SH);
    $raw = stream_get_contents($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function merge_step_into_build(string $token, array $miniDb, bool $isFailure = false): void {
    $path = build_file_path($token);
    $fh = fopen($path, 'c+');
    flock($fh, LOCK_EX);
    $raw = stream_get_contents($fh);
    $mainDb = json_decode($raw, true) ?: [];

    if (!isset($mainDb['_meta'])) $mainDb['_meta'] = ['failed_count' => 0];
    if ($isFailure) $mainDb['_meta']['failed_count']++;

    $cityKeys = array_keys($miniDb);
    foreach ($cityKeys as $cKey) {
        if ($cKey === '_major_events' || $cKey === '_all_teams' || $cKey === '_meta') continue;
        if (!isset($miniDb[$cKey]['leagues'])) continue;

        foreach ($miniDb[$cKey]['leagues'] as $league => $lData) {
            if (empty($lData['games']) && empty($lData['upcoming'])) continue;

            if (!isset($mainDb[$cKey]['leagues'][$league])) {
                $mainDb[$cKey]['leagues'][$league] = ['latest_timestamp' => 0, 'live' => [], 'games' => [], 'upcoming' => []];
            }

            $mainDb[$cKey]['leagues'][$league]['games'] = array_merge($mainDb[$cKey]['leagues'][$league]['games'], $lData['games']);
            $mainDb[$cKey]['leagues'][$league]['upcoming'] = array_merge($mainDb[$cKey]['leagues'][$league]['upcoming'], $lData['upcoming']);
            $mainDb[$cKey]['leagues'][$league]['latest_timestamp'] = max($mainDb[$cKey]['leagues'][$league]['latest_timestamp'], $lData['latest_timestamp']);
        }
    }

    if (!empty($miniDb['_major_events'])) {
        $mainDb['_major_events'] = array_merge($mainDb['_major_events'] ?? [], $miniDb['_major_events']);
    }

    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($mainDb));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
}

function merge_live_step_into_build(string $token, string $stepKey, array $data, array $CITIES): void {
    $path = build_file_path($token);
    $fh = fopen($path, 'c+');
    flock($fh, LOCK_EX);
    $raw = stream_get_contents($fh);
    $mainDb = json_decode($raw, true) ?: [];

    if (empty($mainDb)) {
        live_log("merge stepKey=$stepKey: baseline build file was EMPTY before overlay (latest_db.json missing/blank?)");
    }

    $liveBefore = live_count_all($mainDb);

    // Overlay live stats onto memory snapshot
    apply_live_scoreboards($mainDb, [$stepKey => $data], $CITIES, live_logger());

    $liveAfter = live_count_all($mainDb);
    live_log("merge stepKey=$stepKey: total live records before=$liveBefore after=$liveAfter");

    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($mainDb));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
}

/** Count every live record across all cities/leagues — used for before/after diagnostics. */
function live_count_all(array $db): int {
    $n = 0;
    foreach ($db as $cKey => $cData) {
        if (!is_array($cData) || empty($cData['leagues'])) continue;
        foreach ($cData['leagues'] as $lData) {
            if (!empty($lData['live'])) $n += count($lData['live']);
        }
    }
    return $n;
}

function json_response(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

// --- AJAX actions --------------------------------------------------------

$action = $_GET['action'] ?? '';
$level  = $_GET['level'] ?? 'full'; 
$steps  = $level === 'live' ? build_live_step_list() : build_step_list($CITIES, $MAJOR_EVENTS, $SPORT_LABELS);

if ($action !== '') {
    live_log(sprintf(
        'ROUTING action=%s level=%s (raw level param=%s) -> %s step list with %d steps',
        $action,
        $level,
        var_export($_GET['level'] ?? null, true),
        $level === 'live' ? 'LIVE' : 'FULL',
        count($steps)
    ));
}

if ($action === 'start') {
    ensure_build_dir();
    cleanup_stale_builds();

    $token = bin2hex(random_bytes(16));
    
    if ($level === 'live') {
        $masterDbPath = __DIR__ . '/latest_db.json';
        if (file_exists($masterDbPath)) {
            $db = json_decode(file_get_contents($masterDbPath), true) ?: [];
            file_put_contents(build_file_path($token), json_encode($db));
        } else {
            json_response(['ok' => false, 'error' => 'No Full Build exists yet. Run Level 1 (Full) first to create the baseline.']);
        }
    } else {
        $emptyDb = aggregate_database([], $CITIES, $MAJOR_EVENTS, $SPORT_LABELS);
        file_put_contents(build_file_path($token), json_encode($emptyDb));
    }

    json_response(['ok' => true, 'token' => $token, 'total' => count($steps)]);
}

if ($action === 'step') {
    $token = (string) ($_GET['token'] ?? '');
    $index = (int) ($_GET['step'] ?? -1);

    try { build_file_path($token); } catch (InvalidArgumentException $e) {
        json_response(['ok' => false, 'error' => 'Invalid build token'], 400);
    }

    if ($index < 0 || $index >= count($steps)) {
        json_response(['ok' => false, 'error' => 'Invalid step index'], 400);
    }

    $step    = $steps[$index];
    $started = microtime(true);

    live_log("STEP #$index key={$step['key']} label=\"{$step['label']}\" starting fetch");

    $data    = fetch_step($step, live_logger());
    $elapsed = (int) round((microtime(true) - $started) * 1000);

    live_log(sprintf(
        'STEP #%d key=%s fetch done in %dms — data=%s%s',
        $index,
        $step['key'],
        $elapsed,
        $data === null ? 'NULL (treated as failure)' : 'ok',
        (is_array($data) ? ' events=' . count($data['events'] ?? []) : '')
    ));

    if ($level === 'live') {
        if ($data !== null) {
            merge_live_step_into_build($token, $step['key'], $data, $CITIES);
        } else {
            live_log("STEP #$index key={$step['key']}: no data, recording as failed (no live overlay applied)");
            merge_step_into_build($token, [], true); 
        }
    } else {
        if ($data !== null) {
            $miniDb = aggregate_database([$step['key'] => $data], $CITIES, $MAJOR_EVENTS, $SPORT_LABELS);
            merge_step_into_build($token, $miniDb, false);
        } else {
            merge_step_into_build($token, [], true);
        }
    }

    json_response([
        'ok'         => true,
        'step'       => $index,
        'label'      => $step['label'],
        'success'    => $data !== null,
        'elapsed_ms' => $elapsed,
    ]);
}

if ($action === 'finalize') {
    $token = (string) ($_GET['token'] ?? '');

    try { build_file_path($token); } catch (InvalidArgumentException $e) {
        json_response(['ok' => false, 'error' => 'Invalid build token'], 400);
    }

    $database = read_build($token);
    $failedCount = $database['_meta']['failed_count'] ?? 0;
    unset($database['_meta']); 

    // Only full builds require sorting the completed/upcoming arrays. Live builds inherit 
    // the pre-sorted structure directly from latest_db.json
    if ($level === 'full') {
        $cityKeys = array_keys($CITIES);
        $cityKeys[] = 'all';
        foreach ($cityKeys as $cKey) {
            if (!isset($database[$cKey]['leagues'])) continue;
            uasort($database[$cKey]['leagues'], function ($a, $b) {
                return $b['latest_timestamp'] <=> $a['latest_timestamp'];
            });
            foreach ($database[$cKey]['leagues'] as &$lData) {
                usort($lData['games'], function ($a, $b) { return $b['timestamp'] <=> $a['timestamp']; });
                usort($lData['upcoming'], function ($a, $b) { return $a['timestamp'] <=> $b['timestamp']; });
            }
            unset($lData);
        }
        usort($database['_major_events'], function ($a, $b) {
            return strtotime($b['date_raw'] ?? 'now') <=> strtotime($a['date_raw'] ?? 'now');
        });

        // Save the compiled, sorted, fresh baseline schedule for future live runs
        file_put_contents(__DIR__ . '/latest_db.json', json_encode($database));
    }

    $timestamp = date('l, F j, Y g:i:s A T');
    $html      = render_index_html($database, $CITIES, $timestamp);

    $targetFile = __DIR__ . '/index.php';
    write_index_file($html, $targetFile);
    @unlink(build_file_path($token));

    json_response([
        'ok'           => true,
        'timestamp'    => $timestamp,
        'team_count'   => count($database['_all_teams'] ?? []),
        'city_count'   => count($CITIES),
        'failed_count' => $failedCount,
    ]);
}

// --- CRON actions --------------------------------------------------------

if ($action === 'cron_run') {
    ensure_build_dir();
    cleanup_stale_builds();
    $token = bin2hex(random_bytes(16));
    
    if ($level === 'live') {
        $masterDbPath = __DIR__ . '/latest_db.json';
        if (file_exists($masterDbPath)) {
            $db = json_decode(file_get_contents($masterDbPath), true) ?: [];
            file_put_contents(build_file_path($token), json_encode($db));
        } else {
            exit("Error: No Full Build exists. Run Level 1 before running Level 2 cron.\n");
        }
    } else {
        $emptyDb = aggregate_database([], $CITIES, $MAJOR_EVENTS, $SPORT_LABELS);
        file_put_contents(build_file_path($token), json_encode($emptyDb));
    }

    $failedCount = 0;

    foreach ($steps as $step) {
        live_log("CRON STEP key={$step['key']} label=\"{$step['label']}\"");
        $data = fetch_step($step, live_logger());
        live_log(sprintf('CRON STEP key=%s data=%s%s', $step['key'],
            $data === null ? 'NULL' : 'ok',
            is_array($data) ? ' events=' . count($data['events'] ?? []) : ''));
        if ($data !== null) {
            if ($level === 'live') {
                merge_live_step_into_build($token, $step['key'], $data, $CITIES);
            } else {
                $miniDb = aggregate_database([$step['key'] => $data], $CITIES, $MAJOR_EVENTS, $SPORT_LABELS);
                merge_step_into_build($token, $miniDb, false);
                unset($miniDb);
            }
        } else {
            merge_step_into_build($token, [], true);
            $failedCount++;
        }
        unset($data);
    }

    $database = read_build($token);
    unset($database['_meta']); 

    if ($level === 'full') {
        $cityKeys = array_keys($CITIES);
        $cityKeys[] = 'all';
        foreach ($cityKeys as $cKey) {
            if (!isset($database[$cKey]['leagues'])) continue;
            uasort($database[$cKey]['leagues'], function ($a, $b) {
                return $b['latest_timestamp'] <=> $a['latest_timestamp'];
            });
            foreach ($database[$cKey]['leagues'] as &$lData) {
                usort($lData['games'], function ($a, $b) { return $b['timestamp'] <=> $a['timestamp']; });
                usort($lData['upcoming'], function ($a, $b) { return $a['timestamp'] <=> $b['timestamp']; });
            }
            unset($lData);
        }
        usort($database['_major_events'], function ($a, $b) {
            return strtotime($b['date_raw'] ?? 'now') <=> strtotime($a['date_raw'] ?? 'now');
        });

        file_put_contents(__DIR__ . '/latest_db.json', json_encode($database));
    }

    $timestamp = date('l, F j, Y g:i:s A T');
    $html      = render_index_html($database, $CITIES, $timestamp);

    write_index_file($html, __DIR__ . '/index.php');
    @unlink(build_file_path($token));

    echo "Cron build ($level) complete at {$timestamp}.\n";
    echo "Failed requests: {$failedCount}\n";
    exit;
}

// --- Default: render the build page --------------------------------------

$stepsFullJs = json_encode(array_map(fn($s) => ['label' => $s['label']], build_step_list($CITIES, $MAJOR_EVENTS, $SPORT_LABELS)), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$stepsLiveJs = json_encode(array_map(fn($s) => ['label' => $s['label']], build_live_step_list()), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$keyParam = isset($_GET['key']) ? '&key=' . urlencode((string) $_GET['key']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Build Casual Fan Data</title>
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
        --accent: #2563eb;
        --live: #e11d48;
        --ok: #16a34a;
        --fail: #dc2626;
    }
    @media (prefers-color-scheme: dark) {
        :root {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --border: #334155;
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.5);
            --live: #f43f5e;
        }
    }
    body { font-family: system-ui, -apple-system, sans-serif; background: var(--bg-color); color: var(--text-primary); margin: 0; padding: 1rem; }
    .container { max-width: 700px; margin: 0 auto; }
    h1 { font-size: 1.25rem; font-weight: 800; margin: 0 0 0.25rem 0; }
    p.sub { color: var(--text-secondary); margin: 0 0 1rem 0; font-size: 0.9rem; }

    .panel {
        background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius);
        box-shadow: var(--shadow); padding: 1rem; margin-bottom: 1rem;
    }

    .controls { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem; flex-wrap: wrap; }
    button {
        font: inherit; font-weight: 700; font-size: 0.85rem; padding: 0.55rem 1rem;
        border-radius: 6px; border: 1px solid var(--accent); background: var(--accent);
        color: white; cursor: pointer;
    }
    button.live-btn { border-color: var(--live); background: var(--live); }
    button:disabled { opacity: 0.5; cursor: default; pointer-events: none; }

    .progress-track { height: 10px; border-radius: 999px; background: var(--border); overflow: hidden; flex: 1; min-width: 150px; }
    .progress-fill { height: 100%; width: 0%; background: var(--accent); transition: width 0.15s ease-out; }
    .progress-fill.live { background: var(--live); }
    .progress-count { font-size: 0.85rem; color: var(--text-secondary); white-space: nowrap; }

    .log {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: 0.78rem; line-height: 1.5; max-height: 360px; overflow-y: auto;
        background: var(--bg-color); border: 1px solid var(--border); border-radius: 6px;
        padding: 0.6rem 0.75rem;
    }
    .log-line { display: flex; gap: 0.5rem; white-space: pre-wrap; word-break: break-word; }
    .log-line .icon { flex: none; width: 1.1em; }
    .log-line.ok .icon { color: var(--ok); }
    .log-line.fail .icon { color: var(--fail); }
    .log-line .ms { flex: none; color: var(--text-secondary); }

    .summary { font-size: 0.9rem; }
    .summary strong { color: var(--ok); }
    .summary a { color: var(--accent); }
</style>
</head>
<body>
<main class="container">
    <h1>Build Casual Fan Data</h1>
    <p class="sub">Run Level 1 to compile all schedules, or Level 2 to fetch quick live updates.</p>

    <div class="panel">
        <div class="controls">
            <button id="start-btn-full" onclick="runBuild('full')">Full Build (Level 1)</button>
            <button id="start-btn-live" class="live-btn" onclick="runBuild('live')">Live Update (Level 2)</button>
            <div class="progress-track"><div class="progress-fill" id="progress-fill"></div></div>
            <div class="progress-count" id="progress-count">Ready</div>
        </div>
        <div class="log" id="log"></div>
    </div>

    <div class="panel" id="summary-panel" hidden>
        <div class="summary" id="summary"></div>
    </div>
</main>

<script>
    const STEPS_FULL = <?php echo $stepsFullJs; ?>;
    const STEPS_LIVE = <?php echo $stepsLiveJs; ?>;
    const KEY_PARAM = <?php echo json_encode($keyParam); ?>;

    const btnFull = document.getElementById('start-btn-full');
    const btnLive = document.getElementById('start-btn-live');
    const progressFill = document.getElementById('progress-fill');
    const progressCount = document.getElementById('progress-count');
    const log = document.getElementById('log');
    const summaryPanel = document.getElementById('summary-panel');
    const summary = document.getElementById('summary');

    function escapeHTML(str) {
        return String(str).replace(/[&<>'"]/g, tag => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
        }[tag] || tag));
    }

    function appendLog(ok, label, ms) {
        const row = document.createElement('div');
        row.className = 'log-line ' + (ok ? 'ok' : 'fail');
        row.innerHTML = `<span class="icon">${ok ? '✓' : '✗'}</span><span class="label">${escapeHTML(label)}</span><span class="ms">${ms}ms</span>`;
        log.appendChild(row);
        log.scrollTop = log.scrollHeight;
    }

    function setProgress(done, total) {
        progressFill.style.width = (done / total * 100).toFixed(1) + '%';
        progressCount.textContent = `${done} / ${total}`;
    }

    async function runBuild(level) {
        btnFull.disabled = true;
        btnLive.disabled = true;
        
        const isLive = level === 'live';
        const steps = isLive ? STEPS_LIVE : STEPS_FULL;
        const total = steps.length;
        
        if (isLive) progressFill.classList.add('live');
        else progressFill.classList.remove('live');

        log.innerHTML = '';
        summaryPanel.hidden = true;
        setProgress(0, total);

        const startRes = await fetch(`generate.php?action=start&level=${level}${KEY_PARAM}`);
        const startData = await startRes.json();
        
        if (!startData.ok) {
            appendLog(false, 'Could not start build: ' + (startData.error || 'unknown error'), 0);
            btnFull.disabled = false;
            btnLive.disabled = false;
            return;
        }
        
        const token = startData.token;
        let failedCount = 0;
        
        for (let i = 0; i < total; i++) {
            try {
                const res = await fetch(`generate.php?action=step&token=${token}&step=${i}&level=${level}${KEY_PARAM}`);
                const data = await res.json();
                if (!data.ok) {
                    appendLog(false, steps[i].label + ' — request failed', 0);
                    failedCount++;
                } else {
                    appendLog(data.success, data.label, data.elapsed_ms);
                    if (!data.success) failedCount++;
                }
            } catch (e) {
                appendLog(false, steps[i].label + ' — network error', 0);
                failedCount++;
            }
            setProgress(i + 1, total);
        }

        appendLog(true, 'Rendering index.php…', 0);
        const finalizeRes = await fetch(`generate.php?action=finalize&token=${token}&level=${level}${KEY_PARAM}`);
        const finalizeData = await finalizeRes.json();

        btnFull.disabled = false;
        btnLive.disabled = false;

        summaryPanel.hidden = false;
        if (finalizeData.ok) {
            summary.innerHTML = `<strong>Done.</strong> Generated index.php at ${escapeHTML(finalizeData.timestamp)}
                covering ${finalizeData.team_count} teams across ${finalizeData.city_count} cities
                (${finalizeData.failed_count} request${finalizeData.failed_count === 1 ? '' : 's'} failed).
                <br><a href="index.php" target="_blank">View index.php &rarr;</a>`;
        } else {
            summary.innerHTML = `<span style="color: var(--fail)">Finalize failed: ${escapeHTML(finalizeData.error || 'unknown error')}</span>`;
        }
    }
</script>
</body>
</html>