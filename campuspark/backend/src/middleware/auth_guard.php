<?php
declare(strict_types=1);

function require_auth(): int {
  start_session();
  if (!isset($_SESSION['user_id'])) {
    json_fail('Not authenticated', 401);
  }
  return (int)$_SESSION['user_id'];
}