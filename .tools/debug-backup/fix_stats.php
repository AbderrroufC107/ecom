<?php
$file = 'order-statistics.php';
$content = file_get_contents($file);

$bad_block = <<<EOF
                <div class="box-body">
                                        \$total_profit = \$order['quantity'] * \$profit_per_unit;
EOF;

$good_block = <<<EOF
                <div class="box-body">
                    <canvas id="statusChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="box box-success">
                <div class="box-header with-border">
                    <h3 class="box-title">المبيعات الشهرية</h3>
                </div>
                <div class="box-body">
                    <canvas id="salesChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- جدول الطلبات -->
        <div class="col-md-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">الطلبات</h3>
                </div>
                <div class="box-body">
                    <div class="table-responsive">
                        <table id="ordersTable" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>رقم الطلب</th>
                                    <th>اسم العميل</th>
                                    <th>المنتج</th>
                                    <th>الكمية</th>
                                    <th>السعر الإجمالي (مع التوصيل)</th>
                                    <th>الربح</th>
                                    <th>التاريخ</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // بناء استعلام البحث
                                \$where_conditions = [];
                                \$params = [];

                                if (!empty(\$_GET['date_from'])) {
                                    \$where_conditions[] = "order_date >= ?";
                                    \$params[] = \$_GET['date_from'];
                                }

                                if (!empty(\$_GET['date_to'])) {
                                    \$where_conditions[] = "order_date <= ?";
                                    \$params[] = \$_GET['date_to'] . ' 23:59:59';
                                }

                                if (!empty(\$_GET['status'])) {
                                    \$where_conditions[] = "order_status = ?";
                                    \$params[] = \$_GET['status'];
                                }

                                if (!empty(\$_GET['product_name'])) {
                                    // فلترة حسب اسم المنتج من جدول المنتجات
                                    \$where_conditions[] = "(p.p_name = ? OR o.product_name = ?)";
                                    \$params[] = \$_GET['product_name'];
                                    \$params[] = \$_GET['product_name'];
                                }

                                \$where_clause = !empty(\$where_conditions) ? 'WHERE ' . implode(' AND ', \$where_conditions) : '';
                                
                                \$statement = \$pdo->prepare("
                                    SELECT o.*, p.purchase_price, p.p_name, c.cust_name 
                                    FROM tbl_order o 
                                    LEFT JOIN tbl_product p ON o.product_id = p.p_id 
                                    LEFT JOIN tbl_customer c ON o.customer_id = c.id 
                                    \$where_clause 
                                    ORDER BY o.order_date DESC 
                                    LIMIT 100
                                ");
                                \$statement->execute(\$params);
                                \$orders = \$statement->fetchAll(PDO::FETCH_ASSOC);

                                foreach (\$orders as \$order):
                                ?>
                                <tr>
                                    <td><?php echo \$order['id']; ?></td>
                                    <td><?php echo htmlspecialchars(normalize_utf8(\$order['cust_name'] ? \$order['cust_name'] : \$order['customer_name'])); ?></td>
                                    <td><?php echo htmlspecialchars(normalize_utf8(\$order['p_name'] ? \$order['p_name'] : \$order['product_name'])); ?></td>
                                    <td><?php echo \$order['quantity']; ?></td>
                                    <td><?php echo number_format(\$order['total_price']); ?> دج</td>
                                    <td>
                                        <?php 
                                        \$purchase_price = \$order['purchase_price'] ?? 0;
                                        \$profit_per_unit = \$order['unit_price'] - \$purchase_price;
                                        \$total_profit = \$order['quantity'] * \$profit_per_unit;
EOF;

$content = str_replace($bad_block, $good_block, $content);

// Also remove the previously added CSS block to keep it clean, if he doesn't want the Ecotrack table here at all.
// Wait, the user didn't ask to remove the CSS, but it doesn't matter, we can keep the CSS, it's nice.
// But the wrapper <div class="orders-page"> was broken anyway.
// Let's just fix it.

file_put_contents($file, $content);
echo "Fixed!";
