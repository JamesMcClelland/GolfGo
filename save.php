<?php
header('Content-Type: application/json');

$raw = file_get_contents('php://input');
file_put_contents(__DIR__ . '/debug_input.txt', $raw); // Add this for debugging

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);      // Method Not Allowed
    echo json_encode(['status'=>'error','message'=>'POST required']);
    exit;
}

$raw = file_get_contents('php://input');
if (!$raw) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Empty body']);
    exit;
}

// Validate JSON
$data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
if (!is_array($data) || empty($data)) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Invalid JSON']);
    exit;
}

// Ensure data directory exists & is writeable
$dir = __DIR__ . '/data';
if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'Cannot create data directory']);
    exit;
}

// Build filename: e.g. data/20250626_141205.json
$filename = $dir . '/' . date('Ymd_His') . '.json';

if (file_put_contents($filename, $raw, LOCK_EX) === false) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'Write failed']);
    exit;
}

echo json_encode([
  'status' => 'ok',
  'file'   => basename($filename),
  'points' => count($data)
]);
