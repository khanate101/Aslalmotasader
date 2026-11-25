<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$pdo=null;$error='';$done=false;
try {
 $pdo=Database::connection();
 $count=(int)$pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE r.role_key='super_admin'")->fetchColumn();
 if($count>0){http_response_code(410);exit('<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><title>تم إغلاق التهيئة</title><body style="font-family:sans-serif;max-width:650px;margin:60px auto;padding:20px"><h1>تم إغلاق صفحة إنشاء المدير</h1><p>يوجد مدير نظام رئيسي بالفعل. احذف الملف public/setup-admin.php من المشروع ثم سجل الدخول.</p><a href="/admin/login.php">الذهاب إلى تسجيل الدخول</a></body></html>');}
 $setupKey=(string)(getenv('INITIAL_ADMIN_SETUP_KEY')?:'');
 if(strlen($setupKey)<32){http_response_code(503);exit('تهيئة المدير غير مفعلة. أضف مفتاحاً عشوائياً لا يقل عن 32 حرفاً إلى INITIAL_ADMIN_SETUP_KEY في ملف .env.');}
 if($_SERVER['REQUEST_METHOD']==='POST'){
  Security::verifyCsrf();
  $submitted=(string)($_POST['setup_key']??'');$name=trim($_POST['name']??'');$username=trim($_POST['username']??'');$password=(string)($_POST['password']??'');$confirm=(string)($_POST['password_confirm']??'');
  if(!hash_equals($setupKey,$submitted))$error='مفتاح التهيئة غير صحيح.';
  elseif(mb_strlen($name)<2)$error='أدخل اسم المدير.';
  elseif(!preg_match('/^[A-Za-z0-9_.-]{3,100}$/',$username))$error='اسم المستخدم يجب أن يتكون من 3 أحرف على الأقل، ويقبل الحروف والأرقام والنقطة والشرطة.';
  elseif(strlen($password)<12)$error='كلمة المرور يجب ألا تقل عن 12 حرفاً.';
  elseif(!hash_equals($password,$confirm))$error='تأكيد كلمة المرور غير مطابق.';
  else {
   $lock=$pdo->query("SELECT GET_LOCK('aslalmotasader_initial_admin',10)")->fetchColumn();
   if((int)$lock!==1)throw new RuntimeException('تعذر قفل التهيئة. أعد المحاولة.');
   try {
    $pdo->beginTransaction();
    if((int)$pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE r.role_key='super_admin'")->fetchColumn()>0)throw new RuntimeException('تم إنشاء مدير النظام الرئيسي بالفعل. صفحة التهيئة مغلقة.');
    $role=$pdo->query("SELECT id FROM roles WHERE role_key='super_admin' LIMIT 1")->fetchColumn();
    if(!$role)throw new RuntimeException('استورد database/schema.sql أولاً.');
    $q=$pdo->prepare('INSERT INTO users(role_id,name,username,password_hash,status) VALUES(?,?,?,? ,\'active\')');
    $q->execute([(int)$role,$name,$username,password_hash($password,PASSWORD_DEFAULT)]);
    $pdo->commit();$done=true;
   } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
   finally{$pdo->query("SELECT RELEASE_LOCK('aslalmotasader_initial_admin')");}
  }
 }
} catch(Throwable $e){$error=$config['debug']?$e->getMessage():'تعذر إنشاء الحساب. تحقق من قاعدة البيانات والإعدادات.';}
?><!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>إنشاء المدير الأول | أصل المتصدر</title><link rel="stylesheet" href="/assets/css/app.css"><style>body{min-height:100vh;display:grid;place-items:center;background:#f3f6f9}.setup{width:min(480px,calc(100% - 28px));background:white;padding:28px;border:1px solid #e2e8ef;border-radius:16px;box-shadow:0 18px 55px #10263f12}.setup h1{font-size:23px;color:#10263f}.setup label{display:grid;gap:7px;margin:13px 0;font-size:14px}.setup input{width:100%;padding:12px;border:1px solid #d9e1e9;border-radius:8px}.setup button{width:100%;margin-top:14px}.note{background:#fff8e8;color:#78581b;padding:12px;border-radius:9px;font-size:13px}.error{background:#fff0f0;color:#9b2424;padding:12px;border-radius:9px}.success{background:#edf8f0;color:#176b39;padding:14px;border-radius:9px}</style></head><body><main class="setup"><a class="brand" href="/"><span class="brand-mark">م</span><b>أصل المتصدر</b></a><?php if($done):?><h1>تم إنشاء المدير الأول بنجاح</h1><p class="success">تم حفظ الحساب وتشفير كلمة المرور. أصبحت صفحة إنشاء المدير مغلقة تلقائياً.</p><p class="note">احذف الملف <b>public/setup-admin.php</b> من ملفات المشروع الآن، ثم سجل الدخول من صفحة الإدارة.</p><a class="btn btn-primary full" href="/admin/login.php">الانتقال لتسجيل الدخول</a><?php else:?><h1>إنشاء حساب المدير الأول</h1><p class="muted">هذه صفحة إعداد لمرة واحدة فقط. لا تتطلب تسجيل دخول إداري، لكنها تتطلب مفتاح التهيئة السري الموجود في ملف البيئة.</p><p class="note">لا تنشر الموقع للعامة قبل إنشاء المدير. ستُغلق الصفحة تلقائياً بمجرد وجود أول مستخدم، ومع ذلك احذف الملف بعد نجاح الإنشاء.</p><?php if($error):?><p class="error"><?=e($error)?></p><?php endif;?><form method="post" autocomplete="off"><input type="hidden" name="_csrf" value="<?=e(Security::csrf())?>"><label>مفتاح التهيئة السري<input type="password" name="setup_key" required autocomplete="off"></label><label>اسم المدير الكامل<input name="name" required maxlength="160"></label><label>اسم المستخدم<input name="username" required minlength="3" maxlength="100" autocomplete="username"></label><label>كلمة المرور (12 حرفاً على الأقل)<input type="password" name="password" required minlength="12" autocomplete="new-password"></label><label>تأكيد كلمة المرور<input type="password" name="password_confirm" required minlength="12" autocomplete="new-password"></label><button class="btn btn-primary" type="submit">إنشاء المدير الأول</button></form><?php endif;?></main></body></html>