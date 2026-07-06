<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>اختبار أزرار الإجراءات</title>
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <style>
        body { font-family: 'Cairo', sans-serif; padding: 30px; direction: rtl; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: center; }
        th { background: #f8f9fa; }
        .btn { margin: 2px; }
    </style>
</head>
<body>
    <h2>اختبار أزرار الإجراءات - جميع الصفحات</h2>

    <h3>product.php</h3>
    <table>
        <tr><th>#</th><th>المنتج</th><th>الإجراءات</th></tr>
        <tr>
            <td>1</td>
            <td>سجاد الطريق التفاعلي</td>
            <td style="white-space: nowrap;">
                <a href="#" class="btn btn-primary btn-xs"><i class="fa fa-edit"></i> تعديل</a>
                <a href="#" class="btn btn-danger btn-xs"><i class="fa fa-trash"></i> حذف</a>
            </td>
        </tr>
    </table>

    <h3>top-category.php</h3>
    <table>
        <tr><th>#</th><th>الفئة</th><th>الإجراءات</th></tr>
        <tr>
            <td>1</td>
            <td>ألعاب أطفال</td>
            <td style="white-space: nowrap;">
                <a href="#" class="btn btn-primary btn-xs"><i class="fa fa-edit"></i> تعديل</a>
                <a href="#" class="btn btn-danger btn-xs"><i class="fa fa-trash"></i> حذف</a>
            </td>
        </tr>
    </table>

    <h3>size.php</h3>
    <table>
        <tr><th>#</th><th>المقاس</th><th>الإجراءات</th></tr>
        <tr>
            <td>1</td>
            <td>XL</td>
            <td style="white-space: nowrap;">
                <a href="#" class="btn btn-primary btn-xs"><i class="fa fa-edit"></i> تعديل</a>
                <a href="#" class="btn btn-danger btn-xs"><i class="fa fa-trash"></i> حذف</a>
            </td>
        </tr>
    </table>

    <h3>color.php</h3>
    <table>
        <tr><th>#</th><th>اللون</th><th>الإجراءات</th></tr>
        <tr>
            <td>1</td>
            <td>أحمر</td>
            <td style="white-space: nowrap;">
                <a href="#" class="btn btn-primary btn-xs"><i class="fa fa-edit"></i> تعديل</a>
                <a href="#" class="btn btn-danger btn-xs"><i class="fa fa-trash"></i> حذف</a>
            </td>
        </tr>
    </table>

    <br>
    <p style="color:green;font-weight:bold;">إذا رأيت أيقونات + نصوص تعديل/حذف = الكود يعمل بشكل صحيح</p>
    <p><a href="product.php" class="btn btn-info">انتقل لصفحة المنتجات الحقيقية</a></p>
</body>
</html>
