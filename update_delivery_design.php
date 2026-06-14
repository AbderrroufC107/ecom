<?php
$file = 'landing_page.php';
$content = file_get_contents($file);

// Replace the HTML for delivery options
$old_delivery_options = <<<'HTML'
                        <div class="delivery-options">
                            <?php if ($product_delivery_mode === 'free'): ?>
                                <button type="button" class="delivery-btn selected" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode('free', $product_delivery_mode), ENT_QUOTES, 'UTF-8') ?>">
                                    <span class="delivery-label">توصيل مجاني</span>
                                    <span class="delivery-price-tag">0 دج</span>
                                </button>
                            <?php elseif ($product_delivery_mode === 'home_only'): ?>
                                <button type="button" class="delivery-btn selected" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode('home', $product_delivery_mode), ENT_QUOTES, 'UTF-8') ?>">
                                    <span class="delivery-label">توصيل للمنزل</span>
                                    <span id="homePriceBtn" class="delivery-price-tag">0 دج</span>
                                </button>
                            <?php else: ?>
                                <button type="button" class="delivery-btn selected" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode('home', $product_delivery_mode), ENT_QUOTES, 'UTF-8') ?>">
                                    <span class="delivery-label">توصيل للمنزل</span>
                                    <span id="homePriceBtn" class="delivery-price-tag">0 دج</span>
                                </button>
                                <button type="button" class="delivery-btn" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode('office', $product_delivery_mode), ENT_QUOTES, 'UTF-8') ?>">
                                    <span class="delivery-label">توصيل للمكتب</span>
                                    <span id="officePriceBtn" class="delivery-price-tag">0 دج</span>
                                </button>
                            <?php endif; ?>
                        </div>
HTML;

$new_delivery_options = <<<'HTML'
                        <div class="delivery-options custom-delivery-grid">
                            <?php if ($product_delivery_mode === 'free'): ?>
                                <button type="button" class="delivery-btn custom-del-card selected" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode('free', $product_delivery_mode), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="del-radio-circle"></div>
                                    <div class="del-title">توصيل مجاني</div>
                                    <div class="del-subtitle green">أرخص وأسرع</div>
                                    <span class="delivery-price-tag" style="display:none;">0 دج</span>
                                </button>
                            <?php elseif ($product_delivery_mode === 'home_only'): ?>
                                <button type="button" class="delivery-btn custom-del-card selected" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode('home', $product_delivery_mode), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="del-radio-circle"></div>
                                    <div class="del-title">للمنزل</div>
                                    <div class="del-subtitle gray">مريح</div>
                                    <span id="homePriceBtn" class="delivery-price-tag" style="display:none;">0 دج</span>
                                </button>
                            <?php else: ?>
                                <button type="button" class="delivery-btn custom-del-card selected" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode('home', $product_delivery_mode), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="del-radio-circle"></div>
                                    <div class="del-title">للمنزل</div>
                                    <div class="del-subtitle gray">مريح</div>
                                    <span id="homePriceBtn" class="delivery-price-tag" style="display:none;">0 دج</span>
                                </button>
                                <button type="button" class="delivery-btn custom-del-card" data-type="<?= htmlspecialchars(resolve_delivery_type_by_mode('office', $product_delivery_mode), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="del-radio-circle"></div>
                                    <div class="del-title">للمكتب (Stop Desk)</div>
                                    <div class="del-subtitle green">أرخص وأسرع</div>
                                    <span id="officePriceBtn" class="delivery-price-tag" style="display:none;">0 دج</span>
                                </button>
                            <?php endif; ?>
                        </div>
HTML;

$content = str_replace($old_delivery_options, $new_delivery_options, $content);

// Now add the new CSS
$css = <<<'CSS'
/* Custom Delivery Cards CSS */
.custom-delivery-grid {
    display: flex;
    gap: 12px;
    flex-direction: row-reverse; /* Since direction is RTL, row-reverse will put Home on the left and Office on the right if needed, wait. RTL puts first item on right. Image has Home on left and Office on right. */
}
.custom-del-card {
    flex: 1;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 15px 10px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
    min-height: 110px;
}
.custom-del-card.selected {
    border-color: #3b82f6;
    background: #fff; /* Keep white background as per image */
}
.del-radio-circle {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 1px solid #9ca3af;
    margin-bottom: 10px;
    position: relative;
    background: #fff;
}
.custom-del-card.selected .del-radio-circle {
    border-color: #3b82f6;
}
.custom-del-card.selected .del-radio-circle::after {
    content: '';
    width: 10px;
    height: 10px;
    background: #3b82f6;
    border-radius: 50%;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}
.del-title {
    font-weight: 800;
    color: #1e3a8a; /* Dark blue as in image */
    font-size: 1.05rem;
    margin-bottom: 5px;
}
.del-subtitle {
    font-size: 0.9rem;
    font-weight: 700;
}
.del-subtitle.gray {
    color: #9ca3af;
}
.del-subtitle.green {
    color: #10b981;
}
CSS;

$content = str_replace('/* Delivery Banner */', $css . "\n\n/* Delivery Banner */", $content);

file_put_contents($file, $content);
echo "Done";
?>
