<?php
require_once('header.php');

if (isset($_POST['form1'])) {
    try {
        require_once __DIR__ . '/inc/rate-limiter.php';
        $limiter = new PublicRateLimiter($pdo);
        $limiter->check('customer_register', 3, 600); // 3 attempts per 10 mins

        if(empty($_POST['cust_name']) || empty($_POST['cust_phone']) || 
           empty($_POST['wilaya']) || empty($_POST['commune']) || 
           empty($_POST['cust_address']) || empty($_POST['cust_password']) || 
           empty($_POST['cust_password_confirm'])) {
            throw new Exception("يرجى ملء جميع الحقول المطلوبة");
        }

        if($_POST['cust_password'] != $_POST['cust_password_confirm']) {
            throw new Exception("كلمتا المرور غير متطابقتين");
        }

        // التحقق من رقم الهاتف
        if(!preg_match("/^0[567][0-9]{8}$/", $_POST['cust_phone'])) {
            throw new Exception("رقم الهاتف غير صحيح");
        }

        // التحقق من عدم وجود رقم الهاتف مسبقاً
        $statement = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_phone=?");
        $statement->execute(array($_POST['cust_phone']));
        $total = $statement->rowCount();
        if($total) {
            throw new Exception("رقم الهاتف مسجل مسبقاً");
        }

        // إنشاء الحساب
        $statement = $pdo->prepare("INSERT INTO tbl_customer (
            cust_name,
            cust_phone,
            wilaya,
            commune,
            cust_address,
            cust_password,
            cust_status
        ) VALUES (?,?,?,?,?,?,?)");
        
        $statement->execute(array(
            strip_tags($_POST['cust_name']),
            strip_tags($_POST['cust_phone']),
            strip_tags($_POST['wilaya']),
            strip_tags($_POST['commune']),
            strip_tags($_POST['cust_address']),
            password_hash($_POST['cust_password'], PASSWORD_DEFAULT),
            1
        ));

        $_SESSION['success_message'] = "تم إنشاء حسابك بنجاح. يمكنك الآن تسجيل الدخول.";
        header("location: ".BASE_URL."login.php");
        exit;

    } catch(Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<div class="page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="user-content">
                    <h2 class="text-center mb-4">إنشاء حساب جديد</h2>
                    
                   

                    <form action="" method="post">
                        <div class="form-group">
                            <label>الاسم الكامل *</label>
                            <input type="text" class="form-control" name="cust_name" value="<?php if(isset($_POST['cust_name'])) echo htmlspecialchars($_POST['cust_name'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>رقم الهاتف *</label>
                            <input type="tel" class="form-control" name="cust_phone" placeholder="مثال: 0555123456" value="<?php if(isset($_POST['cust_phone'])) echo htmlspecialchars($_POST['cust_phone'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>الولاية *</label>
                                    <select name="wilaya" id="wilaya" class="form-control" required>
                                        <option value="">اختر الولاية</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>البلدية *</label>
                                    <select name="commune" id="commune" class="form-control" required>
                                        <option value="">اختر البلدية</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>العنوان *</label>
                            <textarea name="cust_address" class="form-control" rows="3" placeholder="أدخل عنوانك بالتفصيل"><?php if(isset($_POST['cust_address'])) echo htmlspecialchars($_POST['cust_address'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>كلمة المرور *</label>
                            <input type="password" class="form-control" name="cust_password">
                        </div>

                        <div class="form-group">
                            <label>تأكيد كلمة المرور *</label>
                            <input type="password" class="form-control" name="cust_password_confirm">
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-block" name="form1">إنشاء الحساب</button>
                        </div>

                        <div class="text-center">
                            لديك حساب بالفعل؟ <a href="login.php">تسجيل الدخول</a>
                        </div>
                    </form>
                </div>                
            </div>
        </div>
    </div>
</div>

<script src="assets/js/wilayas-communes.js"></script>
<script>
// تهيئة قائمة الولايات
const wilayaSelect = document.getElementById('wilaya');
wilayasList.forEach(wilaya => {
    const option = document.createElement('option');
    option.value = wilaya.name;
    option.textContent = wilaya.name;
    wilayaSelect.appendChild(option);
});

// تحديث قائمة البلديات عند اختيار الولاية
wilayaSelect.addEventListener('change', function() {
    const communeSelect = document.getElementById('commune');
    communeSelect.innerHTML = '<option value="">اختر البلدية</option>';
    
    if(this.value) {
        const wilayaId = wilayasList.find(w => w.name === this.value).id;
        const communes = communesList[wilayaId];
        communes.forEach(commune => {
            const option = document.createElement('option');
            option.value = commune;
            option.textContent = commune;
            communeSelect.appendChild(option);
        });
    }
});

// التحقق من كلمة المرور
const password = document.querySelector('input[name="cust_password"]');
const confirmPassword = document.querySelector('input[name="cust_password_confirm"]');
const phoneInput = document.querySelector('input[name="cust_phone"]');

// إضافة عناصر لعرض رسائل الخطأ
const passwordError = document.createElement('div');
passwordError.className = 'text-danger mt-1';
passwordError.style.fontSize = '0.9rem';
password.parentNode.appendChild(passwordError);

const phoneError = document.createElement('div');
phoneError.className = 'text-danger mt-1';
phoneError.style.fontSize = '0.9rem';
phoneInput.parentNode.appendChild(phoneError);

// التحقق من قوة كلمة المرور
function checkPasswordStrength(password) {
    let strength = 0;
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]/)) strength++;
    if (password.match(/[A-Z]/)) strength++;
    if (password.match(/[0-9]/)) strength++;
    if (password.match(/[^a-zA-Z0-9]/)) strength++;
    return strength;
}

// تحديث رسالة قوة كلمة المرور
password.addEventListener('input', function() {
    const strength = checkPasswordStrength(this.value);
    if (this.value.length > 0) {
        if (strength < 3) {
            passwordError.textContent = 'كلمة المرور ضعيفة. يجب أن تحتوي على 8 أحرف على الأقل، وحروف كبيرة وصغيرة، وأرقام، ورموز خاصة';
            passwordError.style.color = '#dc3545';
        } else if (strength < 5) {
            passwordError.textContent = 'كلمة المرور متوسطة. يمكنك جعلها أقوى بإضافة المزيد من الأحرف والرموز';
            passwordError.style.color = '#ffc107';
        } else {
            passwordError.textContent = 'كلمة المرور قوية';
            passwordError.style.color = '#28a745';
        }
    } else {
        passwordError.textContent = '';
    }
});

// التحقق من تطابق كلمتي المرور
confirmPassword.addEventListener('input', function() {
    if (this.value !== password.value) {
        this.setCustomValidity('كلمتا المرور غير متطابقتين');
    } else {
        this.setCustomValidity('');
    }
});

// التحقق من رقم الهاتف
phoneInput.addEventListener('input', function() {
    const phoneRegex = /^0[567][0-9]{8}$/;
    if (!phoneRegex.test(this.value)) {
        phoneError.textContent = 'رقم الهاتف غير صحيح. يجب أن يبدأ بـ 05 أو 06 أو 07 ويتكون من 10 أرقام';
        this.setCustomValidity('رقم الهاتف غير صحيح');
    } else {
        phoneError.textContent = '';
        this.setCustomValidity('');
    }
});

// استعادة القيم المحددة سابقاً بعد إرسال النموذج
<?php if(isset($_POST['wilaya']) && isset($_POST['commune'])): ?>
window.addEventListener('load', function() {
    wilayaSelect.value = <?php echo json_encode($_POST['wilaya']); ?>;
    wilayaSelect.dispatchEvent(new Event('change'));
    setTimeout(() => {
        document.getElementById('commune').value = <?php echo json_encode($_POST['commune']); ?>;
    }, 100);
});
<?php endif; ?>
</script>

<style>
.page {
    background: #f8f9fa;
    padding: 40px 0;
    min-height: 100vh;
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

textarea.form-control {
    height: auto;
    min-height: 120px;
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

/* تنسيقات إضافية لرسائل الخطأ */
.text-danger {
    color: #dc3545;
    font-size: 0.9rem;
    margin-top: 5px;
    display: block;
}

.form-control.is-invalid {
    border-color: #dc3545;
    padding-right: calc(1.5em + 0.75rem);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='none' stroke='%23dc3545' viewBox='0 0 12 12'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.form-control.is-valid {
    border-color: #28a745;
    padding-right: calc(1.5em + 0.75rem);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8' viewBox='0 0 8 8'%3e%3cpath fill='%2328a745' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}
</style>

<?php require_once('footer.php'); ?>