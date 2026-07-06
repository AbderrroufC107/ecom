<?php
ob_start();
session_start();
include("inc/config.php");
include("inc/functions.php");

// التحقق من تسجيل الدخول
if(!isset($_SESSION['user'])) {
    header("location: login.php");
    exit;
}

$success_message = '';
$error_message = '';
$test_result = '';

// اختبار الاتصال
if(isset($_POST['test_connection'])) {
    try {
        require_once('../assets/procolis-notification.php');
        
        $token = '8a24f510a69c5c8d3cfce2928fdcefe00ff6f6b5703ceef9e554ee1b6130ed29';
        $key = '9ada52b8b8614dc1b890bfce58a8c9cf';
        
        $procolis = new ProcolisNotification($token, $key);
        $result = $procolis->testConnection();
        
        if($result['success']) {
            $success_message = 'اختبار الاتصال نجح!';
            $test_result = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $error_message = 'فشل اختبار الاتصال: ' . $result['message'];
        }
        
    } catch(Exception $e) {
        $error_message = 'خطأ: ' . $e->getMessage();
    }
}

// اختبار إرسال طلب تجريبي
if(isset($_POST['test_order'])) {
    try {
        require_once('../assets/procolis-notification.php');
        
        $token = '8a24f510a69c5c8d3cfce2928fdcefe00ff6f6b5703ceef9e554ee1b6130ed29';
        $key = '9ada52b8b8614dc1b890bfce58a8c9cf';
        
        $procolis = new ProcolisNotification($token, $key);
        
        // بيانات طلب تجريبي
        $orderData = [
            'customer_name' => 'اختبار العميل',
            'customer_phone' => '0555123456',
            'wilaya' => 'الجزائر',
            'commune' => 'الجزائر',
            'product_name' => 'منتج تجريبي',
            'total_price' => '1000',
            'delivery_type' => 'منزل',
            'order_id' => 'TEST_' . time()
        ];
        
        $formattedOrder = $procolis->formatOrderForProcolis($orderData);
        $result = $procolis->createOrder($formattedOrder);
        
        if($result) {
            $success_message = 'تم إرسال الطلب التجريبي بنجاح!';
            $test_result = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $error_message = 'فشل في إرسال الطلب التجريبي';
        }
        
    } catch(Exception $e) {
        $error_message = 'خطأ: ' . $e->getMessage();
    }
}

// اختبار قراءة التعريفة
if(isset($_POST['test_tarification'])) {
    try {
        require_once('../assets/procolis-notification.php');
        
        $token = '8a24f510a69c5c8d3cfce2928fdcefe00ff6f6b5703ceef9e554ee1b6130ed29';
        $key = '9ada52b8b8614dc1b890bfce58a8c9cf';
        
        $procolis = new ProcolisNotification($token, $key);
        $result = $procolis->getTarification();
        
        if($result) {
            $success_message = 'تم جلب التعريفة بنجاح!';
            $test_result = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $error_message = 'فشل في جلب التعريفة';
        }
        
    } catch(Exception $e) {
        $error_message = 'خطأ: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>اختبار Procolis API</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f8f9fc;
        }
        .sidebar {
            min-height: 100vh;
            background-color: #4e73df;
            padding: 1rem;
        }
        .sidebar a {
            color: rgba(255,255,255,.8);
            text-decoration: none;
            padding: 0.5rem 1rem;
            display: block;
        }
        .sidebar a:hover {
            color: #fff;
            background-color: rgba(255,255,255,.1);
        }
        .content {
            padding: 1.5rem;
        }
        .test-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        .result-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
            max-height: 400px;
            overflow-y: auto;
        }
        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
        }
    </style>
</head>

<body>

<div class="container-fluid">
    <div class="row">
        <!-- القائمة الجانبية -->
        <div class="col-md-2 sidebar">
            <h3 class="text-white mb-4">اختبار Procolis</h3>
            <nav>
                <a href="index.php"><i class="fas fa-home me-2"></i>العودة للرئيسية</a>
                <a href="settings.php"><i class="fas fa-cog me-2"></i>الإعدادات</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>تسجيل الخروج</a>
            </nav>
        </div>
        
        <!-- المحتوى الرئيسي -->
        <div class="col-md-10 content">
            <h1 class="mb-4">اختبار Procolis API</h1>
            
            <?php if($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="test-card">
                        <h3><i class="fas fa-plug text-primary"></i> اختبار الاتصال</h3>
                        <p>اختبار الاتصال بـ Procolis API للتأكد من صحة Token و Key</p>
                        <form method="post">
                            <button type="submit" name="test_connection" class="btn btn-primary">
                                <i class="fas fa-wifi"></i> اختبار الاتصال
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="test-card">
                        <h3><i class="fas fa-box text-success"></i> طلب تجريبي</h3>
                        <p>إرسال طلب تجريبي إلى Procolis لاختبار إنشاء الطلبات</p>
                        <form method="post">
                            <button type="submit" name="test_order" class="btn btn-success">
                                <i class="fas fa-plus"></i> إرسال طلب تجريبي
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="test-card">
                        <h3><i class="fas fa-money-bill text-warning"></i> اختبار التعريفة</h3>
                        <p>جلب تعريفة الشحن من Procolis</p>
                        <form method="post">
                            <button type="submit" name="test_tarification" class="btn btn-warning">
                                <i class="fas fa-dollar-sign"></i> جلب التعريفة
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <?php if($test_result): ?>
                <div class="test-card">
                    <h3><i class="fas fa-code text-info"></i> نتيجة الاختبار</h3>
                    <div class="result-box">
                        <pre><?php echo htmlspecialchars($test_result); ?></pre>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="test-card">
                <h3><i class="fas fa-info-circle text-info"></i> معلومات الإعدادات الحالية</h3>
                <div class="row">
                    <div class="col-md-6">
                        <h5>Token:</h5>
                        <code>8a24f510a69c5c8d3cfce2928fdcefe00ff6f6b5703ceef9e554ee1b6130ed29</code>
                    </div>
                    <div class="col-md-6">
                        <h5>Key:</h5>
                        <code>9ada52b8b8614dc1b890bfce58a8c9cf</code>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <h5>Chat ID:</h5>
                        <code>5480012371</code>
                    </div>
                    <div class="col-md-6">
                        <h5>URL الأساسي:</h5>
                        <code>https://procolis.com/api_v1</code>
                    </div>
                </div>
            </div>
            
            <div class="test-card">
                <h3><i class="fas fa-question-circle text-secondary"></i> كيفية التحقق من النتائج</h3>
                <ul>
                    <li><strong>اختبار الاتصال:</strong> يجب أن يعيد استجابة نجاح من Procolis</li>
                    <li><strong>الطلب التجريبي:</strong> يجب أن يظهر في لوحة تحكم Procolis</li>
                    <li><strong>التعريفة:</strong> يجب أن تعيد قائمة بأسعار الشحن</li>
                </ul>
                
                <h5>روابط مفيدة:</h5>
                <ul>
                    <li><a href="https://procolis.com" target="_blank">موقع Procolis</a></li>
                    <li><a href="settings.php">تعديل الإعدادات</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
