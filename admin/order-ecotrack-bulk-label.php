<?php
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once('inc/config.php');
require_once('inc/functions.php');

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

if (!function_exists('admin_ecotrack_bulk_labels_redirect')) {
    function admin_ecotrack_bulk_labels_redirect($message = '', $type = 'warning')
    {
        if ($message !== '') {
            admin_set_flash_message('orders', $type, $message);
        }

        header('Location: order.php');
        exit;
    }
}

if (!function_exists('admin_ecotrack_bulk_label_src')) {
    function admin_ecotrack_bulk_label_src($order_id)
    {
        return 'order-ecotrack-label.php?order_id=' . (int) $order_id . '&inline=1&print=1#toolbar=0&navpanes=0&scrollbar=0&view=FitH';
    }
}

$order_ids = [];
if (!empty($_GET['order_ids']) && is_array($_GET['order_ids'])) {
    $order_ids = $_GET['order_ids'];
} elseif (!empty($_POST['order_ids']) && is_array($_POST['order_ids'])) {
    $order_ids = $_POST['order_ids'];
} elseif (!empty($_GET['ids'])) {
    $order_ids = explode(',', (string) $_GET['ids']);
}

$order_ids = array_values(array_unique(array_filter(array_map('intval', $order_ids))));
if (empty($order_ids)) {
    admin_ecotrack_bulk_labels_redirect('حدد طلبًا واحدًا على الأقل لفتح الطباعة الجماعية.');
}

admin_ensure_ecotrack_setting_columns($pdo);
admin_ensure_order_ecotrack_columns($pdo);

$placeholders = implode(',', array_fill(0, count($order_ids), '?'));
$statement = $pdo->prepare("SELECT id, customer_name, product_name, order_date, ecotrack_tracking FROM tbl_order WHERE id IN ($placeholders)");
$statement->execute($order_ids);
$rows = $statement->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    admin_ecotrack_bulk_labels_redirect('لم يتم العثور على الطلبات المحددة.');
}

$orders_by_id = [];
foreach ($rows as $row) {
    $orders_by_id[(int) $row['id']] = $row;
}

$valid_orders = [];
$skipped_orders = [];

foreach ($order_ids as $order_id) {
    if (!isset($orders_by_id[$order_id])) {
        $skipped_orders[] = '#' . $order_id . ' غير موجود.';
        continue;
    }

    $order = $orders_by_id[$order_id];
    $tracking = trim((string) ($order['ecotrack_tracking'] ?? ''));
    if ($tracking === '') {
        $skipped_orders[] = '#' . $order_id . ' لا يملك رقم تتبع داخل ECOTRACK.';
        continue;
    }

    $valid_orders[] = $order;
}

if (empty($valid_orders)) {
    admin_ecotrack_bulk_labels_redirect('الطلبات المحددة لا تحتوي على بوردرو صالح للطباعة الجماعية.');
}

$sheets = array_chunk($valid_orders, 4);
?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طباعة بوردرو ECOTRACK</title>
    <style>
        :root {
            --ink: #1f2937;
            --muted: #667085;
            --line: #d7e0ea;
            --panel: #ffffff;
            --bg: #eef3f8;
            --accent: #0f766e;
            --accent-2: #2563eb;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Tahoma, "Segoe UI", sans-serif;
            background: var(--bg);
            color: var(--ink);
        }
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 20;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px 22px;
            background: rgba(255, 255, 255, 0.96);
            border-bottom: 1px solid var(--line);
            backdrop-filter: blur(10px);
        }
        .toolbar h1 {
            margin: 0;
            font-size: 24px;
            line-height: 1.4;
        }
        .toolbar p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 14px;
        }
        .toolbar-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 18px;
            border-radius: 12px;
            border: 1px solid transparent;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-primary {
            background: var(--accent);
            color: #fff;
        }
        .btn-secondary {
            background: #fff;
            color: var(--ink);
            border-color: var(--line);
        }
        .page-wrap {
            padding: 24px;
        }
        .notice {
            margin: 0 auto 18px;
            max-width: 210mm;
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid #f5cf76;
            background: #fff8e8;
            color: #8a5a00;
            line-height: 1.8;
        }
        .notice strong {
            display: block;
            margin-bottom: 6px;
        }
        .sheet {
            width: 210mm;
            height: 297mm;
            margin: 0 auto 18px;
            padding: 6mm;
            background: var(--panel);
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.14);
            border-radius: 6mm;
            page-break-after: always;
        }
        .sheet:last-child {
            page-break-after: auto;
        }
        .sheet-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            grid-template-rows: repeat(2, 1fr);
            gap: 5mm;
            height: calc(297mm - 12mm);
        }
        .label-slot {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border-radius: 4mm;
            border: 1px solid var(--line);
            background: #fff;
        }
        .label-slot.is-empty {
            border-style: dashed;
            background: #f8fafc;
        }
        .slot-meta {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 8px 10px;
            border-bottom: 1px solid var(--line);
            background: #f8fafc;
            font-size: 12px;
            color: var(--muted);
        }
        .slot-meta strong {
            color: var(--ink);
        }
        .label-frame {
            width: 100%;
            height: 100%;
            min-height: 0;
            flex: 1 1 auto;
            border: 0;
            background: #fff;
        }
        .label-fallback {
            padding: 16px;
            line-height: 1.8;
        }
        .label-empty {
            display: flex;
            flex: 1 1 auto;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-size: 14px;
            min-height: 120px;
        }
        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }
            body {
                background: #fff;
            }
            .toolbar,
            .notice,
            .screen-only {
                display: none !important;
            }
            .page-wrap {
                padding: 0;
            }
            .sheet {
                width: 210mm;
                height: 297mm;
                margin: 0;
                padding: 5mm;
                border-radius: 0;
                box-shadow: none;
                break-after: page;
            }
            .sheet:last-child {
                break-after: auto;
            }
            .sheet-grid {
                gap: 4mm;
                height: calc(297mm - 10mm);
            }
            .label-slot {
                border-color: #cbd5e1;
                overflow: hidden;
            }
            .slot-meta {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar screen-only">
        <div>
            <h1>طباعة بوردرو ECOTRACK</h1>
            <p>تم ترتيب البوردرو على شكل 4 ملصقات داخل كل ورقة A4. استخدم زر الطباعة أو حفظ PDF من المتصفح.</p>
        </div>
        <div class="toolbar-actions">
            <a href="order.php" class="btn btn-secondary">العودة إلى الطلبات</a>
            <button type="button" class="btn btn-primary" onclick="window.print()">طباعة / حفظ PDF</button>
        </div>
    </div>

    <div class="page-wrap">
        <?php if (!empty($skipped_orders)): ?>
            <div class="notice screen-only">
                <strong>عناصر تم تجاوزها</strong>
                <?php echo htmlspecialchars(implode(' | ', $skipped_orders), ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php foreach ($sheets as $sheet_index => $sheet_orders): ?>
            <section class="sheet">
                <div class="sheet-grid">
                    <?php for ($slot = 0; $slot < 4; $slot++): ?>
                        <?php $order = $sheet_orders[$slot] ?? null; ?>
                        <article class="label-slot<?php echo $order ? '' : ' is-empty'; ?>">
                            <?php if ($order): ?>
                                <div class="slot-meta screen-only">
                                    <span><strong>#<?php echo (int) $order['id']; ?></strong> <?php echo htmlspecialchars((string) ($order['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span><?php echo htmlspecialchars((string) ($order['ecotrack_tracking'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <iframe
                                    class="label-frame"
                                    loading="lazy"
                                    src="<?php echo htmlspecialchars(admin_ecotrack_bulk_label_src((int) $order['id']), ENT_QUOTES, 'UTF-8'); ?>"
                                    title="ECOTRACK Label <?php echo (int) $order['id']; ?>"
                                ></iframe>
                                <noscript>
                                    <div class="label-fallback">
                                        افتح الملصق مباشرة من:
                                        <a href="<?php echo htmlspecialchars(admin_ecotrack_bulk_label_src((int) $order['id']), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">طلب #<?php echo (int) $order['id']; ?></a>
                                    </div>
                                </noscript>
                            <?php else: ?>
                                <div class="label-empty">مساحة فارغة</div>
                            <?php endif; ?>
                        </article>
                    <?php endfor; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</body>
</html>
