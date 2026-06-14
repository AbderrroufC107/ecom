<?php
declare(strict_types=1);

require_once __DIR__ . '/next-common.php';

next_api_headers();

try {
    $productId = next_resolve_product_id($_GET['id'] ?? '');
    if ($productId <= 0) {
        next_json(['success' => false, 'message' => 'معرف المنتج غير صحيح.'], 400);
    }

    $payload = next_load_product_payload($pdo, $productId);
    if (empty($payload)) {
        next_json(['success' => false, 'message' => 'المنتج غير متاح حاليا.'], 404);
    }

    $payload['success'] = true;
    $payload['template'] = next_text($_GET['template'] ?? '');
    next_json($payload);
} catch (Throwable $e) {
    error_log('next-product failed: ' . $e->getMessage());
    next_json(['success' => false, 'message' => 'تعذر تحميل بيانات المنتج.'], 500);
}
