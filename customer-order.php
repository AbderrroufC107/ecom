<?php require_once('header.php'); ?>

<?php
// Check if the customer is logged in or not
if(!isset($_SESSION['customer'])) {
    header('location: login.php');
    exit;
}
?>

<div class="page">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="user-content">
                    <h2 class="text-center">طلباتي</h2>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="list-group">
                                <a href="dashboard.php" class="list-group-item">معلوماتي</a>
                                <a href="customer-order.php" class="list-group-item active">طلباتي</a>
                                <a href="edit-profile.php" class="list-group-item">تعديل معلوماتي</a>
                                <a href="logout.php" class="list-group-item">تسجيل الخروج</a>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="orders-list">
                                <?php
                                // استخدام customer_name بدلاً من customer_id
                                $statement = $pdo->prepare("SELECT * FROM tbl_order WHERE customer_name=? ORDER BY id DESC");
                                $statement->execute(array($_SESSION['customer']['cust_name']));
                                $result = $statement->fetchAll(PDO::FETCH_ASSOC);
                                if($statement->rowCount() == 0) {
                                    echo '<div class="empty-state">لا توجد طلبات</div>';
                                } else {
                                    foreach($result as $row) {
                                        $statusText = 'غير مؤكد';
                                        $statusClass = 'status-pending';

                                        if($row['order_status'] == 'Confirmed') {
                                            $statusText = 'مؤكد';
                                            $statusClass = 'status-confirmed';
                                        } elseif($row['order_status'] == 'Completed') {
                                            $statusText = 'مكتمل';
                                            $statusClass = 'status-completed';
                                        } elseif($row['order_status'] == 'Cancelled') {
                                            $statusText = 'ملغي';
                                            $statusClass = 'status-cancelled';
                                        }
                                        ?>
                                        <div class="order-card">
                                            <div class="order-card-header">
                                                <div>
                                                    <div class="order-label">رقم الطلب</div>
                                                    <div class="order-value">#<?php echo $row['id']; ?></div>
                                                </div>
                                                <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                            </div>
                                            <div class="order-card-body">
                                                <div class="order-info">
                                                    <span class="order-info-label">المنتج</span>
                                                    <span class="order-info-value"><?php echo $row['product_name']; ?></span>
                                                </div>
                                                <div class="order-info">
                                                    <span class="order-info-label">السعر</span>
                                                    <span class="order-info-value"><?php echo $row['unit_price']; ?> دج</span>
                                                </div>
                                                <div class="order-info">
                                                    <span class="order-info-label">الكمية</span>
                                                    <span class="order-info-value"><?php echo $row['quantity']; ?></span>
                                                </div>
                                                <div class="order-info">
                                                    <span class="order-info-label">المجموع</span>
                                                    <span class="order-info-value total-price"><?php echo $row['total_price']; ?> دج</span>
                                                </div>
                                            </div>
                                            <div class="order-card-footer">
                                                <span>التاريخ</span>
                                                <strong><?php echo date('d/m/Y H:i', strtotime($row['order_date'])); ?></strong>
                                            </div>
                                        </div>
                                        <?php
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>                
            </div>
        </div>
    </div>
</div>

<style>
.user-content {
    background: #fff;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
    margin: 40px 0;
}

.list-group-item.active {
    background-color: #007bff;
    border-color: #007bff;
}

.orders-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.order-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 18px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
}

.order-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f0f0f0;
}

.order-label {
    font-size: 0.8rem;
    color: #6b7280;
    margin-bottom: 4px;
}

.order-value {
    font-size: 1.05rem;
    font-weight: 700;
    color: #111827;
}

.status-badge {
    padding: 7px 12px;
    border-radius: 999px;
    font-size: 0.9rem;
    font-weight: 600;
}

.status-pending {
    background: #fff3cd;
    color: #8a6d3b;
}

.status-confirmed {
    background: #d1ecf1;
    color: #0c5460;
}

.status-completed {
    background: #d4edda;
    color: #155724;
}

.status-cancelled {
    background: #f8d7da;
    color: #721c24;
}

.order-card-body {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.order-info {
    background: #f9fafb;
    border-radius: 12px;
    padding: 12px;
}

.order-info-label {
    display: block;
    font-size: 0.8rem;
    color: #6b7280;
    margin-bottom: 4px;
}

.order-info-value {
    display: block;
    font-weight: 600;
    color: #111827;
}

.total-price {
    color: #0d6efd;
}

.order-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid #f0f0f0;
    color: #4b5563;
    font-size: 0.95rem;
}

.empty-state {
    text-align: center;
    padding: 24px;
    border: 1px dashed #d1d5db;
    border-radius: 12px;
    background: #f9fafb;
    color: #6b7280;
}

@media (max-width: 768px) {
    .order-card {
        padding: 16px;
    }

    .order-card-header,
    .order-card-footer {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }

    .order-card-body {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require_once('footer.php'); ?>