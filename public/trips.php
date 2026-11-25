<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$from = filter_input(INPUT_GET, 'from', FILTER_VALIDATE_INT) ?: null;
$to = filter_input(INPUT_GET, 'to', FILTER_VALIDATE_INT) ?: null;
$date = $_GET['date'] ?? '';
$people = max(1, min(20, (int)($_GET['people'] ?? 1)));
$class = in_array($_GET['class'] ?? '', ['regular','vip'], true) ? $_GET['class'] : '';
$sql = "SELECT t.id,t.departure_at,t.arrival_at,t.price,t.currency,t.seats_total,t.vehicle_type,t.image_path,t.trip_class,t.vehicle_description,
c.name company_name,o.name_ar origin_name,d.name_ar destination_name,
(t.seats_total-COALESCE((SELECT SUM(b.passengers_count) FROM bookings b WHERE b.trip_id=t.id AND b.status='confirmed'),0)) seats_available
FROM trips t JOIN companies c ON c.id=t.company_id JOIN cities o ON o.id=t.origin_city_id JOIN cities d ON d.id=t.destination_city_id
WHERE t.is_published=1 AND t.status='available' AND c.status='active' AND t.departure_at>=NOW()";
$args = [];
if ($from) { $sql .= ' AND t.origin_city_id=?'; $args[] = $from; }
if ($to) { $sql .= ' AND t.destination_city_id=?'; $args[] = $to; }
if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $sql .= ' AND DATE(t.departure_at)=?'; $args[] = $date; }
if ($class !== '') { $sql .= ' AND t.trip_class=?'; $args[] = $class; }
$sql .= " HAVING seats_available >= ? ORDER BY t.departure_at ASC LIMIT 100";
$args[] = $people;
$q = Database::connection()->prepare($sql);
$q->execute($args);
$trips = $q->fetchAll();
$brandName = $config['name'];
$brandLogo = $config['logo'] ?? '';
?><!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($class==='vip'?'رحلات VIP':($class==='regular'?'الرحلات العادية':'كل الرحلات'))?> | <?=e($brandName)?></title>
<link rel="stylesheet" href="/assets/css/app.css">
<style>.trip-card-image{width:100%;height:190px;object-fit:cover;border-radius:9px;margin-bottom:14px}.brand-mark img{width:100%;height:100%;object-fit:contain;border-radius:inherit}</style>
</head><body>
<header class="site-header"><a class="brand" href="/"><span class="brand-mark"><?php if($brandLogo):?><img src="<?=e($brandLogo)?>" alt="شعار <?=e($brandName)?>"><?php else:?>م<?php endif;?></span><span><b><?=e($brandName)?></b><small>للسفر والنقل والخدمات السياحية</small></span></a><a class="btn btn-outline" href="/">الرئيسية</a></header>
<main class="section">
<div class="section-heading"><div><span class="eyebrow">اختر رحلتك</span><h2><?=e($class==='vip'?'رحلات VIP':($class==='regular'?'الرحلات العادية':'الرحلات المتاحة'))?></h2></div><span class="muted"><?=count($trips)?> رحلة متاحة</span></div>
<nav class="admin-nav" aria-label="تصنيف الرحلات"><a href="/trips.php">كل الرحلات</a><a href="/trips.php?class=regular">الرحلات العادية</a><a href="/trips.php?class=vip">رحلات VIP</a></nav>
<?php if(!$trips):?><div class="empty-state">لا توجد رحلات مطابقة حالياً. جرّب تغيير الوجهة أو التاريخ أو عدد المسافرين.</div><?php else:?>
<div class="trip-grid"><?php foreach($trips as $t):?><article class="trip-card">
<?php if(!empty($t['image_path'])):?><img class="trip-card-image" src="<?=e($t['image_path'])?>" alt="صورة حافلة <?=e($t['company_name'])?>" loading="lazy"><?php endif;?>
<div class="trip-card-top"><span class="trip-company"><?=e($t['company_name'])?></span><?php if(($t['trip_class']??'regular')==='vip'):?><span class="vip-badge">VIP</span><?php else:?><span class="regular-badge">عادية</span><?php endif;?></div>
<div class="route"><div><small>من</small><b><?=e($t['origin_name'])?></b></div><span class="route-arrow">←</span><div><small>إلى</small><b><?=e($t['destination_name'])?></b></div></div>
<div class="trip-meta"><span>الذهاب: <?=e(date('d/m/Y H:i',strtotime($t['departure_at'])))?></span><strong><?=e(number_format((float)$t['price'],2))?> <?=e($t['currency'])?></strong></div>
<div class="trip-meta"><span>المقاعد المتاحة</span><strong><?=max(0,(int)$t['seats_available'])?></strong></div>
<a class="btn btn-primary full" href="/trip.php?id=<?=(int)$t['id']?>">تفاصيل الرحلة والحجز</a>
</article><?php endforeach;?></div><?php endif;?>
</main><footer><a class="brand footer-brand" href="/"><b><?=e($brandName)?></b></a><span>© <?=date('Y')?> <?=e($brandName)?></span><a href="https://wa.me/<?=e($config['whatsapp'])?>">واتساب الاستقبال</a></footer>
</body></html>
