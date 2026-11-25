<?php
declare(strict_types=1);
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $path): never { header('Location: '.$path); exit; }
function json_response(array $data, int $status=200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function setting(string $key, string $default=''): string {
 static $cache=[]; if (array_key_exists($key,$cache)) return $cache[$key];
 try { $q=Database::connection()->prepare('SELECT setting_value FROM settings WHERE setting_key=?'); $q->execute([$key]); $v=$q->fetchColumn(); return $cache[$key]=($v===false?$default:(string)$v); } catch(Throwable) { return $default; }
}
function money(mixed $amount): string { return number_format((float)$amount,2).' ر.س'; }
