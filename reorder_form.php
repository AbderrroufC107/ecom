<?php
$file = 'landing_page.php';
$content = file_get_contents($file);

// The delivery block (lines 4415-4440) needs to move BEFORE the wilaya block (lines 4373-4389)
// We'll extract the delivery block and insert it before wilaya

$delivery_block = '                    <div class="form-group">
                        <label>&#1606;&#1608;&#1593;&#32;&#1575;&#1604;&#1578;&#1608;&#1589;&#1610;&#1604; *</label>
                        <div class="delivery-options">
                            <?php if ($product_delivery_mode === \'free\'): ?>
                                <button type="button" class="delivery-btn selected" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode(\'free\', $product_delivery_mode), ENT_QUOTES, \'UTF-8\') ?>">
                                    <span class="delivery-label">توصيل مجاني</span>
                                    <span class="delivery-price-tag">0 دج</span>
                                </button>
                            <?php elseif ($product_delivery_mode === \'home_only\'): ?>
                                <button type="button" class="delivery-btn selected" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode(\'home\', $product_delivery_mode), ENT_QUOTES, \'UTF-8\') ?>">
                                    <span class="delivery-label">توصيل للمنزل</span>
                                    <span id="homePriceBtn" class="delivery-price-tag">0 دج</span>
                                </button>
                            <?php else: ?>
                                <button type="button" class="delivery-btn selected" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode(\'home\', $product_delivery_mode), ENT_QUOTES, \'UTF-8\') ?>">
                                    <span class="delivery-label">توصيل للمنزل</span>
                                    <span id="homePriceBtn" class="delivery-price-tag">0 دج</span>
                                </button>
                                <button type="button" class="delivery-btn" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode(\'office\', $product_delivery_mode), ENT_QUOTES, \'UTF-8\') ?>">
                                    <span class="delivery-label">توصيل للمكتب</span>
                                    <span id="officePriceBtn" class="delivery-price-tag">0 دج</span>
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="delivery-options-note" id="deliveryOptionsNote">اختر الولاية لعرض خيارات التوصيل المتاحة.</div>
                    </div>';

$wilaya_block = '                    <div style="display:flex; gap:10px; margin-bottom:15px; direction:rtl;">
                        <div style="flex:1; min-width:0;">
                            <label for="wilaya">الولاية *</label>
                            <select class="form-control" id="wilaya" name="wilaya" required>
                                <option value="">اختر الولاية</option>
                                <?php foreach (array_keys($shipping_data) as $wilaya): ?>
                                    <option value="<?= htmlspecialchars($wilaya) ?>"><?= htmlspecialchars($wilaya) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="flex:1; min-width:0;">
                            <label for="commune">البلدية *</label>
                            <select class="form-control" id="commune" name="commune" required style="width:100%;">
                                <option value="">اختر البلدية</option>
                            </select>
                        </div>
                    </div>';

// Remove delivery block from current location, add extra newlines after
$content = str_replace($delivery_block . "\r\n\r\n\r\n", '', $content);
$content = str_replace($delivery_block . "\n\n\n", '', $content);
$content = str_replace($delivery_block, '', $content);

// Now insert delivery block BEFORE wilaya block
$content = str_replace($wilaya_block, $delivery_block . "\n" . $wilaya_block, $content);

file_put_contents($file, $content);
echo "Done: moved delivery before wilaya";
?>
