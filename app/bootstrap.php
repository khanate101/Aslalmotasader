<?php
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
function load_env(string $file): void {
 if (!is_file($file)) return;
 foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
  $line = trim($line); if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
  [$key,$value] = explode('=', $line, 2); $value = trim($value);
  if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'"))) ) $value = substr($value,1,-1);
  if (getenv(trim($key)) === false) putenv(trim($key).'='.$value);
 }
}
load_env(BASE_PATH.'/.env');
$config = require BASE_PATH.'/config/app.php';
date_default_timezone_set($config['timezone']);
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name('aslalmotasader_session');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$isHttps || filter_var(getenv('SESSION_SECURE') ?: 'false', FILTER_VALIDATE_BOOLEAN),'httponly'=>true,'samesite'=>'Lax']);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once BASE_PATH.'/app/helpers.php';
require_once BASE_PATH.'/app/Database.php';
require_once BASE_PATH.'/app/Security.php';
require_once BASE_PATH.'/app/Auth.php';
