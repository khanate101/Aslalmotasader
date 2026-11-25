<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
if ($argc < 3) { fwrite(STDERR,"Usage: php scripts/create_admin.php USERNAME PASSWORD [NAME]\n"); exit(1); }
[$script,$username,$password]=$argv; $name=$argv[3]??'مدير النظام';
if (strlen($password)<12) { fwrite(STDERR,"Password must be at least 12 characters.\n"); exit(1); }
$pdo=Database::connection(); $role=$pdo->query("SELECT id FROM roles WHERE role_key='super_admin'")->fetchColumn();
if (!$role) throw new RuntimeException('Run database/schema.sql first.');
$stmt=$pdo->prepare('INSERT INTO users(role_id,name,username,password_hash) VALUES(?,?,?,?)');
$stmt->execute([$role,$name,$username,password_hash($password,PASSWORD_DEFAULT)]);
fwrite(STDOUT,"Admin account created. Keep credentials private.\n");
