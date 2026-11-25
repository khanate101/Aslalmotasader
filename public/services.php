<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$pdo = Database::connection();
$services = $pdo->query("SELECT * FROM services WHERE status='active' ORDER BY title")->fetchAll();
$error = '';
$success = '';
$wa = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::verifyCsrf();
    $name = trim($_POST['name'] ?? '');
    $phone = preg_replace('/[^0-9+]/', '', $_POST['phone'] ?? '');
    $serviceId = (int)($_POST['service_id'] ?? 0);
    $people = max(1, min(50, (int)($_POST['people'] ?? 1)));
    $nationality = trim($_POST['nationality'] ?? '');
    $details = trim($_POST['details'] ?? '');
    $validService = false;
    foreach ($services as $service) if ((int)$service['id'] === $serviceId) $validService = true;
    if (mb_strlen($name) < 3 || strlen($phone) < 8 || !$validService) $error = 'يرجى إكمال الاسم والهاتف واختيار خدمة منشورة.';
    else try {
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO customers(full_name,phone,nationality) VALUES(?,?,?) ON DUPLICATE KEY UPDATE full_name=VALUES(full_name),nationality=VALUES(nationality)')->execute([$name,$phone,$nationality]);
        $q = $pdo->prepare('SELECT id FROM customers WHERE phone=?');
        $q->execute([$phone]);
        $customerId = (int)$q->fetchColumn();
        $ref = 'SRV-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
        $pdo->prepare('INSERT INTO service_requests(reference_code,customer_id,service_id,nationality,people_count,details) VALUES(?,?,?,?,?,?)')->execute([$ref,$customerId,$serviceId,$nationality,$people,$details]);
        $pdo->commit();
        $success = $ref;
        $selected = '';
        foreach ($services as $service) if ((int)$service['id'] === $serviceId) $selected = $service['title'];
        $msg = 'طلب خدمة: '.rawurlencode($selected).'%0Aالمرجع: '.$ref.'%0Aالاسم: '.rawurlencode($name).'%0Aالهاتف: '.rawurlencode($phone).'%0Aعدد الأشخاص: '.$people;
        $wa = 'https://wa.me/'.$config['whatsapp'].'?text='.$msg;
    } catch (Throwable) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = 'تعذر تسجيل الطلب حالياً.';
    }
}
$brandName = $config['name'];
$brandLogo = $config['logo'] ?? '';
?><!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>التأشيرات والزيارات | <?=e($brandName)?></title><link rel="stylesheet" href="/assets/css/app.css"><style>.brand-mark img{width:100%;height:100%;object-fit:contain;border-radius:inherit}</style></head>
<body><header class="site-header"><a class="brand" href="/"><span class="brand-mark"><?php if($brandLogo):?><img src="<?=e($brandLogo)?>" alt="شعار <?=e($brandName)?>"><?php else:?>م<?php endif;?></span><b><?=e($brandName)?></b></a><a href="/">الرئيسية</a></header>
<main class="section"><span class="eyebrow">خدمات السفر</span><h1>التأشيرات والزيارات</h1><div class="service-grid"><?php foreach($services as $service):?><article class="service-card"><span>✧</span><h3><?=e($service['title'])?></h3><p><?=e($service['description'])?></p><?php if($service['requirements']):?><p><b>المتطلبات:</b> <?=e($service['requirements'])?></p><?php endif;?></article><?php endforeach;?></div>
<?php if(!$services):?><div class="empty-state">لا توجد خدمات منشورة حالياً. يرجى التواصل مع الاستقبال لمعرفة المتاح.</div><?php endif;?>
<section class="panel" style="margin-top:30px"><h2>طلب خدمة</h2><?php if($error):?><p class="notice"><?=e($error)?></p><?php endif;?><?php if($success):?><p class="notice">تم تسجيل طلبك بالرقم <?=e($success)?>. <a href="<?=e($wa)?>" target="_blank" rel="noopener">فتح واتساب</a></p><?php else:?><form method="post" class="trip-search" style="grid-template-columns:repeat(2,1fr)"><input type="hidden" name="_csrf" value="<?=e(Security::csrf())?>"><label>الاسم<input name="name" required></label><label>الهاتف<input name="phone" required></label><label>الخدمة<select name="service_id" required><option value="">اختر الخدمة</option><?php foreach($services as $service):?><option value="<?=(int)$service['id']?>"><?=e($service['title'])?></option><?php endforeach;?></select></label><label>الجنسية<input name="nationality"></label><label>عدد الأشخاص<input type="number" name="people" min="1" value="1"></label><label style="grid-column:1/-1">تفاصيل الطلب<textarea name="details"></textarea></label><button class="btn btn-primary" type="submit">إرسال الطلب</button></form><?php endif;?></section></main>
<footer><span>© <?=date('Y')?> <?=e($brandName)?></span><a href="https://wa.me/<?=e($config['whatsapp'])?>">واتساب الاستقبال</a></footer></body></html>
