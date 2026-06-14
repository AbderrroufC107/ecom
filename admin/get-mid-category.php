<?php
require_once 'inc/config.php';

$id = 0;
if (isset($_POST['id'])) {
    $id = (int)$_POST['id'];
} elseif (isset($_POST['tcat_id'])) {
    $id = (int)$_POST['tcat_id'];
}

echo '<option value="">اختر الفئة المتوسطة</option>';
if ($id <= 0) {
    exit;
}

$statement = $pdo->prepare("SELECT * FROM tbl_mid_category WHERE tcat_id=? ORDER BY mcat_name ASC");
$statement->execute([$id]);
$result = $statement->fetchAll(PDO::FETCH_ASSOC);
foreach ($result as $row) {
    echo '<option value="' . (int)$row['mcat_id'] . '">' . htmlspecialchars($row['mcat_name'], ENT_QUOTES, 'UTF-8') . '</option>';
}
