<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require BASE_PATH . '/app/AdminUi.php';

$user = Auth::requireLogin();
Auth::requirePermission('companies.manage');
$pdo = Database::connection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::verifyCsrf();
    try {
        if (isset($_POST['archive_company'])) {
            $id = (int)($_POST['id'] ?? 0);
            $q = $pdo->prepare("UPDATE companies SET status='hidden' WHERE id=?");
            $q->execute([$id]);
            if ($q->rowCount() < 1) throw new RuntimeException('الشركة غير موجودة أو مخفية بالفعل.');
            admin_log($pdo, $user, 'company.archived', 'company', $id, 'إخفاء الشركة مع الاحتفاظ بسجلاتها ورحلاتها');
            redirect('/admin/companies.php?archived=1');
        }

        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slug = strtolower(trim($_POST['slug'] ?? ''));
        $type = in_array($_POST['company_type'] ?? 'transport', ['transport','agent'], true) ? $_POST['company_type'] : 'transport';
        $status = in_array($_POST['status'] ?? 'active', ['active','hidden','suspended'], true) ? $_POST['status'] : 'active';
        if (mb_strlen($name) < 2 || !preg_match('/^[a-z0-9-]{2,190}$/', $slug)) throw new RuntimeException('أدخل اسم الشركة ومعرّفاً لاتينياً صالحاً.');

        $oldLogo = $oldCover = null;
        if ($id) {
            $old = $pdo->prepare('SELECT logo_path, cover_path FROM companies WHERE id=?');
            $old->execute([$id]);
            $oldRow = $old->fetch();
            if (!$oldRow) throw new RuntimeException('الشركة غير موجودة.');
            $oldLogo = $oldRow['logo_path'];
            $oldCover = $oldRow['cover_path'];
        }
        $logo = upload_image_file('logo', 'company-logo');
        $cover = upload_image_file('cover', 'company-cover');
        $logo = isset($_POST['remove_logo']) ? null : ($logo ?? $oldLogo);
        $cover = isset($_POST['remove_cover']) ? null : ($cover ?? $oldCover);

        $data = [$name, $slug, $type, $logo, $cover, trim($_POST['description'] ?? ''), trim($_POST['phone'] ?? ''), trim($_POST['whatsapp'] ?? ''), $status];
        if ($id) {
            $data[] = $id;
            $pdo->prepare('UPDATE companies SET name=?,slug=?,company_type=?,logo_path=?,cover_path=?,description=?,phone=?,whatsapp=?,status=? WHERE id=?')->execute($data);
            admin_log($pdo, $user, 'company.updated', 'company', $id, 'تعديل بيانات الشركة');
        } else {
            $pdo->prepare('INSERT INTO companies(name,slug,company_type,logo_path,cover_path,description,phone,whatsapp,status) VALUES(?,?,?,?,?,?,?,?,?)')->execute($data);
            $id = (int)$pdo->lastInsertId();
            admin_log($pdo, $user, 'company.created', 'company', $id, 'إضافة شركة نقل');
        }
        redirect('/admin/companies.php?saved=1');
    } catch (Throwable $e) {
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'تعذر حفظ الشركة. تحقق من عدم تكرار المعرّف وصحة الصور.';
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $q = $pdo->prepare('SELECT * FROM companies WHERE id=?');
    $q->execute([(int)$_GET['edit']]);
    $edit = $q->fetch() ?: null;
}
$rows = $pdo->query('SELECT id,name,slug,company_type,logo_path,status,phone,whatsapp FROM companies ORDER BY id DESC')->fetchAll();
admin_page_start('إدارة شركات النقل والوكلاء', $user);
if ($error) echo '<p class="notice danger">'.e($error).'</p>';
if (isset($_GET['saved'])) echo '<p class="notice">تم حفظ الشركة وشعارها وبياناتها.</p>';
if (isset($_GET['archived'])) echo '<p class="notice">تم إخفاء الشركة مع الاحتفاظ بسجلاتها.</p>';
$v = fn($key) => e($edit[$key] ?? '');
echo '<section class="panel"><h2>'.($edit ? 'تعديل الشركة' : 'إضافة شركة نقل أو وكيل').'</h2>';
echo '<form method="post" enctype="multipart/form-data" class="form-grid"><input type="hidden" name="_csrf" value="'.e(Security::csrf()).'"><input type="hidden" name="id" value="'.(int)($edit['id'] ?? 0).'">';
echo '<label>اسم الشركة<input name="name" required value="'.$v('name').'"></label><label>المعرّف اللاتيني<input name="slug" pattern="[a-z0-9-]{2,190}" required value="'.$v('slug').'"></label>';
echo '<label>النوع<select name="company_type"><option value="transport" '.(($edit['company_type']??'transport')==='transport'?'selected':'').'>شركة نقل</option><option value="agent" '.(($edit['company_type']??'')==='agent'?'selected':'').'>وكيل</option></select></label>';
echo '<label>شعار الشركة<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label><label>غلاف الشركة<input type="file" name="cover" accept="image/png,image/jpeg,image/webp"></label>';
echo '<label>الهاتف<input name="phone" value="'.$v('phone').'"></label><label>واتساب<input name="whatsapp" value="'.$v('whatsapp').'"></label><label>الحالة<select name="status">';
foreach (['active'=>'نشطة','hidden'=>'مخفية','suspended'=>'موقوفة'] as $key=>$label) echo '<option value="'.$key.'" '.(($edit['status']??'active')===$key?'selected':'').'>'.$label.'</option>';
echo '</select></label><label class="wide">الوصف<textarea name="description">'.$v('description').'</textarea></label>';
if (!empty($edit['logo_path'])) echo '<div class="wide"><b>الشعار الحالي</b><br><img src="'.e($edit['logo_path']).'" alt="شعار الشركة" style="max-width:140px;max-height:90px;object-fit:contain"><label><input type="checkbox" name="remove_logo" value="1"> إزالة الشعار الحالي</label></div>';
if (!empty($edit['cover_path'])) echo '<div class="wide"><b>الغلاف الحالي</b><br><img src="'.e($edit['cover_path']).'" alt="غلاف الشركة" style="max-width:300px;max-height:130px;object-fit:cover"><label><input type="checkbox" name="remove_cover" value="1"> إزالة الغلاف الحالي</label></div>';
echo '<div class="wide"><button class="btn btn-primary" type="submit">حفظ الشركة</button> '.($edit?'<a class="admin-link" href="/admin/companies.php">إلغاء</a>':'').'</div></form></section>';
echo '<section class="panel"><h2>الشركات المسجلة</h2><table><thead><tr><th>الشركة</th><th>المعرّف</th><th>النوع</th><th>الهاتف</th><th>الحالة</th><th>الإجراءات</th></tr></thead><tbody>';
foreach ($rows as $row) {
    echo '<tr><td>'.(!empty($row['logo_path'])?'<img src="'.e($row['logo_path']).'" alt="" style="width:38px;height:38px;object-fit:contain;vertical-align:middle;margin-left:8px">':'').e($row['name']).'</td><td>'.e($row['slug']).'</td><td>'.($row['company_type']==='transport'?'نقل':'وكيل').'</td><td>'.e($row['phone']).'</td><td>'.e($row['status']).'</td><td><a class="admin-link" href="/admin/companies.php?edit='.(int)$row['id'].'">تعديل</a> ';
    if ($row['status'] !== 'hidden') echo '<form method="post" class="inline" onsubmit="return confirm(\'سيتم إخفاء الشركة مع الاحتفاظ بالرحلات والحجوزات. متابعة؟\')"><input type="hidden" name="_csrf" value="'.e(Security::csrf()).'"><input type="hidden" name="id" value="'.(int)$row['id'].'"><button class="btn-sm" name="archive_company" value="1" type="submit">إخفاء / أرشفة</button></form>';
    echo '</td></tr>';
}
if (!$rows) echo '<tr><td colspan="6">لا توجد شركات مسجلة.</td></tr>';
echo '</tbody></table></section>';
admin_page_end();
