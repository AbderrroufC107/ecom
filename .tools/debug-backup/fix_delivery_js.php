<?php
$file = 'landing_page.php';
$content = file_get_contents($file);

// Replace the hiding logic
$content = preg_replace('/if \(productDeliveryMode !== \'free\' && !selectedWilaya\) \{.*?return \{\};\s*\}/s', 'if (productDeliveryMode !== \'free\' && !selectedWilaya) { if (deliveryNote) deliveryNote.textContent = \'اختر الولاية لعرض سعر التوصيل.\'; }', $content);

// Replace the toggling logic
$content = preg_replace('/button\.classList\.toggle\(\'is-hidden\', !isAvailable\);\s*button\.disabled = !isAvailable;/s', '', $content);

// In case the user also meant the form submit validation, we shouldn't disable buttons.
// Let's also ensure `is-hidden` isn't applied initially. I already used my custom CSS, so `display: flex` keeps them visible.
// But we must remove `disabled = true`.

file_put_contents($file, $content);
echo "Done";
?>
