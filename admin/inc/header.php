<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>لوحة التحكم</title>
    
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
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- القائمة الجانبية -->
        <div class="col-md-2 sidebar">
            <h3 class="text-white mb-4">لوحة التحكم</h3>
            <nav>
                <a href="index.php"><i class="fas fa-home me-2"></i>الرئيسية</a>
                <a href="products.php"><i class="fas fa-box me-2"></i>المنتجات</a>
                <a href="orders.php"><i class="fas fa-shopping-cart me-2"></i>الطلبات</a>
                <a href="users.php"><i class="fas fa-users me-2"></i>المستخدمين</a>
                <a href="settings.php"><i class="fas fa-cog me-2"></i>الإعدادات</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>تسجيل الخروج</a>
            </nav>
        </div>
        
        <!-- المحتوى الرئيسي -->
        <div class="col-md-10 content"> 