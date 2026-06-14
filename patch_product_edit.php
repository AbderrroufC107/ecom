<?php
$file = 'admin/product-edit.php';
$content = file_get_contents($file);

// 1. Add pixel save logic
$search1 = '$pdo->prepare("DELETE FROM tbl_product_size WHERE p_id=?")->execute([$id]);';
$replace1 = $search1 . '
        $pdo->prepare("DELETE FROM tbl_product_pixel WHERE product_id=?")->execute([$id]);
        foreach ((array)($_POST[\'pixel\'] ?? []) as $pixel_id_value) {
            $pdo->prepare("INSERT INTO tbl_product_pixel (pixel_id, product_id) VALUES (?, ?)")->execute([(int)$pixel_id_value, $id]);
        }';
$content = str_replace($search1, $replace1, $content);

// 2. Add pixel fetching logic
$search2 = '$stmt_colors = $pdo->prepare("SELECT color_id FROM tbl_product_color WHERE p_id=?");';
$replace2 = '$pixel_id = [];
$stmt_pixels = $pdo->prepare("SELECT pixel_id FROM tbl_product_pixel WHERE product_id=?");
$stmt_pixels->execute([$id]);
foreach ($stmt_pixels->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $pixel_id[] = (int)$row[\'pixel_id\'];
}
' . $search2;
$content = str_replace($search2, $replace2, $content);

// 3. Add $selected_pixels_for_form
$search3 = '$selected_sizes_for_form = array_map(\'intval\', (array)($_POST[\'size\'] ?? $size_id));';
$replace3 = '$selected_pixels_for_form = array_map(\'intval\', (array)($_POST[\'pixel\'] ?? $pixel_id));
' . $search3;
$content = str_replace($search3, $replace3, $content);

// 4. Add HTML UI
$search4 = '<div id="colorPhotoFields"></div>';
$replace4 = $search4 . '
                        <div class="form-group">
                            <label class="col-sm-3 control-label">بكسلات التتبع (Pixels)</label>
                            <div class="col-sm-4">
                                <select name="pixel[]" class="form-control select2" multiple="multiple">
                                    <?php
                                    $stmt = $pdo->prepare("SELECT * FROM tbl_pixel ORDER BY pixel_name ASC");
                                    $stmt->execute();
                                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row):
                                        $selected = in_array((int)$row[\'id\'], $selected_pixels_for_form, true) ? \'selected\' : \'\';
                                        echo "<option value=\'{$row[\'id\']}\' {$selected}>{$row[\'pixel_name\']} ({$row[\'pixel_network\']})</option>";
                                    endforeach;
                                    ?>
                                </select>
                            </div>
                        </div>';
$content = str_replace($search4, $replace4, $content);

file_put_contents($file, $content);
echo "Patched product-edit.php";
