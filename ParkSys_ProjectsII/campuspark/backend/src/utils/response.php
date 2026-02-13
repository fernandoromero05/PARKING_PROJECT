<?php
declare(strict_types=1);

function json_ok(array $data = []): void {
  header('Content-Type: application/json');
  echo json_encode(array_merge(['ok' => true], $data));
  exit;
}

function json_fail(string $error, int $code = 400, array $extra = []): void {
  http_response_code($code);
  header('Content-Type: application/json');
  echo json_encode(array_merge(['ok' => false, 'error' => $error], $extra));
  exit;
}

function enable_cors(): void {
  $config = require __DIR__ . '/../../config/config.php';
  header('Access-Control-Allow-Origin: ' . $config['cors']['allow_origin']);
  header('Access-Control-Allow-Headers: Content-Type');
  header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
  if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
}

function start_session(): void {
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
}