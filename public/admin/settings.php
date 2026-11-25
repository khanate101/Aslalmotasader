<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require BASE_PATH . '/app/AdminUi.php';

$user = Auth::requireLogin();
Auth::requirePermission('settings.manage');
$pdo = Database::connection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::verifyCsrf();
    try {
        $name = trim($_POST['platform_name'] ?? '');
        if (mb_strlen($name) < 2) {
            throw new RuntimeException('اسم المنصة يجب أن يتكون من حرفين على الأقل.');
        }

        $values = [
            'platform_name' => $name,
            'whatsapp_number' => preg_replace('/\D+/', '', $_POST['whatsapp_number'] ?? ''),
            'default_currency' => in_array($_POST['default_currency'] ?? 'SAR', ['SAR', 'YER'], true) ? $_POST['default_currency'] : 'SAR',
            'support_email' => trim($_POST['support_email'] ?? ''),
        ];

        $logo = upload_image_file('site_logo', 'site-logo');
        $brand = upload_image_file('site_brand_image', 'site-brand');
        if ($logo !== null) $values['site_logo_path'] = $logo;
        elseif (isset($_POST['remove_site_logo'])) $values['site_logo_path'] = '';
        if ($brand !== null) $values['site_brand_image_path'] = $brand;
        elseif (isset($_POST['remove_site_brand'])) $values['site_brand_image_path'] = '';

        $pdo->beginTransaction();
        $save = $pdo->prepare('INSERT INTO settings(setting_key, setting_value) VALUES(?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
        foreach ($values as $key => $value) $save->execute([$key, $value]);
        admin_log($pdo, $user, 'settings.updated', 'settings', 0, 'تحديث هوية وإعدادات المنصة');
        $pdo->commit();
        redirect('/admin/settings.php?saved=1');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'تعذر حفظ الإعدادات. تحقق من الصور والاتصال بقاعدة البيانات.';
    }
}

$vals = [];
foreach (['platform_name'=>'أصل المتصدر','whatsapp_number'=>'967733148606','default_currency'=>'SAR','support_email'=>'','site_logo_path'=>'','site_brand_image_path'=>''] as $key=>$default) {
    $vals[$key] = setting($key, $default);
}
admin_page_start('هوية المنصة وإعداداتها', $user);
if ($error) echo '<p class="notice danger">'.e($error).'</p>';
if (isset($_GET['saved'])) echo '<p class="notice">تم حفظ إعدادات الهوية بنجاح.</p>';

echo '<section class="panel"><p class="muted">تظهر الهوية في رأس الموقع وصفحاته العامة. الصور المدعومة JPG وPNG وWebP، بحد أقصى 5 ميجابايت لكل صورة.</p>';
echo '<form method="post" enctype="multipart/form-data" class="form-grid">';
echo '<input type="hidden" name="_csrf" value="'.e(Security::csrf()).'">';
echo '<label>اسم المنصة<input name="platform_name" maxlength="180" value="'.e($vals['platform_name']).'" required></label>';
echo '<label>رقم واتساب الاستقبال<input name="whatsapp_number" inputmode="tel" value="'.e($vals['whatsapp_number']).'"></label>';
echo '<label>بريد الدعم<input type="email" name="support_email" value="'.e($vals['support_email']).'"></label>';
echo '<label>العملة الافتراضية<select name="default_currency"><option value="SAR" '.($vals['default_currency']==='SAR'?'selected':'').'>ريال سعودي</option><option value="YER" '.($vals['default_currency']==='YER'?'selected':'').'>ريال يمني</option></select></label>';
echo '<label class="wide">شعار المنصة<input type="file" name="site_logo" accept="image/png,image/jpeg,image/webp"></label>';
if ($vals['site_logo_path'] !== '') echo '<div class="wide"><b>الشعار الحالي</b><br><img src="'.e($vals['site_logo_path']).'" alt="الشعار الحالي" style="max-width:170px;max-height:100px;object-fit:contain"><label><input type="checkbox" name="remove_site_logo" value="1"> إزالة الشعار الحالي</label></div>';
echo '<label class="wide">صورة الغلاف / الهوية العلوية للصفحة الرئيسية<input type="file" name="site_brand_image" accept="image/png,image/jpeg,image/webp"></label>';
if ($vals['site_brand_image_path'] !== '') echo '<div class="wide"><b>صورة الغلاف الحالية</b><br><img src="'.e($vals['site_brand_image_path']).'" alt="صورة الغلاف الحالية" style="width:min(100%,420px);max-height:180px;object-fit:cover;border-radius:10px"><label><input type="checkbox" name="remove_site_brand" value="1"> إزالة صورة الغلاف الحالية</label></div>';
echo '<div class="wide"><button class="btn btn-primary" type="submit">حفظ الهوية والإعدادات</button></div></form></section>';
admin_page_end();
