<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';

$dsn = sprintf(
  'mysql:host=%s;dbname=%s;charset=%s',
  $config['db']['host'],
  $config['db']['name'],
  $config['db']['charset']
);

$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
  $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], $options);
} catch (PDOException $e) {
  http_response_code(500);
  header('Content-Type: application/json');
  echo json_encode(['ok' => false, 'error' => 'DB connection failed']);
  exit;
}