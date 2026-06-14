<?php
$file = 'landing_page.php';
$content = file_get_contents($file);

$new_css = <<<'CSS'
<style>
/* Custom Layout overrides */
.order-offers-top {
    margin-bottom: 20px;
    direction: rtl;
}
.order-offers-top .offer-title {
    font-size: 1.3rem;
    font-weight: 800;
    text-align: center;
    color: #1f2937;
    margin-bottom: 15px;
}
.offer-card {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fff;
    margin-bottom: 12px;
    position: relative;
    cursor: pointer;
    transition: all 0.2s;
    direction: rtl;
}
.offer-card.selected {
    border: 1px solid #ef4444;
    background: #fffafa;
}
.offer-card .offer-select-dot {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 1px solid #9ca3af;
    margin-left: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    background: #fff;
}
.offer-card.selected .offer-select-dot {
    border-color: #3b82f6;
}
.offer-card.selected .offer-select-dot::after {
    content: '';
    width: 10px;
    height: 10px;
    background: #3b82f6;
    border-radius: 50%;
}
.offer-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-grow: 1;
}
.offer-details {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
}
.offer-qty-price, .offer-special-label {
    font-weight: 700;
    font-size: 1.1rem;
    color: #1e293b;
}
.offer-badge {
    background: #dcfce7;
    color: #166534;
    font-size: 0.75rem;
    padding: 3px 8px;
    border-radius: 4px;
    font-weight: 800;
    display: inline-block;
}
.offer-price-stack {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    text-align: left;
}
.offer-new {
    color: #ef4444;
    font-weight: 800;
    font-size: 1.25rem;
}
.offer-old {
    text-decoration: line-through;
    color: #9ca3af;
    font-size: 0.85rem;
}
.offer-popular-badge {
    position: absolute;
    top: -12px;
    left: 20px;
    background: #dc2626;
    color: white;
    font-size: 0.75rem;
    padding: 3px 12px;
    border-radius: 12px;
    font-weight: 800;
    z-index: 2;
}
.offer-thumb {
    display: none !important;
}

/* Delivery Banner */
.custom-delivery-banner {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 6px;
    padding: 12px 15px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    direction: rtl;
}
.custom-delivery-banner .text {
    font-size: 0.85rem;
    color: #1e3a8a;
    line-height: 1.6;
}
.custom-delivery-banner .text strong {
    font-weight: 800;
    font-size: 0.95rem;
}
.custom-delivery-banner .icon {
    font-size: 2rem;
    margin-right: 15px;
    opacity: 0.9;
}

/* Form Styles */
.order-card {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    background: #fff;
}
.order-form-title {
    text-align: center;
    font-weight: 800;
    font-size: 1.4rem;
    color: #111827;
    margin-bottom: 20px;
}
.order-card-head .order-tag,
.order-card-head .product-title,
.order-card-head .rating-row,
.order-card-head .hero-price,
.order-card-head .order-now-text {
    display: none;
}
.form-group label {
    font-weight: 700;
    font-size: 0.85rem;
    color: #374151;
    margin-bottom: 8px;
}
.form-control {
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 10px 12px;
    box-shadow: none;
}
.form-control:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
.phone-wrapper {
    display: flex;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    overflow: hidden;
    direction: ltr;
}
.phone-wrapper .country-code {
    background: #f3f4f6;
    padding: 10px 15px;
    border-right: 1px solid #d1d5db;
    display: flex;
    align-items: center;
    font-weight: 700;
    color: #374151;
}
.phone-wrapper .country-code img {
    width: 20px;
    margin-right: 8px;
}
.phone-wrapper input {
    border: none;
    border-radius: 0;
    width: 100%;
    padding: 10px 15px;
}
.phone-wrapper input:focus {
    outline: none;
}

/* Delivery Options */
.delivery-options {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 20px;
}
.delivery-btn {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 15px 10px;
    background: #fff;
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    position: relative;
    transition: all 0.2s;
}
.delivery-btn.selected {
    border-color: #3b82f6;
    background: #f0fdf4;
}
.delivery-btn .radio-icon {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 1px solid #d1d5db;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.delivery-btn.selected .radio-icon {
    border-color: #3b82f6;
}
.delivery-btn.selected .radio-icon::after {
    content: '';
    width: 8px;
    height: 8px;
    background: #3b82f6;
    border-radius: 50%;
}
.delivery-btn .delivery-label {
    font-weight: 700;
    font-size: 0.9rem;
    color: #111827;
    margin-bottom: 4px;
}
.delivery-btn .delivery-price-tag {
    font-size: 0.8rem;
    color: #6b7280;
}
.delivery-btn.selected .delivery-price-tag {
    color: #059669;
    font-weight: bold;
}

/* Submit Button */
.btn-buy-now {
    background: #22c55e !important;
    color: white !important;
    font-weight: 800 !important;
    font-size: 1.2rem !important;
    padding: 16px !important;
    border-radius: 8px !important;
    width: 100% !important;
    border: none !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 10px !important;
}
.btn-buy-now:hover {
    background: #16a34a !important;
}

.privacy-note, .form-features {
    display: none !important;
}

/* Custom grid for wilaya and commune */
.form-row {
    display: flex;
    margin-left: -5px;
    margin-right: -5px;
}
.form-row > .form-group {
    padding-left: 5px;
    padding-right: 5px;
    flex: 1;
}

/* Hide original delivery-info box since it's redundant */
.delivery-info {
    display: none !important;
}
.delivery-options-note {
    display: none !important;
}
</style>
CSS;

// 1. Replace the entire <style> block
$content = preg_replace('/<style>\s*\/\*\s*Custom Layout overrides\s*\*\/(.*?)<\/style>/s', $new_css, $content);

// 2. Update the "الأكثر مبيعاً" to "الأكثر طلباً"
$content = preg_replace('/<span class="offer-popular-badge">.*?<\/span>/', '<span class="offer-popular-badge">الأكثر طلباً</span>', $content);

// 3. Update the blue banner HTML
$new_banner = <<<HTML
<div class="custom-delivery-banner">
                <div class="text">
                    <strong>ملاحظة هامة حول التوصيل:</strong><br>
                    التوصيل متوفر لجميع الولايات: 400 دج للمكتب و 500 دج للمنزل.
                </div>
                <div class="icon">🚚</div>
            </div>
HTML;
$content = preg_replace('/<div class="custom-delivery-banner">.*?<\/div>\s*<\/div>/s', $new_banner, $content);

file_put_contents($file, $content);
echo "Done";
?>
