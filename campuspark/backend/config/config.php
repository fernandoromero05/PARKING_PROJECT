<?php
declare(strict_types=1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

return [
  'db' => [
    'host' => 'localhost',
    'name' => 'campuspark',
    'user' => 'root',
    'pass' => '',          // set to your phpMyAdmin/MySQL password
    'charset' => 'utf8mb4',
  ],
  'cors' => [
    'allow_origin' => '*', // for local dev; lock down later
  ],
  'python_api_base' => 'http://127.0.0.1:8000',
];