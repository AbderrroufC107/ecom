<?php
/**
 * حل شامل لإصلاح مشكلة الترميز العربي
 * قم بتشغيل هذا الملف مرة واحدة فقط
 */

// إعدادات الترميز الشاملة
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_regex_encoding('UTF-8');
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html lang='ar' dir='rtl'><head><meta charset='utf-8'><title>إصلاح الترميز</title></head><body>";
echo "<h2>بدء إصلاح شامل لمشكلة الترميز العربي...</h2>";

// الاتصال بقاعدة البيانات
$dbhost = 'localhost';
$dbname = 'boomtsvp_ecommerceweb';
$dbuser = 'root';
$dbpass = '';

try {
    $pdo = new PDO("mysql:host={$dbhost};dbname={$dbname};charset=utf8mb4", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    echo "<p style='color: green;'>✅ تم الاتصال بقاعدة البيانات بنجاح</p>";
    
    // أولاً: تغيير ترميز قاعدة البيانات
    echo "<h3>تغيير ترميز قاعدة البيانات...</h3>";
    try {
        $pdo->exec("ALTER DATABASE boomtsvp_ecommerceweb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "<p style='color: green;'>✅ تم تغيير ترميز قاعدة البيانات</p>";
    } catch (Exception $e) {
        echo "<p style='color: orange;'>⚠️ تحذير: " . $e->getMessage() . "</p>";
    }
    
    // ثانياً: تغيير ترميز الجداول
    echo "<h3>تغيير ترميز الجداول...</h3>";
    $tables = ['tbl_order', 'tbl_product', 'tbl_customer', 'tbl_language'];
    foreach ($tables as $table) {
        try {
            $pdo->exec("ALTER TABLE $table CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo "<p style='color: green;'>✅ تم تغيير ترميز جدول $table</p>";
        } catch (Exception $e) {
            echo "<p style='color: orange;'>⚠️ تحذير في جدول $table: " . $e->getMessage() . "</p>";
        }
    }
    
    // ثالثاً: إصلاح البيانات الموجودة
    echo "<h3>إصلاح البيانات الموجودة...</h3>";

    // دالة عامة لمحاولة إصلاح سلاسل النص العربية المشوهة
    function fix_ar_text($value) {
        if ($value === null || $value === '') return $value;
        $str = (string)$value;

        // إذا السلسلة بالفعل UTF-8 وبها حروف عربية، أعدها كما هي
        if (mb_detect_encoding($str, 'UTF-8', true) && preg_match('/[\x{0600}-\x{06FF}]/u', $str)) {
            return $str;
        }

        // مؤشرات تشوه شائعة
        $looks_broken = strpos($str, 'Ã') !== false || strpos($str, 'Ø') !== false || strpos($str, '�') !== false || strpos($str, 'þ') !== false;

        if ($looks_broken || !mb_detect_encoding($str, 'UTF-8', true)) {
            // جرّب ترميزات عربية أولاً
            $candidates = ['CP1256', 'Windows-1256', 'ISO-8859-6', 'Windows-1252', 'ISO-8859-1'];
            foreach ($candidates as $enc) {
                $converted = @mb_convert_encoding($str, 'UTF-8', $enc);
                if ($converted !== false && $converted !== '') {
                    $str = $converted;
                    break;
                }
            }
            // محاولة إضافية باستخدام iconv تجاه CP1256
            if (!mb_detect_encoding($str, 'UTF-8', true)) {
                $iconvTry = @iconv('CP1256', 'UTF-8//IGNORE', $str);
                if ($iconvTry !== false && $iconvTry !== '') {
                    $str = $iconvTry;
                }
            }
        }

        // إزالة الرموز المستبدلة والأحرف الشاذة
        $str = str_replace(["\xEF\xBF\xBD", '�', 'þ'], '', $str);
        $str = preg_replace('/\s{2,}/u', ' ', $str);
        return trim($str);
    }

    // دالة لفحص وجود عمود في جدول
    function table_has_column(PDO $pdo, $table, $column) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
            $stmt->execute([$table, $column]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return isset($row['c']) && intval($row['c']) > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    // إصلاح بيانات الطلبات
    // حدد الأعمدة الموجودة فعلياً
    $order_text_columns = [];
    foreach (['product_name','customer_name','wilaya','commune'] as $col) {
        if (table_has_column($pdo, 'tbl_order', $col)) { $order_text_columns[] = $col; }
    }
    $select_cols = 'id' . (count($order_text_columns) ? ', ' . implode(', ', $order_text_columns) : '');
    $stmt = $pdo->prepare("SELECT $select_cols FROM tbl_order");
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $fixed_orders = 0;
    foreach ($orders as $order) {
        $needs_fix = false;
        $fixed_data = [];
        
        // فحص كل حقل
        foreach ($order_text_columns as $field) {
            $value = isset($order[$field]) ? $order[$field] : null;
            $newVal = fix_ar_text($value);
            if ($newVal !== $value) { $needs_fix = true; }
            $fixed_data[$field] = $newVal;
        }
        
        if ($needs_fix) {
            if (!empty($order_text_columns)) {
                $set_parts = [];
                $params = [];
                foreach ($order_text_columns as $c) {
                    $set_parts[] = "$c = ?";
                    $params[] = $fixed_data[$c];
                }
                $params[] = $order['id'];
                $sql = "UPDATE tbl_order SET " . implode(', ', $set_parts) . " WHERE id = ?";
                $update_stmt = $pdo->prepare($sql);
                $update_stmt->execute($params);
            }
            $fixed_orders++;
            echo "<p>تم إصلاح الطلب رقم: " . $order['id'] . "</p>";
        }
    }
    
    echo "<p style='color: green;'>✅ تم إصلاح " . $fixed_orders . " طلب</p>";
    
    // إصلاح على مستوى قاعدة البيانات عبر تحويلات MySQL المباشرة للحالات المتبقية (mojibake)
    echo "<h3>محاولة تحويل SQL مباشرة للحالات المتبقية...</h3>";
    $order_text_columns_all = array_filter(['product_name','customer_name','wilaya','commune'], function($c) use ($pdo) {
        return table_has_column($pdo, 'tbl_order', $c);
    });
    foreach ($order_text_columns_all as $col) {
        // أنماط مشوهة شائعة
        $patterns = ["%Ã%","%þ%","%Ø%","%�%"];
        foreach ($patterns as $like) {
            // cp1256 → utf8mb4
            try {
                $sql = "UPDATE tbl_order SET $col = CONVERT(CAST(CONVERT($col USING cp1256) AS BINARY) USING utf8mb4) WHERE $col LIKE ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$like]);
                $count = $stmt->rowCount();
                if ($count > 0) {
                    echo "<p>تم تحويل $count صف في $col باستخدام cp1256→utf8mb4</p>";
                }
            } catch (Exception $e) {
                echo "<p style='color: orange;'>تحذير أثناء تحويل $col (cp1256): " . htmlspecialchars($e->getMessage()) . "</p>";
            }

            // latin1 → utf8mb4
            try {
                $sql = "UPDATE tbl_order SET $col = CONVERT(CAST(CONVERT($col USING latin1) AS BINARY) USING utf8mb4) WHERE $col LIKE ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$like]);
                $count = $stmt->rowCount();
                if ($count > 0) {
                    echo "<p>تم تحويل $count صف في $col باستخدام latin1→utf8mb4</p>";
                }
            } catch (Exception $e) {
                echo "<p style='color: orange;'>تحذير أثناء تحويل $col (latin1): " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    }
    
    // إصلاح بيانات المنتجات
    $stmt = $pdo->prepare("SELECT p_id, p_name, p_description FROM tbl_product");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $fixed_products = 0;
    foreach ($products as $product) {
        $needs_fix = false;
        $fixed_name = fix_ar_text($product['p_name']);
        $fixed_description = fix_ar_text($product['p_description']);
        if ($fixed_name !== $product['p_name'] || $fixed_description !== $product['p_description']) {
            $needs_fix = true;
        }
        
        if ($needs_fix) {
            $update_stmt = $pdo->prepare("UPDATE tbl_product SET p_name = ?, p_description = ? WHERE p_id = ?");
            $update_stmt->execute([$fixed_name, $fixed_description, $product['p_id']]);
            $fixed_products++;
            echo "<p>تم إصلاح المنتج رقم: " . $product['p_id'] . "</p>";
        }
    }
    
    echo "<p style='color: green;'>✅ تم إصلاح " . $fixed_products . " منتج</p>";
    
    // إصلاح بيانات العملاء
    $stmt = $pdo->prepare("SELECT cust_id, cust_name FROM tbl_customer");
    $stmt->execute();
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $fixed_customers = 0;
    foreach ($customers as $customer) {
        $newName = fix_ar_text($customer['cust_name']);
        if ($newName !== $customer['cust_name']) {
            $update_stmt = $pdo->prepare("UPDATE tbl_customer SET cust_name = ? WHERE cust_id = ?");
            $update_stmt->execute([$newName, $customer['cust_id']]);
            $fixed_customers++;
            echo "<p>تم إصلاح العميل رقم: " . $customer['cust_id'] . "</p>";
        }
    }
    
    echo "<p style='color: green;'>✅ تم إصلاح " . $fixed_customers . " عميل</p>";
    
    // اختبار النتيجة
    echo "<h3>اختبار النتيجة...</h3>";
    $test_stmt = $pdo->prepare("SELECT product_name, customer_name FROM tbl_order LIMIT 5");
    $test_stmt->execute();
    $test_data = $test_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'><th>اسم المنتج</th><th>اسم العميل</th></tr>";
    foreach ($test_data as $row) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['product_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['customer_name']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2 style='color: green; background: #d4edda; padding: 20px; border-radius: 10px;'>🎉 تم إصلاح جميع البيانات بنجاح!</h2>";
    echo "<p><strong>ملخص الإصلاحات:</strong></p>";
    echo "<ul>";
    echo "<li>الطلبات: " . $fixed_orders . "</li>";
    echo "<li>المنتجات: " . $fixed_products . "</li>";
    echo "<li>العملاء: " . $fixed_customers . "</li>";
    echo "</ul>";
    echo "<p style='color: red; font-weight: bold;'>⚠️ يرجى حذف هذا الملف من الخادم الآن!</p>";
    
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>❌ خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage() . "</h3>";
}

echo "</body></html>";
?>
