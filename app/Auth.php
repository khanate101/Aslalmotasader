<?php
declare(strict_types=1);
final class Auth {
 public static function user(): ?array { return $_SESSION['user'] ?? null; }
 public static function check(): bool { return self::user() !== null; }
 public static function requireLogin(): array { $u=self::user(); if (!$u) redirect('/admin/login.php'); return $u; }
 public static function can(string $permission): bool {
  $u=self::user(); if (!$u) return false; if (($u['role'] ?? '')==='super_admin') return true;
  $q=Database::connection()->prepare('SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=? AND p.permission_key=? LIMIT 1');
  $q->execute([(int)$u['role_id'],$permission]); return (bool)$q->fetchColumn();
 }
 public static function requirePermission(string $permission): void { self::requireLogin(); if (!self::can($permission)) { http_response_code(403); exit('ليس لديك صلاحية لتنفيذ هذا الإجراء.'); } }
 public static function login(string $username,string $password): bool {
  $key=hash('sha256',strtolower(trim($username)).'|'.($_SERVER['REMOTE_ADDR']??'unknown'));
  $_SESSION['_login_attempts']=$_SESSION['_login_attempts']??[];
  $state=$_SESSION['_login_attempts'][$key]??['count'=>0,'until'=>0];
  if((int)$state['until']>time())return false;
  if((int)$state['until']!==0 && (int)$state['until']<=time())$state=['count'=>0,'until'=>0];
  $q=Database::connection()->prepare("SELECT u.id,u.name,u.username,u.password_hash,u.role_id,u.company_id,u.branch_id,r.role_key FROM users u JOIN roles r ON r.id=u.role_id WHERE u.username=? AND u.status='active' AND (r.role_key<>'company_manager' OR EXISTS (SELECT 1 FROM companies co WHERE co.id=u.company_id AND co.status='active')) LIMIT 1");
  $q->execute([$username]); $u=$q->fetch();
  if (!$u || !password_verify($password,$u['password_hash'])) {
   $state['count']=(int)$state['count']+1;
   if($state['count']>=8){$state['until']=time()+900;$state['count']=0;}
   $_SESSION['_login_attempts'][$key]=$state;
   return false;
  }
  unset($_SESSION['_login_attempts'][$key]);
  session_regenerate_id(true); unset($u['password_hash']); $u['role']=$u['role_key']; $_SESSION['user']=$u;
  Database::connection()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$u['id']]);
  return true;
 }
 public static function logout(): void { $_SESSION=[]; if (ini_get('session.use_cookies')) { $p=session_get_cookie_params(); setcookie(session_name(),'',time()-42000,$p['path'],$p['domain']??'',$p['secure'],$p['httponly']); } session_destroy(); }
}
