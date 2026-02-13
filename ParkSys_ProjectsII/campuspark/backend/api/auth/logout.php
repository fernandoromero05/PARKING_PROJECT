<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/utils/response.php';

enable_cors();
start_session();
session_destroy();
json_ok();