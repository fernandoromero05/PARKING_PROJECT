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
  $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
  // In development, we can allow everything by echoing the request's origin
  // for credentials to work. For production, you should validate against a list.
  if ($origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
  } else {
    header('Access-Control-Allow-Origin: *');
  }
  
  header('Access-Control-Allow-Credentials: true');
  header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
  header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
  
  if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
  }
}

function start_session(): void {
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
}