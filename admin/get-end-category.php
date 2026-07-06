<?php
require_once 'inc/config.php';

$id = 0;
if (isset($_POST['id'])) {
    $id = (int)$_POST['id'];
} elseif (isset($_POST['mcat_id'])) {
    $id = (int)$_POST['mcat_id'];
}

echo '<option value="">اختر الفئة النهائية</option>';
if ($id <= 0) {
    exit;
}

$statement = $dbRepo->prepare("SELECT * FROM tbl_end_category WHERE mcat_id=? ORDER BY ecat_name ASC");
$statement->execute([$id]);
$result = $statement->fetchAll(PDO::FETCH_ASSOC);
foreach ($result as $row) {
    echo '<option value="' . (int)$row['ecat_id'] . '">' . htmlspecialchars($row['ecat_name'], ENT_QUOTES, 'UTF-8') . '</option>';
}
