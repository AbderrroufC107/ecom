<?php require_once('header.php'); ?>
<!-- fetching row banner login -->
<?php
$statement = $pdo->prepare("SELECT * FROM tbl_settings WHERE id=1");
$statement->execute();
$result = $statement->fetchAll(PDO::FETCH_ASSOC);                            
foreach ($result as $row) {
    $banner_login = $row['banner_login'];
}
$banner_login_url = trim((string)get_front_image_url($banner_login));
?>
<!-- login form -->
<?php
if(isset($_POST['form1'])) {
    try {
        require_once __DIR__ . '/inc/rate-limiter.php';
        $limiter = new PublicRateLimiter($pdo);
        $limiter->check('customer_login', 5, 300); // 5 attempts per 5 mins

        if(empty($_POST['cust_phone']) || empty($_POST['cust_password'])) {
            throw new Exception("يرجى ملء جميع الحقول المطلوبة");
        }
        
        // التحقق من رقم الهاتف
        if(!preg_match("/^0[567][0-9]{8}$/", $_POST['cust_phone'])) {
            throw new Exception("رقم الهاتف غير صحيح");
        }
        
        $statement = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_phone=? AND cust_status=1");
        $statement->execute(array($_POST['cust_phone']));
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        
        if($statement->rowCount() == 0) {
            throw new Exception("رقم الهاتف غير مسجل أو الحساب غير مفعل");
        } else {
            $row = $result[0];
            $row_password = $row['cust_password'];
            
            if (password_verify($_POST['cust_password'], $row_password)) {
                // Bcrypt match
            } elseif (strlen($row_password) === 32 && md5($_POST['cust_password']) === $row_password) {
                // Legacy MD5 match — rehash and update
                $bcrypt_hash = password_hash($_POST['cust_password'], PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE tbl_customer SET cust_password = ? WHERE id = ?");
                $update->execute([$bcrypt_hash, $row['id']]);
                $row['cust_password'] = $bcrypt_hash; // update in memory
            } else {
                throw new Exception("كلمة المرور غير صحيحة");
            }
            
            session_regenerate_id(true);
            $_SESSION['customer'] = $row;
            header("location: ".BASE_URL."dashboard.php");
            exit;
        }
    } catch(Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<div class="page-banner" style="background-color:#444;<?php if ($banner_login_url !== ''): ?>background-image: url('<?php echo htmlspecialchars($banner_login_url, ENT_QUOTES, 'UTF-8'); ?>');<?php endif; ?>">
    <div class="inner">
        <h1><?php echo LANG_VALUE_10; ?></h1>
    </div>
</div>

<div class="page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="user-content">
                    <h2 class="text-center mb-4">تسجيل الدخول</h2>


                    <form action="" method="post">
                        <div class="form-group">
                            <label>رقم الهاتف *</label>
                            <input type="tel" class="form-control" name="cust_phone" placeholder="مثال: 0555123456" value="<?php if(isset($_POST['cust_phone'])) echo htmlspecialchars($_POST['cust_phone'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="form-group">
                            <label>كلمة المرور *</label>
                            <input type="password" class="form-control" name="cust_password">
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-block" name="form1">تسجيل الدخول</button>
                        </div>
                        <div class="text-center">
                            ليس لديك حساب؟ <a href="registration.php">إنشاء حساب جديد</a>
                        </div>
                    </form>
                </div>                
            </div>
        </div>
    </div>
</div>

<style>
.page {
    background: #f8f9fa;
    padding: 40px 0;
    min-height: 100vh;
}

.page-banner {
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    padding: 80px 0;
    position: relative;
    margin-bottom: 40px;
}

.page-banner:before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
}

.page-banner .inner {
    position: relative;
    z-index: 1;
    text-align: center;
    color: #fff;
}

.page-banner h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
}

.user-content {
    background: #fff;
    padding: 40px;
    border-radius: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    margin: 20px 0;
    border: 1px solid #eee;
}

.user-content h2 {
    color: #333;
    font-size: 2.2rem;
    font-weight: 700;
    margin-bottom: 30px;
    text-align: center;
    position: relative;
    padding-bottom: 15px;
}

.user-content h2:after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 3px;
    background: #7c3aed;
    border-radius: 3px;
}

.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #555;
    font-weight: 600;
    font-size: 0.95rem;
}

.form-control {
    height: 50px;
    border: 2px solid #eee;
    border-radius: 12px;
    padding: 10px 15px;
    font-size: 1rem;
    transition: all 0.3s;
}

.form-control:focus {
    border-color: #7c3aed;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
}

.btn-primary {
    background: #7c3aed;
    border: none;
    color: #fff;
    font-weight: 600;
    padding: 15px 30px;
    border-radius: 12px;
    font-size: 1.1rem;
    width: 100%;
    transition: all 0.3s;
}

.btn-primary:hover {
    background: #6d28d9;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(124, 58, 237, 0.2);
}

.alert {
    border-radius: 12px;
    padding: 15px 20px;
    margin-bottom: 25px;
    border: none;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
}

.alert-danger {
    background: #fee2e2;
    color: #dc2626;
}

.text-center a {
    color: #7c3aed;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
}

.text-center a:hover {
    color: #6d28d9;
    text-decoration: underline;
}

@media (max-width: 768px) {
    .page-banner {
        padding: 60px 0;
    }
    
    .page-banner h1 {
        font-size: 2rem;
    }
    
    .user-content {
        padding: 25px;
        margin: 15px;
    }
    
    .user-content h2 {
        font-size: 1.8rem;
    }
    
    .form-control {
        height: 45px;
    }
    
    .btn-primary {
        padding: 12px 25px;
        font-size: 1rem;
    }
}
</style>

<?php require_once('footer.php'); ?>
