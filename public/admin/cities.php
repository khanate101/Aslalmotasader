<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require BASE_PATH.'/app/AdminUi.php';
$user=Auth::requireLogin(); Auth::requirePermission('companies.manage');
$pdo=Database::connection(); $error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 Security::verifyCsrf();
 try {
  $id=(int)($_POST['id']??0); $name=trim($_POST['name_ar']??'');
  $country=in_array($_POST['country_code']??'YE',['YE','SA'],true)?$_POST['country_code']:'YE';
  $english=trim($_POST['name_en']??'')?:null; $active=isset($_POST['is_active'])?1:0;
  if(mb_strlen($name)<2) throw new RuntimeException('اسم المدينة مطلوب.');
  if($id){$pdo->prepare('UPDATE cities SET country_code=?,name_ar=?,name_en=?,is_active=? WHERE id=?')->execute([$country,$name,$english,$active,$id]);admin_log($pdo,$user,'city.updated','city',$id,'تعديل مدينة');}
  else{$pdo->prepare('INSERT INTO cities(country_code,name_ar,name_en,is_active) VALUES(?,?,?,?)')->execute([$country,$name,$english,$active]);admin_log($pdo,$user,'city.created','city',(int)$pdo->lastInsertId(),'إضافة مدينة');}
  redirect('/admin/cities.php?saved=1');
 } catch(Throwable $e){$error=$e->getMessage();}
}
$edit=null;
if(isset($_GET['edit'])){$q=$pdo->prepare('SELECT * FROM cities WHERE id=?');$q->execute([(int)$_GET['edit']]);$edit=$q->fetch()?:null;}
$rows=$pdo->query('SELECT * FROM cities ORDER BY country_code,name_ar')->fetchAll();
admin_page_start('إدارة المدن ومسارات السفر',$user);
if($error)echo '<p class="notice danger">'.e($error).'</p>';
if(isset($_GET['saved']))echo '<p class="notice">تم حفظ المدينة.</p>';
echo '<section class="panel"><form method="post" class="form-grid"><input type="hidden" name="_csrf" value="'.e(Security::csrf()).'"><input type="hidden" name="id" value="'.(int)($edit['id']??0).'"><label>اسم المدينة بالعربية<input name="name_ar" required value="'.e($edit['name_ar']??'').'"></label><label>الاسم بالإنجليزية<input name="name_en" value="'.e($edit['name_en']??'').'"></label><label>الدولة<select name="country_code"><option value="YE" '.(($edit['country_code']??'YE')==='YE'?'selected':'').'>اليمن</option><option value="SA" '.(($edit['country_code']??'')==='SA'?'selected':'').'>السعودية</option></select></label><label><input type="checkbox" name="is_active" '.((int)($edit['is_active']??1)===1?'checked':'').'> مدينة مفعلة</label><button class="btn btn-primary">حفظ المدينة</button></form></section><section class="panel"><table><thead><tr><th>المدينة</th><th>الدولة</th><th>الحالة</th><th></th></tr></thead><tbody>';
foreach($rows as $r)echo '<tr><td>'.e($r['name_ar']).'</td><td>'.($r['country_code']==='YE'?'اليمن':'السعودية').'</td><td>'.((int)$r['is_active']?'مفعلة':'موقوفة').'</td><td><a class="admin-link" href="/admin/cities.php?edit='.(int)$r['id'].'">تعديل</a></td></tr>';
echo '</tbody></table></section>'; admin_page_end();
