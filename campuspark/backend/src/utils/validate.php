<?php
declare(strict_types=1);

function require_json_body(): array {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!is_array($data)) {
    json_fail('Invalid JSON body', 400);
  }
  return $data;
}

function require_fields(array $data, array $fields): void {
  foreach ($fields as $f) {
    if (!array_key_exists($f, $data)) {
      json_fail("Missing field: $f", 400);
    }
  }
}