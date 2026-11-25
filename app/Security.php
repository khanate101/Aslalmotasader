<?php
declare(strict_types=1);
final class Security {
 public static function csrf(): string { if (empty($_SESSION['_csrf'])) $_SESSION['_csrf']=bin2hex(random_bytes(32)); return $_SESSION['_csrf']; }
 public static function verifyCsrf(): void {
  $token=$_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
  if (!is_string($token) || !hash_equals(self::csrf(),$token)) { http_response_code(419); exit('انتهت صلاحية النموذج. أعد تحميل الصفحة وحاول مجدداً.'); }
 }
 public static function requireMethod(string $method): void { if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== strtoupper($method)) { http_response_code(405); exit('Method Not Allowed'); } }
}
