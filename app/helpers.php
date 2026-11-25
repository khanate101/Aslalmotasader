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


/** Store a validated image under public/uploads and return its public URL path. */
function upload_image_file(string $field, string $prefix='image', int $maxBytes=5242880): ?string {
 if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) return null;
 $file=$_FILES[$field];
 if (($file['error'] ?? UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return null;
 if (($file['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new RuntimeException('تعذر رفع الصورة. تحقق من حجم الملف وإعدادات الرفع.');
 if (!is_uploaded_file((string)$file['tmp_name']) || (int)$file['size']<1 || (int)$file['size']>$maxBytes) throw new RuntimeException('حجم الصورة غير صالح. الحد الأقصى 5 ميجابايت.');
 $finfo=new finfo(FILEINFO_MIME_TYPE);
 $mime=$finfo->file((string)$file['tmp_name']);
 $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
 if (!isset($extensions[$mime]) || @getimagesize((string)$file['tmp_name'])===false) throw new RuntimeException('صيغة الصورة غير مدعومة. استخدم JPG أو PNG أو WebP.');
 $dir=BASE_PATH.'/public/uploads';
 if (!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) throw new RuntimeException('تعذر إنشاء مجلد الصور.');
 $name=preg_replace('/[^a-z0-9_-]/i','',$prefix).'-'.bin2hex(random_bytes(16)).'.'.$extensions[$mime];
 if (!move_uploaded_file((string)$file['tmp_name'],$dir.'/'.$name)) throw new RuntimeException('تعذر حفظ الصورة على الخادم.');
 return '/uploads/'.$name;
}
