<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$city = isset($_GET['city']) ? trim((string) $_GET['city']) : '';

if ($city === '' || !isset($CITIES[$city])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid city']);
    exit;
}

$summary = fetch_city_summary($city, $SUMMARY_BASE_URL);

if ($summary === null) {
    http_response_code(502);
    echo json_encode(['error' => 'Summary unavailable']);
    exit;
}

echo json_encode($summary);
