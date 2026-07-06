import os

pages = {
    'ai-products.php': 'بطاقات ذكاء المنتجات',
    'ai-campaigns.php': 'الحملات الإعلانية',
    'ai-settings.php': 'إعدادات الذكاء الاصطناعي',
    'ai-keywords.php': 'الكلمات المفتاحية العامة',
    'ai-faqs.php': 'الأسئلة الشائعة',
    'ai-rules.php': 'قواعد البيع العامة',
    'ai-analytics.php': 'تحليلات الذكاء الاصطناعي',
    'ai-chat-logs.php': 'سجل المحادثات'
}

template = """<?php
require_once('header.php');
?>
<section class="content-header">
    <div class="content-header-left">
        <h1>{title}</h1>
    </div>
</section>
<section class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="box box-info">
                <div class="box-body">
                    <p>هذه الصفحة قيد التطوير (إصدار لاحق)...</p>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once('footer.php'); ?>
"""

for file_name, title in pages.items():
    path = os.path.join('C:/xampp/htdocs/ecom/admin', file_name)
    if not os.path.exists(path):
        with open(path, 'w', encoding='utf-8') as f:
            f.write(template.replace('{title}', title))

print('Skeleton pages created.')
