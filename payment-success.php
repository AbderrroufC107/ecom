<?php
require_once('header.php');

// التحقق من وجود تفاصيل الطلب في الجلسة
if (!isset($_SESSION['order_details'])) {
    header('Location: index.php');
    exit;
}

$order_details = $_SESSION['order_details'];
?>

<!-- سكريبت تتبع الشراء (Purchase Event) -->
<div class="page">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="success-container text-center py-5">
                    <div class="success-icon mb-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i>
                    </div>
                    <h1 class="success-title mb-4">تم استلام طلبك بنجاح!</h1>
                    <p class="success-message mb-4">شكراً لك <?php echo htmlspecialchars($order_details['customer_name']); ?> على ثقتك بنا. سنقوم بالتواصل معك قريباً لتأكيد الطلب.</p>
                    
                    <div class="order-summary mb-4">
                        <div class="order-header">
                            <h3><i class="fas fa-shopping-bag me-2"></i>ملخص الطلب</h3>
                        </div>
                        <div class="order-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <div class="info-icon">
                                            <i class="fas fa-hashtag"></i>
                                        </div>
                                        <div class="info-content">
                                            <label>رقم الطلب</label>
                                            <span id="orderNumber" class="highlight"></span>
                                        </div>
                                    </div>
                                    <div class="info-card">
                                        <div class="info-icon">
                                            <i class="fas fa-calendar-alt"></i>
                                        </div>
                                        <div class="info-content">
                                            <label>تاريخ الطلب</label>
                                            <span id="orderDate" class="highlight"><?php 
                                                $date = new DateTime($order_details['order_date']);
                                                echo $date->format('d/m/Y H:i');
                                            ?></span>
                                        </div>
                                    </div>
                                    <div class="info-card">
                                        <div class="info-icon">
                                            <i class="fas fa-box"></i>
                                        </div>
                                        <div class="info-content">
                                            <label>المنتج</label>
                                            <span class="highlight"><?php echo htmlspecialchars($order_details['product_name']); ?></span>
                                        </div>
                                    </div>
                                    <div class="info-card">
                                        <div class="info-icon">
                                            <i class="fas fa-sort-numeric-up"></i>
                                        </div>
                                        <div class="info-content">
                                            <label>الكمية</label>
                                            <span class="highlight"><?php echo htmlspecialchars($order_details['quantity']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <div class="info-icon">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </div>
                                        <div class="info-content">
                                            <label>طريقة الدفع</label>
                                            <span id="paymentMethod" class="highlight"></span>
                                        </div>
                                    </div>
                                    <div class="info-card">
                                        <div class="info-icon">
                                            <i class="fas fa-tag"></i>
                                        </div>
                                        <div class="info-content">
                                            <label>حالة الطلب</label>
                                            <span class="badge bg-success">قيد المعالجة</span>
                                        </div>
                                    </div>
                                    <div class="info-card">
                                        <div class="info-icon">
                                            <i class="fas fa-calculator"></i>
                                        </div>
                                        <div class="info-content">
                                            <label>المجموع</label>
                                            <span class="highlight price"><?php echo number_format($order_details['total_price'], 2, ',', ' '); ?> دج</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <a href="index.php" class="btn btn-primary me-2">
                            <i class="fas fa-home me-2"></i>العودة للرئيسية
                        </a>
                        <a href="dashboard.php" class="btn btn-outline-primary">
                            <i class="fas fa-user me-2"></i>لوحة التحكم
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.success-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 2rem;
}

.success-icon {
    animation: scaleIn 0.5s ease-out;
}

.success-title {
    color: #28a745;
    font-size: 2.5rem;
    font-weight: 700;
    animation: fadeInDown 0.5s ease-out;
}

.success-message {
    color: #666;
    font-size: 1.2rem;
    animation: fadeInUp 0.5s ease-out;
}

.order-summary {
    animation: fadeIn 0.5s ease-out;
    background: #fff;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    overflow: hidden;
}

.order-header {
    background: #f8f9fa;
    padding: 1.5rem;
    border-bottom: 1px solid #eee;
}

.order-header h3 {
    margin: 0;
    color: #333;
    font-size: 1.5rem;
}

.order-body {
    padding: 2rem;
}

.info-card {
    display: flex;
    align-items: center;
    padding: 1rem;
    margin-bottom: 1rem;
    background: #f8f9fa;
    border-radius: 10px;
    transition: all 0.3s ease;
}

.info-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.info-icon {
    width: 40px;
    height: 40px;
    background: #e9ecef;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: 1rem;
}

.info-icon i {
    color: #6c757d;
    font-size: 1.2rem;
}

.info-content {
    flex: 1;
}

.info-content label {
    display: block;
    color: #6c757d;
    font-size: 0.9rem;
    margin-bottom: 0.2rem;
}

.info-content .highlight {
    color: #333;
    font-weight: 600;
    font-size: 1.1rem;
}

.info-content .price {
    color: #28a745;
}

.action-buttons {
    animation: fadeInUp 0.5s ease-out;
    margin-top: 2rem;
}

.btn {
    padding: 0.8rem 2rem;
    font-size: 1.1rem;
    border-radius: 10px;
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.badge {
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
    border-radius: 5px;
}

@keyframes scaleIn {
    from {
        transform: scale(0);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // توليد رقم طلب عشوائي
    const orderNumber = 'ORD-' + Math.floor(Math.random() * 1000000).toString().padStart(6, '0');
    document.getElementById('orderNumber').textContent = orderNumber;
    
    // تعيين طريقة الدفع
    document.getElementById('paymentMethod').textContent = 'الدفع عند الاستلام';
});
</script>

<?php 
// حذف تفاصيل الطلب من الجلسة بعد عرضها
unset($_SESSION['order_details']);
require_once('footer.php'); 
?> 
