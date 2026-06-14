<?php
$url = 'http://127.0.0.1/ecom/admin/ai-insights.php';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "==============================================\n";
echo "  AI Insights - RTL & Content Verification\n";
echo "==============================================\n\n";

echo "HTTP Status: {$httpCode}\n";
echo "Size: " . strlen($response) . " bytes\n\n";

$pass = 0;
$fail = 0;

function check($label, $found) {
    global $pass, $fail;
    if ($found) { $pass++; echo "[PASS] {$label}\n"; }
    else { $fail++; echo "[FAIL] {$label}\n"; }
}

// RTL checks
check('dir="rtl"', strpos($response, 'dir="rtl"') !== false);
check('lang="ar"', strpos($response, 'lang="ar"') !== false);
check('No dir="ltr"', strpos($response, 'dir="ltr"') === false);
check('No lang="fr"', strpos($response, 'lang="fr"') === false);

// Content sections
$sections = [
    'الذكاء الاصطناعي وتحليل الأعمال',
    'تحليل أسباب الإلغاء',
    'مخاطر المنتجات',
    'تحليل أداء الموظفين',
    'تحليل الولايات',
    'تحليل العروض',
    'زمن الاستجابة',
    'توقع الإيرادات',
    'توقع الإرجاع',
    'تشغيل جميع التحليلات',
    'إرسال التقرير الصباحي',
];

foreach ($sections as $s) {
    check("Section: {$s}", strpos($response, $s) !== false);
}

// Charts
check('Chart.js loaded', strpos($response, 'chart.js') !== false || strpos($response, 'Chart') !== false);
check('revenueChart canvas', strpos($response, 'revenueChart') !== false);

echo "\n==============================================\n";
echo "  RESULTS: {$pass} passed, {$fail} failed\n";
echo "==============================================\n";
