import re

with open('C:/xampp/htdocs/ecom/admin/product-edit.php', 'r', encoding='utf-8') as f:
    c = f.read()

tabs_header = """
<div class="nav-tabs-custom" style="border-radius:12px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.05); margin-bottom: 20px;">
    <ul class="nav nav-tabs" style="background:#fff; border-bottom:1px solid #e2e8f0; padding:10px 15px 0 15px;">
        <li class="active"><a href="#tab_1" data-toggle="tab" style="font-weight:bold;">البيانات الأساسية</a></li>
        <li><a href="#tab_ai" data-toggle="tab" style="font-weight:bold; color:#4f46e5;"><i class="fa fa-magic"></i> الذكاء الاصطناعي (AI)</a></li>
        <li><a href="#tab_marketing" data-toggle="tab" style="font-weight:bold; color:#ec4899;"><i class="fa fa-bullhorn"></i> التسويق (Marketing)</a></li>
    </ul>
    <div class="tab-content" style="background:#fff; padding: 20px;">
        <div class="tab-pane active" id="tab_1">
            <form class="form-horizontal admin-product-form" method="post" enctype="multipart/form-data">
"""

c = c.replace('<form class="form-horizontal admin-product-form" method="post" enctype="multipart/form-data">', tabs_header, 1)

tabs_footer = """
            </form>
        </div>
        
        <!-- AI TAB -->
        <div class="tab-pane" id="tab_ai">
            <div id="ai-card-wrapper">جاري التحميل...</div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const aiWrapper = document.getElementById('ai-card-wrapper');
                    fetch('ai-product-tab.php?id=' + encodeURIComponent('<?= $id ?>'))
                        .then(r => r.text())
                        .then(html => {
                            aiWrapper.innerHTML = html;
                            // Execute any scripts in the loaded HTML
                            const scripts = aiWrapper.querySelectorAll('script');
                            scripts.forEach(s => {
                                const newScript = document.createElement('script');
                                newScript.textContent = s.textContent;
                                document.body.appendChild(newScript).parentNode.removeChild(newScript);
                            });
                        })
                        .catch(e => aiWrapper.innerHTML = '<div class="alert alert-danger">خطأ في تحميل بيانات الذكاء الاصطناعي.</div>');
                });
            </script>
        </div>
        
        <!-- MARKETING TAB -->
        <div class="tab-pane" id="tab_marketing">
            <div id="marketing-card-wrapper">جاري التحميل...</div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const mkgWrapper = document.getElementById('marketing-card-wrapper');
                    fetch('marketing-product-tab.php?id=' + encodeURIComponent('<?= $id ?>'))
                        .then(r => r.text())
                        .then(html => {
                            mkgWrapper.innerHTML = html;
                            const scripts = mkgWrapper.querySelectorAll('script');
                            scripts.forEach(s => {
                                const newScript = document.createElement('script');
                                newScript.textContent = s.textContent;
                                document.body.appendChild(newScript).parentNode.removeChild(newScript);
                            });
                        })
                        .catch(e => mkgWrapper.innerHTML = '<div class="alert alert-danger">خطأ في تحميل بيانات التسويق.</div>');
                });
            </script>
        </div>
        
    </div> <!-- end tab-content -->
</div> <!-- end nav-tabs-custom -->
"""

c = c.replace('</form>', tabs_footer, 1)

with open('C:/xampp/htdocs/ecom/admin/product-edit.php', 'w', encoding='utf-8') as f:
    f.write(c)

print('product-edit.php patched successfully.')
