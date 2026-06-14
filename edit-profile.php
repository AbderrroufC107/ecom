<?php require_once('header.php'); ?>

<?php
if(!isset($_SESSION['customer'])) {
    header('location: login.php');
    exit;
}

$error_message = '';
$success_message = '';

if(isset($_POST['form1'])) {
    $valid = 1;

    if(empty($_POST['cust_name'])) {
        $valid = 0;
        $error_message .= 'الاسم الكامل مطلوب<br>';
    }

    if(empty($_POST['cust_phone'])) {
        $valid = 0;
        $error_message .= 'رقم الهاتف مطلوب<br>';
    }

    if(empty($_POST['wilaya'])) {
        $valid = 0;
        $error_message .= 'الولاية مطلوبة<br>';
    }

    if(empty($_POST['commune'])) {
        $valid = 0;
        $error_message .= 'البلدية مطلوبة<br>';
    }

    if(empty($_POST['cust_address'])) {
        $valid = 0;
        $error_message .= 'العنوان مطلوب<br>';
    }

    if($valid == 1) {
        $statement = $pdo->prepare("UPDATE tbl_customer SET 
            cust_name=?,
            cust_phone=?,
            wilaya=?,
            commune=?,
            cust_address=?
            WHERE id=?");
        
        $statement->execute(array(
            $_POST['cust_name'],
            $_POST['cust_phone'],
            $_POST['wilaya'],
            $_POST['commune'],
            $_POST['cust_address'],
            $_SESSION['customer']['id']
        ));

        $success_message = 'تم تحديث معلوماتك بنجاح!';
        
        // تحديث معلومات الجلسة
        $_SESSION['customer']['cust_name'] = $_POST['cust_name'];
        $_SESSION['customer']['cust_phone'] = $_POST['cust_phone'];
        $_SESSION['customer']['wilaya'] = $_POST['wilaya'];
        $_SESSION['customer']['commune'] = $_POST['commune'];
        $_SESSION['customer']['cust_address'] = $_POST['cust_address'];
    }
}
?>

<div class="page">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="user-content">
                    <h2 class="text-center mb-4">تعديل معلوماتي</h2>
                    
                    <?php if($error_message): ?>
                        <div class="alert alert-danger">
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>

                    <?php if($success_message): ?>
                        <div class="alert alert-success">
                            <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="list-group">
                                <a href="dashboard.php" class="list-group-item">معلوماتي</a>
                                <a href="customer-order.php" class="list-group-item">طلباتي</a>
                                <a href="edit-profile.php" class="list-group-item active">تعديل معلوماتي</a>
                                <a href="logout.php" class="list-group-item">تسجيل الخروج</a>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <form action="" method="post">
                                <div class="form-group">
                                    <label for="">الاسم الكامل *</label>
                                    <input type="text" class="form-control" name="cust_name" value="<?php echo $_SESSION['customer']['cust_name']; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="cust_phone">رقم الهاتف <span>*</span></label>
                                    <input type="text" name="cust_phone" class="form-control" value="<?php echo $_SESSION['customer']['cust_phone']; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="">الولاية *</label>
                                    <select name="wilaya" id="wilaya" class="form-control" required>
                                        <option value="">اختر الولاية</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="">البلدية *</label>
                                    <select name="commune" id="commune" class="form-control" required>
                                        <option value="">اختر البلدية</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="">العنوان *</label>
                                    <textarea name="cust_address" class="form-control" rows="3"><?php echo $_SESSION['customer']['cust_address']; ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary" name="form1">تحديث المعلومات</button>
                            </form>
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

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    font-weight: 600;
    margin-bottom: 8px;
    color: #333;
}

.form-control {
    height: 45px;
    border: 2px solid #eee;
    border-radius: 8px;
    padding: 10px 15px;
    font-size: 1rem;
    transition: all 0.3s;
}

.form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}

textarea.form-control {
    height: auto;
}

.btn-primary {
    background: #007bff;
    border: none;
    padding: 12px 30px;
    font-size: 1.1rem;
    font-weight: 600;
    border-radius: 8px;
    transition: all 0.3s;
}

.btn-primary:hover {
    background: #0056b3;
    transform: translateY(-2px);
}

.alert {
    border-radius: 8px;
    padding: 15px 20px;
    margin-bottom: 20px;
    border: none;
}

.alert-success {
    background: #d4edda;
    color: #155724;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
}

@media (max-width: 768px) {
    .user-content {
        padding: 20px;
        margin: 20px 0;
    }
    
    .form-control {
        height: 40px;
        font-size: 0.9rem;
    }
    
    .btn-primary {
        width: 100%;
        padding: 10px 20px;
        font-size: 1rem;
    }
}
</style>

<script src="assets/js/wilayas-communes.js"></script>
<script>
// تعبئة الولايات
const wilayaSelect = document.getElementById('wilaya');
wilayasList.forEach(wilaya => {
    const option = document.createElement('option');
    option.value = wilaya.name;
    option.textContent = wilaya.name;
    wilayaSelect.appendChild(option);
});

// تعيين قيمة الولاية الحالية
wilayaSelect.value = '<?php echo $_SESSION['customer']['wilaya']; ?>';

// تحديث البلديات عند تغيير الولاية
wilayaSelect.addEventListener('change', function() {
    const communeSelect = document.getElementById('commune');
    communeSelect.innerHTML = '<option value="">اختر البلدية</option>';
    const selectedWilaya = this.value.trim();
    if (selectedWilaya) {
        const wilayaId = wilayasList.find(w => w.name === selectedWilaya)?.id;
        if (wilayaId && communesList[wilayaId]) {
            communesList[wilayaId].forEach(commune => {
                const option = document.createElement('option');
                option.value = commune;
                option.textContent = commune;
                communeSelect.appendChild(option);
            });
        }
    }
});

// تحديث قائمة البلديات عند تحميل الصفحة
wilayaSelect.dispatchEvent(new Event('change'));

// تعيين قيمة البلدية الحالية
setTimeout(() => {
    document.getElementById('commune').value = '<?php echo $_SESSION['customer']['commune']; ?>';
}, 100);
</script>

<?php require_once('footer.php'); ?> 