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
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>رقم الطلب</th>
                                            <th>المنتج</th>
                                            <th>السعر</th>
                                            <th>الكمية</th>
                                            <th>المجموع</th>
                                            <th>التاريخ</th>
                                            <th>الحالة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // استخدام customer_name بدلاً من customer_id
                                        $statement = $pdo->prepare("SELECT * FROM tbl_order WHERE customer_name=? ORDER BY id DESC");
                                        $statement->execute(array($_SESSION['customer']['cust_name']));
                                        $result = $statement->fetchAll(PDO::FETCH_ASSOC);
                                        if($statement->rowCount() == 0) {
                                            echo '<tr><td colspan="7" class="text-center">لا توجد طلبات</td></tr>';
                                        } else {
                                            foreach($result as $row) {
                                                ?>
                                                <tr>
                                                    <td><?php echo $row['id']; ?></td>
                                                    <td><?php echo $row['product_name']; ?></td>
                                                    <td><?php echo $row['unit_price']; ?> دج</td>
                                                    <td><?php echo $row['quantity']; ?></td>
                                                    <td><?php echo $row['total_price']; ?> دج</td>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($row['order_date'])); ?></td>
                                                    <td>
                                                        <?php 
                                                        if($row['order_status'] == 'Pending') {
                                                            echo '<span class="badge badge-warning">غير مؤكد</span>';
                                                        } elseif($row['order_status'] == 'Confirmed') {
                                                            echo '<span class="badge badge-info">مؤكد</span>';
                                                        } elseif($row['order_status'] == 'Completed') {
                                                            echo '<span class="badge badge-success">مكتمل</span>';
                                                        } elseif($row['order_status'] == 'Cancelled') {
                                                            echo '<span class="badge badge-danger">ملغي</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                </tr>
                                                <?php
                                            }
                                        }
                                        ?>
                                    </tbody>
                                </table>
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

.badge {
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 600;
}

.badge-warning {
    background: #ffc107;
    color: #000;
}

.badge-success {
    background: #28a745;
    color: #fff;
}

.badge-danger {
    background: #dc3545;
    color: #fff;
}

.badge-info {
    background: #17a2b8;
    color: #fff;
}

.table {
    border-radius: 10px;
    overflow: hidden;
}

.table thead th {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #333;
    padding: 15px;
}

.table tbody td {
    padding: 12px 15px;
    vertical-align: middle;
}

@media (max-width: 768px) {
    .table-responsive {
        border: 0;
    }
    
    .table thead {
        display: none;
    }
    
    .table tbody tr {
        display: block;
        margin-bottom: 1rem;
        border: 1px solid #dee2e6;
        border-radius: 8px;
    }
    
    .table tbody td {
        display: block;
        text-align: right;
        padding: 10px;
        border: none;
        position: relative;
        padding-right: 50%;
    }
    
    .table tbody td:before {
        content: attr(data-label);
        position: absolute;
        right: 10px;
        font-weight: 600;
        color: #666;
    }
}
</style>

<?php require_once('footer.php'); ?>