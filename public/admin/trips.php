<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require BASE_PATH . '/app/AdminUi.php';

$user = Auth::requireLogin();
Auth::requirePermission('trips.manage');
$pdo = Database::connection();
$error = '';
$isCompany = ($user['role'] ?? '') === 'company_manager';
$ownCompany = (int)($user['company_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::verifyCsrf();
    try {
        $id = (int)($_POST['id'] ?? 0);
        if (isset($_POST['archive_trip'])) {
            $check = $pdo->prepare('SELECT company_id FROM trips WHERE id=?');
            $check->execute([$id]);
            $trip = $check->fetch();
            if (!$trip || ($isCompany && (int)$trip['company_id'] !== $ownCompany)) throw new RuntimeException('لا تملك صلاحية أرشفة هذه الرحلة.');
            $pdo->prepare("UPDATE trips SET status='cancelled',is_published=0 WHERE id=?")->execute([$id]);
            admin_log($pdo, $user, 'trip.archived', 'trip', $id, 'إلغاء نشر الرحلة وأرشفتها مع الاحتفاظ بالحجوزات');
            redirect('/admin/trips.php?archived=1');
        }

        $company = $isCompany ? $ownCompany : (int)($_POST['company_id'] ?? 0);
        $from = (int)($_POST['origin_city_id'] ?? 0);
        $to = (int)($_POST['destination_city_id'] ?? 0);
        $departure = str_replace('T', ' ', trim($_POST['departure_at'] ?? ''));
        $arrivalRaw = trim($_POST['arrival_at'] ?? '');
        $arrival = $arrivalRaw !== '' ? str_replace('T', ' ', $arrivalRaw) : null;
        $price = (float)($_POST['price'] ?? -1);
        $seats = (int)($_POST['seats_total'] ?? 0);
        $status = in_array($_POST['status'] ?? 'available', ['available','full','paused','cancelled','completed'], true) ? $_POST['status'] : 'available';
        $published = isset($_POST['is_published']) ? 1 : 0;
        $tripClass = in_array($_POST['trip_class'] ?? 'regular', ['regular','vip'], true) ? $_POST['trip_class'] : 'regular';
        $currency = in_array($_POST['currency'] ?? 'SAR', ['SAR','YER'], true) ? $_POST['currency'] : 'SAR';
        if (!$company || !$from || !$to || $from === $to || $departure === '' || $price < 0 || $seats < 1) throw new RuntimeException('أكمل بيانات الرحلة بشكل صحيح، واختر مدينتين مختلفتين.');

        $image = upload_image_file('trip_image', 'trip');
        if ($id) {
            $check = $pdo->prepare('SELECT company_id,image_path FROM trips WHERE id=?');
            $check->execute([$id]);
            $old = $check->fetch();
            if (!$old || ($isCompany && (int)$old['company_id'] !== $ownCompany)) throw new RuntimeException('لا تملك صلاحية تعديل هذه الرحلة.');
            $image = $image ?? $old['image_path'];
            $confirmed = $pdo->prepare("SELECT COALESCE(SUM(passengers_count),0) FROM bookings WHERE trip_id=? AND status='confirmed'");
            $confirmed->execute([$id]);
            if ($seats < (int)$confirmed->fetchColumn()) throw new RuntimeException('لا يمكن تقليل المقاعد عن عدد المقاعد المحجوزة والمؤكدة.');
            $sql = 'UPDATE trips SET company_id=?,origin_city_id=?,destination_city_id=?,departure_at=?,arrival_at=?,vehicle_type=?,vehicle_description=?,image_path=?,trip_class=?,price=?,currency=?,seats_total=?,status=?,is_published=?,details=? WHERE id=?';
            $pdo->prepare($sql)->execute([$company,$from,$to,$departure,$arrival,trim($_POST['vehicle_type']??''),trim($_POST['vehicle_description']??''),$image,$tripClass,$price,$currency,$seats,$status,$published,trim($_POST['details']??''),$id]);
            admin_log($pdo, $user, 'trip.updated', 'trip', $id, 'تعديل بيانات الرحلة');
        } else {
            $sql = 'INSERT INTO trips(company_id,origin_city_id,destination_city_id,departure_at,arrival_at,vehicle_type,vehicle_description,image_path,trip_class,price,currency,seats_total,status,is_published,details,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
            $pdo->prepare($sql)->execute([$company,$from,$to,$departure,$arrival,trim($_POST['vehicle_type']??''),trim($_POST['vehicle_description']??''),$image,$tripClass,$price,$currency,$seats,$status,$published,trim($_POST['details']??''),(int)$user['id']]);
            $id = (int)$pdo->lastInsertId();
            admin_log($pdo, $user, 'trip.created', 'trip', $id, 'إضافة رحلة جديدة');
        }
        redirect('/admin/trips.php?saved=1');
    } catch (Throwable $e) {
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'تعذر حفظ الرحلة. راجع الشركة والمدن والصورة والبيانات.';
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $q = $pdo->prepare('SELECT * FROM trips WHERE id=?'.($isCompany ? ' AND company_id=?' : ''));
    $q->execute($isCompany ? [(int)$_GET['edit'],$ownCompany] : [(int)$_GET['edit']]);
    $edit = $q->fetch() ?: null;
}
$cities = $pdo->query('SELECT id,name_ar FROM cities WHERE is_active=1 ORDER BY name_ar')->fetchAll();
if ($isCompany) {
    $q = $pdo->prepare("SELECT id,name FROM companies WHERE id=? AND status='active'");
    $q->execute([$ownCompany]);
    $companies = $q->fetchAll();
    $q = $pdo->prepare('SELECT t.*,c.name company_name,o.name_ar origin_name,d.name_ar destination_name FROM trips t JOIN companies c ON c.id=t.company_id JOIN cities o ON o.id=t.origin_city_id JOIN cities d ON d.id=t.destination_city_id WHERE t.company_id=? ORDER BY t.departure_at DESC LIMIT 300');
    $q->execute([$ownCompany]);
    $rows = $q->fetchAll();
} else {
    $companies = $pdo->query("SELECT id,name FROM companies WHERE status='active' ORDER BY name")->fetchAll();
    $rows = $pdo->query('SELECT t.*,c.name company_name,o.name_ar origin_name,d.name_ar destination_name FROM trips t JOIN companies c ON c.id=t.company_id JOIN cities o ON o.id=t.origin_city_id JOIN cities d ON d.id=t.destination_city_id ORDER BY t.departure_at DESC LIMIT 300')->fetchAll();
}

admin_page_start('إدارة الرحلات', $user);
if ($error) echo '<p class="notice danger">'.e($error).'</p>';
if (isset($_GET['saved'])) echo '<p class="notice">تم حفظ الرحلة. إذا كانت منشورة وحالتها متاحة وموعدها مستقبلي فستظهر في الموقع.</p>';
if (isset($_GET['archived'])) echo '<p class="notice">تم إيقاف نشر الرحلة وأرشفتها مع الاحتفاظ بالحجوزات.</p>';
$v = fn($key,$default='') => e($edit[$key] ?? $default);
echo '<section class="panel"><h2>'.($edit ? 'تعديل الرحلة' : 'إضافة رحلة').'</h2><form method="post" enctype="multipart/form-data" class="form-grid"><input type="hidden" name="_csrf" value="'.e(Security::csrf()).'"><input type="hidden" name="id" value="'.(int)($edit['id']??0).'">';
if (!$isCompany) {
    echo '<label>شركة النقل<select name="company_id" required><option value="">اختر الشركة</option>';
    foreach ($companies as $c) echo '<option value="'.(int)$c['id'].'" '.((int)($edit['company_id']??0)===(int)$c['id']?'selected':'').'>'.e($c['name']).'</option>';
    echo '</select></label>';
}
foreach ([['origin_city_id','مدينة الانطلاق'],['destination_city_id','مدينة الوصول']] as [$key,$label]) {
    echo '<label>'.e($label).'<select name="'.$key.'" required><option value="">اختر المدينة</option>';
    foreach ($cities as $city) echo '<option value="'.(int)$city['id'].'" '.((int)($edit[$key]??0)===(int)$city['id']?'selected':'').'>'.e($city['name_ar']).'</option>';
    echo '</select></label>';
}
$departureValue = $edit ? date('Y-m-d\TH:i', strtotime($edit['departure_at'])) : '';
$arrivalValue = $edit && !empty($edit['arrival_at']) ? date('Y-m-d\TH:i', strtotime($edit['arrival_at'])) : '';
echo '<label>تاريخ ووقت الذهاب<input type="datetime-local" name="departure_at" required value="'.e($departureValue).'"></label>';
echo '<label>تاريخ ووقت الوصول<input type="datetime-local" name="arrival_at" value="'.e($arrivalValue).'"></label>';
echo '<label>نوع الحافلة<input name="vehicle_type" value="'.$v('vehicle_type').'"></label>';
echo '<label>تصنيف الرحلة<select name="trip_class"><option value="regular" '.(($edit['trip_class']??'regular')==='regular'?'selected':'').'>عادية</option><option value="vip" '.(($edit['trip_class']??'regular')==='vip'?'selected':'').'>VIP</option></select></label>';
echo '<label class="wide">صورة الحافلة / بطاقة الرحلة<input type="file" name="trip_image" accept="image/png,image/jpeg,image/webp"></label>';
if (!empty($edit['image_path'])) echo '<div class="wide"><b>الصورة الحالية</b><br><img src="'.e($edit['image_path']).'" alt="صورة الرحلة" style="width:min(100%,360px);max-height:190px;object-fit:cover;border-radius:10px"></div>';
echo '<label class="wide">وصف الحافلة<textarea name="vehicle_description">'.$v('vehicle_description').'</textarea></label>';
echo '<label>سعر التذكرة<input type="number" min="0" step=".01" name="price" required value="'.$v('price','0').'"></label>';
echo '<label>العملة<select name="currency"><option value="SAR" '.(($edit['currency']??'SAR')==='SAR'?'selected':'').'>ريال سعودي</option><option value="YER" '.(($edit['currency']??'')==='YER'?'selected':'').'>ريال يمني</option></select></label>';
echo '<label>عدد المقاعد<input type="number" min="1" name="seats_total" required value="'.$v('seats_total','1').'"></label>';
echo '<label>حالة الرحلة<select name="status">';
foreach (['available'=>'متاحة','full'=>'مكتملة','paused'=>'متوقفة','cancelled'=>'ملغاة','completed'=>'منتهية'] as $key=>$label) echo '<option value="'.$key.'" '.(($edit['status']??'available')===$key?'selected':'').'>'.$label.'</option>';
echo '</select></label><label><input type="checkbox" name="is_published" value="1" '.((int)($edit['is_published']??1)===1?'checked':'').'> نشر الرحلة للعامة</label>';
echo '<label class="wide">تفاصيل إضافية<textarea name="details">'.$v('details').'</textarea></label><div class="wide"><button class="btn btn-primary" type="submit">حفظ الرحلة</button> '.($edit?'<a class="admin-link" href="/admin/trips.php">إلغاء التعديل</a>':'').'</div></form></section>';
echo '<section class="panel"><h2>الرحلات المسجلة</h2><table><thead><tr><th>الشركة</th><th>المسار</th><th>الموعد</th><th>السعر</th><th>المقاعد</th><th>التصنيف</th><th>النشر</th><th>الحالة</th><th>الإجراءات</th></tr></thead><tbody>';
foreach ($rows as $row) {
    echo '<tr><td>'.e($row['company_name']).'</td><td>'.e($row['origin_name'].' ← '.$row['destination_name']).'</td><td>'.e($row['departure_at']).'</td><td>'.e($row['price'].' '.$row['currency']).'</td><td>'.(int)$row['seats_total'].'</td><td>'.(($row['trip_class']??'regular')==='vip'?'VIP':'عادية').'</td><td>'.((int)$row['is_published']===1?'منشورة':'مسودة').'</td><td>'.e($row['status']).'</td><td><a class="admin-link" href="/admin/trips.php?edit='.(int)$row['id'].'">تعديل</a> ';
    if ($row['status'] !== 'cancelled') echo '<form method="post" class="inline" onsubmit="return confirm(\'سيتم إلغاء نشر الرحلة مع الاحتفاظ بالحجوزات. متابعة؟\')"><input type="hidden" name="_csrf" value="'.e(Security::csrf()).'"><input type="hidden" name="id" value="'.(int)$row['id'].'"><button class="btn-sm" name="archive_trip" value="1" type="submit">أرشفة</button></form>';
    echo '</td></tr>';
}
if (!$rows) echo '<tr><td colspan="9">لا توجد رحلات.</td></tr>';
echo '</tbody></table></section>';
admin_page_end();
