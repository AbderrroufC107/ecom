<?php
// Redirect to main app or serve docs
require_once __DIR__ . '/../../admin/header.php';
$plan = store_get($pdo, $current_store_id);
?>
<style>
.doc-container { font-family: 'Cairo', 'Outfit', sans-serif; padding: 24px; direction: rtl; text-align: right; color: #1b2559; max-width: 1000px; margin: 0 auto; }
.doc-card { background: rgba(255,255,255,0.95); border: 1px solid #e2e8f0; border-radius: 20px; padding: 32px; box-shadow: 0 18px 40px rgba(112,144,176,0.12); margin-bottom: 24px; }
.doc-title { font-size: 32px; font-weight: 800; margin: 0 0 8px; }
.doc-subtitle { font-size: 15px; color: #707eae; margin: 0 0 32px; }
.doc-section { margin-bottom: 28px; }
.doc-section h2 { font-size: 22px; font-weight: 800; margin: 0 0 12px; color: #4318ff; }
.doc-section h3 { font-size: 17px; font-weight: 700; margin: 16px 0 8px; }
.doc-section p, .doc-section li { font-size: 14px; line-height: 1.7; color: #334155; }
.doc-section code, .doc-code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-family: 'Consolas', 'Courier New', monospace; font-size: 13px; direction: ltr; text-align: left; display: inline-block; }
.doc-pre { background: #0f172a; color: #e2e8f0; padding: 16px; border-radius: 12px; font-family: 'Consolas', 'Courier New', monospace; font-size: 13px; direction: ltr; text-align: left; overflow-x: auto; margin: 8px 0; white-space: pre-wrap; word-break: break-all; }
.doc-pre .comment { color: #64748b; }
.doc-pre .keyword { color: #93c5fd; }
.doc-pre .string { color: #86efac; }
.doc-pre .method { color: #fbbf24; }
.doc-endpoint { background: #f8faff; border-radius: 12px; padding: 16px; margin: 12px 0; border-right: 4px solid #4318ff; }
.doc-endpoint .method { font-weight: 800; padding: 2px 8px; border-radius: 4px; font-size: 12px; }
.doc-endpoint .method-get { background: #dbeafe; color: #2563eb; }
.doc-endpoint .method-post { background: #dcfce7; color: #16a34a; }
.doc-endpoint .method-put { background: #fef3c7; color: #d97706; }
.doc-endpoint .method-delete { background: #fee2e2; color: #dc2626; }
.doc-endpoint .path { font-family: monospace; font-size: 14px; direction: ltr; text-align: left; }
.doc-table { width: 100%; border-collapse: collapse; margin: 12px 0; }
.doc-table th { text-align: right; padding: 10px 8px; font-size: 12px; color: #a3aed1; font-weight: 700; border-bottom: 2px solid #f4f7fe; }
.doc-table td { padding: 10px 8px; font-size: 13px; border-bottom: 1px solid #f4f7fe; }
.doc-table tr:hover td { background: #f8faff; }
.doc-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.doc-badge-green { background: #dcfce7; color: #166534; }
.doc-badge-blue { background: #dbeafe; color: #1e40af; }
.doc-badge-yellow { background: #fef3c7; color: #92400e; }
.doc-badge-red { background: #fee2e2; color: #991b1b; }
</style>

<div class="doc-container">
    <div class="doc-card">
        <h1 class="doc-title"><i class="fa fa-book" style="color: #4318ff;"></i> توثيق API</h1>
        <p class="doc-subtitle">Restful API لمتجر <?php echo htmlspecialchars($plan['name'] ?? 'Ecom', ENT_QUOTES, 'UTF-8'); ?></p>

        <div class="doc-section">
            <h2>المقدمة</h2>
            <p>توفر API RESTful للمتجر إمكانية إدارة الطلبات والمنتجات والعملاء والتحليلات برمجياً. جميع الاستجابات بتنسيق JSON.</p>
            <div style="display: flex; gap: 12px; flex-wrap: wrap; margin: 16px 0;">
                <span class="doc-badge doc-badge-blue">الإصدار: v1</span>
                <span class="doc-badge doc-badge-green">المسار الأساسي: <code>/api/v1/</code></span>
                <span class="doc-badge doc-badge-yellow">التنسيق: JSON</span>
                <span class="doc-badge doc-badge-red">المصادقة: مطلوبة</span>
            </div>
        </div>

        <div class="doc-section">
            <h2>المصادقة</h2>
            <p>تستخدم API مفتاح API للمصادقة. يمكن إدارة المفاتيح من لوحة التحكم: <a href="../admin/api-keys.php" style="color: #4318ff; font-weight: 700;">مفاتيح API</a>.</p>
            <h3>طرق إرسال المفتاح</h3>
            <p>يمكن إرسال مفتاح API بإحدى الطرق التالية:</p>
            <div class="doc-pre"><span class="comment">// 1. Bearer Token (مُوصى به)</span>
Authorization: Bearer {api_key}

<span class="comment">// 2. X-API-Key Header</span>
X-API-Key: {api_key}

<span class="comment">// 3. Query Parameter (للاستفسارات البسيطة)</span>
GET /api/v1/products?api_key={api_key}</div>
        </div>

        <div class="doc-section">
            <h2>الحدود (Rate Limits)</h2>
            <p>يختلف الحد اليومي حسب الخطة:</p>
            <table class="doc-table">
                <thead><tr><th>الخطة</th><th>الحد اليومي</th></tr></thead>
                <tbody>
                    <tr><td>Starter</td><td>1,000 طلب/يوم</td></tr>
                    <tr><td>Pro</td><td>10,000 طلب/يوم</td></tr>
                    <tr><td>Enterprise</td><td>غير محدود</td></tr>
                </tbody>
            </table>
            <p>تُرجع جميع الاستجابات الهيدرات التالية للحدود:</p>
            <div class="doc-pre">X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 995
X-RateLimit-Reset: 1688123456</div>
        </div>

        <div class="doc-section">
            <h2>الأخطاء</h2>
            <p>تستخدم API رموز HTTP القياسية. جميع الأخطاء تُرجع:</p>
            <div class="doc-pre">{ "success": false, "error": "رسالة الخطأ" }</div>
            <table class="doc-table">
                <thead><tr><th>الرمز</th><th>المعنى</th></tr></thead>
                <tbody>
                    <tr><td>200</td><td>نجاح</td></tr>
                    <tr><td>201</td><td>تم الإنشاء</td></tr>
                    <tr><td>400</td><td>طلب غير صحيح</td></tr>
                    <tr><td>401</td><td>مصادقة مطلوبة</td></tr>
                    <tr><td>403</td><td>صلاحية غير كافية</td></tr>
                    <tr><td>404</td><td>غير موجود</td></tr>
                    <tr><td>429</td><td>تجاوز الحد المسموح</td></tr>
                    <tr><td>500</td><td>خطأ في الخادم</td></tr>
                </tbody>
            </table>
        </div>

        <div class="doc-section">
            <h2>الصلاحيات (Permissions)</h2>
            <p>لكل مفتاح API صلاحيات محددة تتحكم في الموارد المسموح الوصول إليها.</p>
            <table class="doc-table">
                <thead><tr><th>الصلاحية</th><th>الوصف</th></tr></thead>
                <tbody>
                    <tr><td>orders.read</td><td>عرض الطلبات</td></tr>
                    <tr><td>orders.write</td><td>إنشاء وتحديث الطلبات</td></tr>
                    <tr><td>products.read</td><td>عرض المنتجات</td></tr>
                    <tr><td>products.write</td><td>إنشاء وتحديث المنتجات</td></tr>
                    <tr><td>customers.read</td><td>عرض العملاء</td></tr>
                    <tr><td>customers.write</td><td>إنشاء وتحديث العملاء</td></tr>
                    <tr><td>analytics.read</td><td>عرض التحليلات</td></tr>
                    <tr><td>employees.read</td><td>عرض الموظفين</td></tr>
                </tbody>
            </table>
        </div>

        <div class="doc-section">
            <h2>الاستعلام (Query Parameters)</h2>
            <p>تدعم نقاط النهاية التي تُرجع قوائم معاملات التصفية والترقيم التالية:</p>
            <table class="doc-table">
                <thead><tr><th>المعامل</th><th>النوع</th><th>الوصف</th></tr></thead>
                <tbody>
                    <tr><td>page</td><td>integer</td><td>رقم الصفحة (يبدأ من 1)</td></tr>
                    <tr><td>limit</td><td>integer</td><td>عدد العناصر في الصفحة (أقصى 100)</td></tr>
                    <tr><td>search</td><td>string</td><td>نص البحث</td></tr>
                    <tr><td>status</td><td>string</td><td>الفلترة حسب الحالة</td></tr>
                    <tr><td>from</td><td>date</td><td>بداية النطاق الزمني (Y-m-d)</td></tr>
                    <tr><td>to</td><td>date</td><td>نهاية النطاق الزمني (Y-m-d)</td></tr>
                    <tr><td>sort</td><td>string</td><td>الترتيب (مثال: created_at DESC)</td></tr>
                </tbody>
            </table>
        </div>

        <div class="doc-section">
            <h2>نقاط النهاية — Endpoints</h2>

            <!-- Orders -->
            <h3><i class="fa fa-shopping-cart" style="color: #4318ff; margin-left: 6px;"></i> الطلبات (Orders)</h3>

            <div class="doc-endpoint">
                <span class="method method-get">GET</span>
                <span class="path">/api/v1/orders</span>
                <p style="margin: 8px 0 0; font-size: 13px;">الحصول على قائمة الطلبات. يتطلب صلاحية <code>orders.read</code>.</p>
            </div>
            <div class="doc-pre">GET /api/v1/orders?page=1&limit=20&status=pending&from=2026-01-01&to=2026-06-30
Authorization: Bearer {api_key}

<span class="comment">// Response</span>
{
  "success": true,
  "data": [
    {
      "id": 1,
      "order_number": "ORD-001",
      "customer_name": "أحمد محمد",
      "total": 250.00,
      "status": "pending",
      "created_at": "2026-06-01 12:00:00"
    }
  ],
  "pagination": { "page": 1, "limit": 20, "total": 45, "pages": 3 }
}</div>

            <div class="doc-endpoint">
                <span class="method method-post">POST</span>
                <span class="path">/api/v1/orders</span>
                <p style="margin: 8px 0 0; font-size: 13px;">إنشاء طلب جديد. يتطلب صلاحية <code>orders.write</code>.</p>
            </div>
            <div class="doc-pre">POST /api/v1/orders
Authorization: Bearer {api_key}
Content-Type: application/json

{
  "customer_id": 5,
  "items": [
    { "product_id": 10, "quantity": 2, "price": 50.00 }
  ],
  "shipping_address": "العنوان الكامل",
  "payment_method": "credit_card",
  "notes": "ملاحظات الطلب"
}

<span class="comment">// Response</span>
{
  "success": true,
  "data": {
    "id": 46,
    "order_number": "ORD-046",
    "total": 100.00,
    "status": "pending",
    "created_at": "2026-06-03 14:30:00"
  }
}</div>

            <div class="doc-endpoint">
                <span class="method method-get">GET</span>
                <span class="path">/api/v1/orders/{id}</span>
                <p style="margin: 8px 0 0; font-size: 13px;">الحصول على طلب محدد. يتطلب صلاحية <code>orders.read</code>.</p>
            </div>

            <div class="doc-endpoint">
                <span class="method method-put">PUT</span>
                <span class="path">/api/v1/orders/{id}</span>
                <p style="margin: 8px 0 0; font-size: 13px;">تحديث طلب (تغيير الحالة). يتطلب صلاحية <code>orders.write</code>.</p>
            </div>

            <!-- Products -->
            <h3 style="margin-top: 28px;"><i class="fa fa-cube" style="color: #05cd99; margin-left: 6px;"></i> المنتجات (Products)</h3>

            <div class="doc-endpoint">
                <span class="method method-get">GET</span>
                <span class="path">/api/v1/products</span>
                <p style="margin: 8px 0 0; font-size: 13px;">قائمة المنتجات. يتطلب صلاحية <code>products.read</code>.</p>
            </div>
            <div class="doc-endpoint">
                <span class="method method-post">POST</span>
                <span class="path">/api/v1/products</span>
                <p style="margin: 8px 0 0; font-size: 13px;">إنشاء منتج جديد. يتطلب صلاحية <code>products.write</code>.</p>
            </div>
            <div class="doc-endpoint">
                <span class="method method-get">GET</span>
                <span class="path">/api/v1/products/{id}</span>
                <p style="margin: 8px 0 0; font-size: 13px;">منتج محدد. يتطلب صلاحية <code>products.read</code>.</p>
            </div>
            <div class="doc-endpoint">
                <span class="method method-put">PUT</span>
                <span class="path">/api/v1/products/{id}</span>
                <p style="margin: 8px 0 0; font-size: 13px;">تحديث منتج. يتطلب صلاحية <code>products.write</code>.</p>
            </div>

            <!-- Customers -->
            <h3 style="margin-top: 28px;"><i class="fa fa-users" style="color: #ff9800; margin-left: 6px;"></i> العملاء (Customers)</h3>

            <div class="doc-endpoint">
                <span class="method method-get">GET</span>
                <span class="path">/api/v1/customers</span>
                <p style="margin: 8px 0 0; font-size: 13px;">قائمة العملاء. يتطلب صلاحية <code>customers.read</code>.</p>
            </div>
            <div class="doc-endpoint">
                <span class="method method-post">POST</span>
                <span class="path">/api/v1/customers</span>
                <p style="margin: 8px 0 0; font-size: 13px;">إنشاء عميل. يتطلب صلاحية <code>customers.write</code>.</p>
            </div>
            <div class="doc-endpoint">
                <span class="method method-get">GET</span>
                <span class="path">/api/v1/customers/{id}</span>
                <p style="margin: 8px 0 0; font-size: 13px;">عميل محدد. يتطلب صلاحية <code>customers.read</code>.</p>
            </div>

            <!-- Analytics -->
            <h3 style="margin-top: 28px;"><i class="fa fa-bar-chart" style="color: #f44336; margin-left: 6px;"></i> التحليلات (Analytics)</h3>

            <div class="doc-endpoint">
                <span class="method method-get">GET</span>
                <span class="path">/api/v1/analytics</span>
                <p style="margin: 8px 0 0; font-size: 13px;">الحصول على ملخص التحليلات (إجمالي الطلبات، الإيرادات، العملاء الجدد، إلخ). يتطلب صلاحية <code>analytics.read</code>.</p>
            </div>
            <div class="doc-pre">GET /api/v1/analytics?from=2026-01-01&to=2026-06-30
Authorization: Bearer {api_key}

<span class="comment">// Response</span>
{
  "success": true,
  "data": {
    "total_orders": 1450,
    "total_revenue": 125000.00,
    "new_customers": 89,
    "avg_order_value": 86.21,
    "top_products": [
      { "name": "منتج أ", "total": 25000.00, "count": 300 }
    ],
    "orders_by_status": {
      "pending": 12,
      "confirmed": 45,
      "completed": 1350,
      "cancelled": 43
    }
  }
}</div>

            <!-- Recovery -->
            <h3 style="margin-top: 28px;"><i class="fa fa-refresh" style="color: #05cd99;"></i> الاسترداد (Recovery)</h3>
            <div class="doc-endpoint">
                <span class="method method-get">GET</span>
                <span class="path">/api/v1/recovery</span>
                <p style="margin: 8px 0 0; font-size: 13px;">بيانات الاسترداد والتحصيل. يتطلب صلاحية <code>orders.read</code>.</p>
            </div>

            <!-- Performance -->
            <h3 style="margin-top: 28px;"><i class="fa fa-tachometer" style="color: #4318ff;"></i> الأداء (Performance)</h3>
            <div class="doc-endpoint">
                <span class="method method-get">GET</span>
                <span class="path">/api/v1/performance</span>
                <p style="margin: 8px 0 0; font-size: 13px;">مؤشرات أداء فريق المندوبين. يتطلب صلاحية <code>employees.read</code>.</p>
            </div>

            <!-- Risk -->
            <h3 style="margin-top: 28px;"><i class="fa fa-shield" style="color: #f44336;"></i> المخاطر (Risk)</h3>
            <div class="doc-endpoint">
                <span class="method method-get">GET</span>
                <span class="path">/api/v1/risk</span>
                <p style="margin: 8px 0 0; font-size: 13px;">حالات المخاطر والاحتيال المحتملة. يتطلب صلاحية <code>orders.read</code>.</p>
            </div>
        </div>

        <div class="doc-section">
            <h2>Webhooks</h2>
            <p>يمكن إعداد Webhooks لإشعار تلقائي عند وقوع أحداث محددة. تُرسل الطلبات إلى الرابط المسجل مع توقيع HMAC-SHA256 للتحقق.</p>

            <h3>الأحداث المدعومة</h3>
            <table class="doc-table">
                <thead><tr><th>الحدث</th><th>الوصف</th></tr></thead>
                <tbody>
                    <tr><td>order.created</td><td>عند إنشاء طلب جديد</td></tr>
                    <tr><td>order.confirmed</td><td>عند تأكيد الطلب</td></tr>
                    <tr><td>order.cancelled</td><td>عند إلغاء الطلب</td></tr>
                    <tr><td>order.completed</td><td>عند اكتمال الطلب</td></tr>
                    <tr><td>order.returned</td><td>عند إرجاع الطلب</td></tr>
                    <tr><td>risk.changed</td><td>عند تغير حالة المخاطر</td></tr>
                    <tr><td>recovery.created</td><td>عند إنشاء محاولة استرداد</td></tr>
                    <tr><td>employee.created</td><td>عند إضافة مندوب جديد</td></tr>
                </tbody>
            </table>

            <h3>تنسيق الحمولة (Payload)</h3>
            <div class="doc-pre">POST {webhook_url}
Content-Type: application/json
X-Webhook-Signature: sha256=...
X-Webhook-Event: order.created

{
  "event": "order.created",
  "store_id": 1,
  "timestamp": "2026-06-03T14:30:00Z",
  "data": { ... }
}</div>

            <h3>التحقق من التوقيع</h3>
            <div class="doc-pre"><span class="comment">// PHP</span>
$payload = file_get_contents('php://input');
$sig = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'];
$expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
if (hash_equals($expected, $sig)) { <span class="comment">/* صحيح */</span> }

<span class="comment">// Node.js</span>
const crypto = require('crypto');
const expected = 'sha256=' + crypto.createHmac('sha256', secret).update(body).digest('hex');
if (crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(sig))) { <span class="comment">/* صحيح */</span> }</div>
        </div>

        <div class="doc-section">
            <h2>أمثلة استخدام</h2>

            <h3>جلب جميع الطلبات المعلقة (cURL)</h3>
            <div class="doc-pre">curl -H "Authorization: Bearer YOUR_API_KEY" \
  "https://example.com/api/v1/orders?status=pending&limit=10"</div>

            <h3>إنشاء منتج (PHP)</h3>
            <div class="doc-pre">$ch = curl_init('https://example.com/api/v1/products');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => [
    'Authorization: Bearer YOUR_API_KEY',
    'Content-Type: application/json',
  ],
  CURLOPT_POSTFIELDS => json_encode([
    'name' => 'منتج جديد',
    'price' => 99.99,
    'stock' => 100,
  ]),
]);
$response = curl_exec($ch);</div>

            <h3>جلب تحليلات (Python)</h3>
            <div class="doc-pre">import requests

headers = {"Authorization": "Bearer YOUR_API_KEY"}
params = {"from": "2026-01-01", "to": "2026-06-30"}
r = requests.get("https://example.com/api/v1/analytics",
                 headers=headers, params=params)
print(r.json())</div>

            <h3>إعداد Webhook (Node.js)</h3>
            <div class="doc-pre">const express = require('express');
const crypto = require('crypto');
const app = express();

app.post('/webhook', express.raw({type: 'application/json'}), (req, res) => {
  const secret = 'YOUR_WEBHOOK_SECRET';
  const sig = req.headers['x-webhook-signature'];
  const expected = 'sha256=' + crypto
    .createHmac('sha256', secret)
    .update(req.body)
    .digest('hex');

  if (!crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(sig))) {
    return res.status(401).send('Invalid signature');
  }
  const event = req.headers['x-webhook-event'];
  const payload = JSON.parse(req.body);
  console.log('Received webhook:', event, payload);
  res.status(200).send('OK');
});

app.listen(3000);</div>
        </div>

        <div class="doc-section" style="background: #f8faff; border-radius: 16px; padding: 24px;">
            <h3 style="margin: 0;"><i class="fa fa-key" style="color: #4318ff;"></i> ابدأ الآن</h3>
            <p style="font-size: 14px; color: #64748b;">توجه إلى <a href="../admin/api-keys.php" style="color: #4318ff; font-weight: 700;">إدارة مفاتيح API</a> لإنشاء مفتاح والبدء في استخدام API.</p>
            <p style="font-size: 14px; color: #64748b;">أو قم بإعداد <a href="../admin/integrations.php" style="color: #ff9800; font-weight: 700;">Webhooks</a> للإشعارات التلقائية.</p>
        </div>

        <div style="text-align: center; padding: 16px; color: #a3aed1; font-size: 12px;">
            API v1 — <?php echo date('Y'); ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../admin/footer.php'; ?>
