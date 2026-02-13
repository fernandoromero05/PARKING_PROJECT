<?php
declare(strict_types=1);

return [
  'db' => [
    'host' => '127.0.0.1',
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