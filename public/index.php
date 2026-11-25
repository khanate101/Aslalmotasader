<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($path !== '/' && $path !== '/index.php') http_response_code(404);

try {
    $pdo = Database::connection();
    $cities = $pdo->query("SELECT id,name_ar,country_code FROM cities WHERE is_active=1 ORDER BY country_code,name_ar")->fetchAll();
    $tripSql = "SELECT t.*,c.name company_name,o.name_ar origin_name,d.name_ar destination_name,
        (t.seats_total-COALESCE((SELECT SUM(b.passengers_count) FROM bookings b WHERE b.trip_id=t.id AND b.status='confirmed'),0)) seats_available
        FROM trips t JOIN companies c ON c.id=t.company_id JOIN cities o ON o.id=t.origin_city_id JOIN cities d ON d.id=t.destination_city_id
        WHERE t.is_published=1 AND t.status='available' AND c.status='active' AND t.departure_at>=NOW() AND t.trip_class=? ORDER BY t.departure_at LIMIT 6";
    $q = $pdo->prepare($tripSql);
    $q->execute(['regular']);
    $trips = $q->fetchAll();
    $q->execute(['vip']);
    $vipTrips = $q->fetchAll();
    $companies = $pdo->query("SELECT id,name,description,logo_path FROM companies WHERE status='active' ORDER BY id")->fetchAll();
} catch (Throwable $e) {
    $cities = $trips = $vipTrips = $companies = [];
    $dbError = $config['debug'] ? $e->getMessage() : 'الخدمة غير متاحة مؤقتاً. يرجى المحاولة لاحقاً.';
}
$csrf = Security::csrf();
$brandName = $config['name'];
$brandLogo = $config['logo'] ?? '';
$brandImage = $config['brand_image'] ?? '';

function render_trip_cards(array $trips): void {
    if (!$trips) { echo '<div class="empty-state">لا توجد رحلات منشورة في هذا القسم حالياً.</div>'; return; }
    echo '<div class="trip-grid">';
    foreach ($trips as $t) {
        echo '<article class="trip-card">';
        if (!empty($t['image_path'])) echo '<img class="trip-card-image" src="'.e($t['image_path']).'" alt="صورة الحافلة" loading="lazy">';
        echo '<div class="trip-card-top"><span class="trip-company">'.e($t['company_name']).'</span>';
        if (($t['trip_class'] ?? 'regular') === 'vip') echo '<span class="vip-badge">VIP</span>';
        echo '<span class="availability">'.max(0,(int)$t['seats_available']).' مقعد متاح</span></div>';
        echo '<div class="route"><div><small>من</small><b>'.e($t['origin_name']).'</b></div><span class="route-arrow">←</span><div><small>إلى</small><b>'.e($t['destination_name']).'</b></div></div>';
        echo '<div class="trip-meta"><span>◷ '.e(date('d/m/Y H:i', strtotime($t['departure_at']))).'</span><strong>'.e(number_format((float)$t['price'],2)).' '.e($t['currency']).'</strong></div>';
        echo '<a class="btn btn-primary full" href="/trip.php?id='.(int)$t['id'].'">تفاصيل الرحلة والحجز</a></article>';
    }
    echo '</div>';
}
?><!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="<?=e($brandName)?> للسفر والنقل والخدمات السياحية والرحلات البرية والعمرة والتأشيرات.">
<title><?=e($brandName)?> | للسفر والنقل</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
<style>.trip-card-image{width:100%;height:190px;object-fit:cover;border-radius:9px;margin-bottom:14px}.brand-mark img{width:100%;height:100%;object-fit:contain;border-radius:inherit}.hero-art.has-image{background-size:cover;background-position:center}.section-heading .vip-badge{margin-inline-start:10px}</style>
</head>
<body>
<header class="site-header">
<a class="brand" href="/"><span class="brand-mark"><?php if($brandLogo):?><img src="<?=e($brandLogo)?>" alt="شعار <?=e($brandName)?>"><?php else:?>م<?php endif;?></span><span><b><?=e($brandName)?></b><small>للسفر والنقل والخدمات السياحية</small></span></a>
<button class="menu-toggle" aria-label="فتح القائمة" aria-expanded="false">☰</button>
<nav class="main-nav"><a href="/">الرئيسية</a><a href="#trips">الرحلات العادية</a><a href="#vip-trips">رحلات VIP</a><a href="#umrah">العمرة</a><a href="#services">التأشيرات والزيارات</a><a href="#companies">شركات النقل</a><a href="/track.php">تتبع طلبك</a><a href="#contact">تواصل معنا</a></nav>
<a class="btn btn-outline header-login" href="/admin/login.php">دخول الموظفين</a>
</header>
<main>
<section class="hero"><div class="hero-content"><span class="eyebrow">رحلتك تبدأ من هنا</span><h1>نسهّل طريقك،<br><em>ونقرّب وجهتك.</em></h1><p>منصة واحدة للرحلات البرية وخدمات العمرة والتأشيرات بين اليمن والمملكة العربية السعودية.</p><a class="btn btn-gold" href="#search">ابحث عن رحلتك <span>←</span></a></div>
<div class="hero-art <?= $brandImage ? 'has-image' : '' ?>" aria-label="صورة الهوية البصرية" style="<?=$brandImage?'background-image:linear-gradient(90deg,#10263f55,#10263f22),url('.e($brandImage).')':''?>"><div class="route-line"></div><div class="hero-bubble">وجهتك أقرب مما تتوقع</div></div></section>
<section class="search-wrap" id="search"><div class="search-heading"><div><span class="eyebrow">خطوتك الأولى</span><h2>ابحث عن رحلة</h2></div><span class="muted">اختر وجهتك وموعد سفرك</span></div>
<form class="trip-search" action="/trips.php" method="get"><label>من مدينة<select name="from" required><option value="">مدينة الانطلاق</option><?php foreach($cities as $c):?><option value="<?=(int)$c['id']?>"><?=e($c['name_ar'])?></option><?php endforeach;?></select></label><label>إلى مدينة<select name="to" required><option value="">مدينة الوصول</option><?php foreach($cities as $c):?><option value="<?=(int)$c['id']?>"><?=e($c['name_ar'])?></option><?php endforeach;?></select></label><label>تاريخ الرحلة<input type="date" name="date" min="<?=date('Y-m-d')?>" required></label><label>المسافرون<input type="number" name="people" min="1" max="20" value="1" required></label><button class="btn btn-primary" type="submit">ابحث الآن</button></form></section>
<?php if(isset($dbError)):?><div class="notice"><?=e($dbError)?></div><?php endif;?>
<section class="section" id="trips"><div class="section-heading"><div><span class="eyebrow">رحلات مختارة</span><h2>الرحلات العادية</h2></div><a class="text-link" href="/trips.php?class=regular">عرض جميع الرحلات ←</a></div><?php render_trip_cards($trips);?></section>
<section class="section soft" id="vip-trips"><div class="section-heading"><div><span class="eyebrow">تجربة سفر مميزة</span><h2>رحلات VIP</h2></div><a class="text-link" href="/trips.php?class=vip">عرض جميع رحلات VIP ←</a></div><?php render_trip_cards($vipTrips);?></section>
<section class="section" id="companies"><div class="section-heading"><div><span class="eyebrow">شركاؤنا</span><h2>شركات النقل</h2></div></div><div class="company-grid"><?php foreach($companies as $c):?><div class="company-card"><?php if(!empty($c['logo_path'])):?><img class="company-logo" src="<?=e($c['logo_path'])?>" alt="شعار <?=e($c['name'])?>"><?php else:?><span class="company-symbol">✦</span><?php endif;?><b><?=e($c['name'])?></b></div><?php endforeach;?></div></section>
<section class="section" id="services"><div class="section-heading"><div><span class="eyebrow">خدماتنا</span><h2>كل ما تحتاجه في طريقك</h2></div></div><div class="service-grid"><a class="service-card" href="/trips.php"><span>↗</span><h3>الرحلات البرية</h3><p>استعرض الرحلات ومواعيدها وأسعارها.</p></a><a class="service-card" href="/umrah.php" id="umrah"><span>☾</span><h3>برامج العمرة</h3><p>تعرّف على البرامج واطلب عرضاً يناسبك.</p></a><a class="service-card" href="/services.php"><span>✧</span><h3>التأشيرات والزيارات</h3><p>اطلب الخدمة وتابع حالة طلبك.</p></a></div></section>
<section class="cta" id="contact"><div><span class="eyebrow">نحن بالقرب منك</span><h2>هل تحتاج مساعدة في اختيار رحلتك؟</h2><p>فريق الاستقبال جاهز للإجابة عن استفساراتك.</p></div><a class="btn btn-gold" target="_blank" rel="noopener" href="https://wa.me/<?=e($config['whatsapp'])?>">تواصل عبر واتساب</a></section>
</main>
<footer><a class="brand footer-brand" href="/"><span class="brand-mark"><?php if($brandLogo):?><img src="<?=e($brandLogo)?>" alt=""><?php else:?>م<?php endif;?></span><span><b><?=e($brandName)?></b><small>للسفر والنقل والخدمات السياحية</small></span></a><p>© <?=date('Y')?> <?=e($brandName)?>. جميع الحقوق محفوظة.</p><a href="https://wa.me/<?=e($config['whatsapp'])?>" target="_blank" rel="noopener">واتساب الاستقبال</a></footer>
<script src="/assets/js/app.js" defer></script>
</body></html>
