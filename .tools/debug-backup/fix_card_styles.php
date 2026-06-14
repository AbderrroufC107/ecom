<?php
$file = 'landing_page.php';
$content = file_get_contents($file);

$css_fixes = <<<'CSS'
/* Fixes for is-popular styling and price wrapping */
.offer-card.is-popular:not(.selected) {
    border-color: #e5e7eb !important;
    background: #fff !important;
    box-shadow: none !important;
}
.offer-card.is-popular:not(.selected) .offer-select-dot {
    background: #fff !important;
    box-shadow: none !important;
    border-color: #9ca3af !important;
}
.offer-card.selected, .offer-card.selected.is-popular {
    border-color: #ef4444 !important;
    background: #fffafa !important;
    box-shadow: none !important;
}
.offer-card.selected .offer-select-dot, .offer-card.selected.is-popular .offer-select-dot {
    background: #fff !important;
    box-shadow: none !important;
    border-color: #3b82f6 !important;
}
.offer-new, .offer-old {
    white-space: nowrap !important;
    display: inline-block;
}
.offer-qty-price {
    text-align: right;
    line-height: 1.4;
}
.offer-details {
    width: 100%;
}
.offer-price-stack {
    flex-shrink: 0;
    margin-right: 15px; /* Add some space between text and price */
}
CSS;

$content = str_replace('/* Delivery Banner */', $css_fixes . "\n\n/* Delivery Banner */", $content);

file_put_contents($file, $content);
echo "Fixed card styles";
?>
