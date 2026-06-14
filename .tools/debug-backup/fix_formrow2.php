<?php
$file = 'landing_page.php';
$content = file_get_contents($file);

$old = '                    <div class="form-row">
                        <div class="form-group form-field col-md-6">
                            <label for="wilaya">الولاية *</label>
                            <select class="form-control" id="wilaya" name="wilaya" required>
                                <option value="">اختر الولاية</option>
                                <?php foreach (array_keys($shipping_data) as $wilaya): ?>
                                    <option value="<?= htmlspecialchars($wilaya) ?>"><?= htmlspecialchars($wilaya) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group form-field col-md-6">
                            <label for="commune">البلدية *</label>
                            <select class="form-control" id="commune" name="commune" required>
                                <option value="">اختر البلدية</option>
                            </select>
                        </div>
                    </div>';

$new = '                    <div style="display:flex; gap:10px; margin-bottom:15px; direction:rtl;">
                        <div style="flex:1; min-width:0;">
                            <label for="wilaya" style="display:block; font-weight:700; font-size:0.85rem; color:#374151; margin-bottom:6px; text-align:right;">الولاية *</label>
                            <select class="form-control" id="wilaya" name="wilaya" required style="width:100%;">
                                <option value="">اختر الولاية</option>
                                <?php foreach (array_keys($shipping_data) as $wilaya): ?>
                                    <option value="<?= htmlspecialchars($wilaya) ?>"><?= htmlspecialchars($wilaya) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="flex:1; min-width:0;">
                            <label for="commune" style="display:block; font-weight:700; font-size:0.85rem; color:#374151; margin-bottom:6px; text-align:right;">البلدية *</label>
                            <select class="form-control" id="commune" name="commune" required style="width:100%;">
                                <option value="">اختر البلدية</option>
                            </select>
                        </div>
                    </div>';

if (strpos($content, $old) !== false) {
    $content = str_replace($old, $new, $content);
    file_put_contents($file, $content);
    echo "Done";
} else {
    // Try to find by line and replace
    $lines = explode("\n", $content);
    // Replace form-row class with inline flex on line 4373 (0-indexed: 4372)
    foreach ($lines as $i => $line) {
        if (strpos($line, 'class="form-row"') !== false) {
            $lines[$i] = str_replace('class="form-row"', 'style="display:flex; gap:10px; margin-bottom:15px; direction:rtl;"', $line);
        }
        if (strpos($line, 'class="form-group form-field col-md-6"') !== false) {
            $lines[$i] = str_replace('class="form-group form-field col-md-6"', 'style="flex:1; min-width:0;"', $line);
        }
    }
    file_put_contents($file, implode("\n", $lines));
    echo "Done via line replacement";
}
?>
