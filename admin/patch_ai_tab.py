import re

with open('C:/xampp/htdocs/ecom/admin/ai-product-tab.php', 'r', encoding='utf-8') as f:
    c = f.read()

tasks_html = """
    <h4 class="ai-section-title"><i class="fa fa-cogs"></i> المهام المطلوبة للتوليد (AI Tasks)</h4>
    <div class="row ai-card" style="margin-bottom: 20px;">
        <div class="col-md-2"><label><input type="checkbox" name="ai_tasks[]" value="keywords" checked> الكلمات المفتاحية</label></div>
        <div class="col-md-2"><label><input type="checkbox" name="ai_tasks[]" value="faqs" checked> الأسئلة الشائعة</label></div>
        <div class="col-md-2"><label><input type="checkbox" name="ai_tasks[]" value="objections" checked> الاعتراضات</label></div>
        <div class="col-md-2"><label><input type="checkbox" name="ai_tasks[]" value="sales_pitch" checked> Sales Pitch</label></div>
        <div class="col-md-3"><label><input type="checkbox" name="ai_tasks[]" value="conversation_training" checked> تدريب الوكلاء</label></div>
    </div>
"""

c = c.replace('<form id="aiProductForm">\n    <input type="hidden" name="p_id" value="<?= $p_id ?>">', '<form id="aiProductForm">\n    <input type="hidden" name="p_id" value="<?= $p_id ?>">\n' + tasks_html)

new_js = """
    let pollInterval;
    $('#btn-generate-ai').click(function() {
        const btn = $(this);
        const tasks = [];
        $('input[name="ai_tasks[]"]:checked').each(function() {
            tasks.push($(this).val());
        });

        if(tasks.length === 0) {
            alert('يجب اختيار مهمة واحدة على الأقل للتوليد.');
            return;
        }

        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> جاري إرسال المهمة...');

        $.ajax({
            url: 'ajax-ai-task-create.php',
            type: 'POST',
            data: {
                entity_id: <?= $p_id ?>,
                entity_type: 'product',
                task_type: 'product_generation',
                tasks: tasks
            },
            success: function(response) {
                let res = JSON.parse(response);
                if(res.status === 'success') {
                    btn.html('<i class="fa fa-spinner fa-spin"></i> قيد المعالجة (Task ID: ' + res.task_id + ')...');
                    pollInterval = setInterval(function() {
                        pollTaskStatus(res.task_id, btn);
                    }, 3000);
                } else {
                    alert('خطأ: ' + res.message);
                    btn.prop('disabled', false).html('<i class="fa fa-magic"></i> Generate AI Content');
                }
            },
            error: function() {
                alert('خطأ في الاتصال.');
                btn.prop('disabled', false).html('<i class="fa fa-magic"></i> Generate AI Content');
            }
        });
    });

    function pollTaskStatus(taskId, btn) {
        $.ajax({
            url: 'ajax-ai-task-status.php',
            type: 'GET',
            data: { task_id: taskId },
            success: function(response) {
                let res = JSON.parse(response);
                if(res.status === 'success') {
                    if(res.task_status === 'COMPLETED') {
                        clearInterval(pollInterval);
                        btn.prop('disabled', false).html('<i class="fa fa-check"></i> اكتمل التوليد بنجاح! راجع البيانات.');
                        if(res.result) {
                            console.log("AI Result Data:", res.result);
                            alert("اكتمل توليد الذكاء الاصطناعي بنجاح! سيتم مستقبلاً ملء الحقول تلقائياً. المخرجات معروضة الآن في الكونسول.");
                        }
                    } else if (res.task_status === 'FAILED' || res.task_status === 'CANCELLED') {
                        clearInterval(pollInterval);
                        btn.prop('disabled', false).html('<i class="fa fa-magic"></i> Generate AI Content');
                        alert('فشلت المهمة: ' + res.error_message);
                    } else {
                        btn.html('<i class="fa fa-spinner fa-spin"></i> ' + res.task_status + '...');
                    }
                }
            }
        });
    }
"""

c = re.sub(r"\$\('#btn-generate-ai'\)\.click\(function\(\) \{\s*alert\('ميزة التوليد التلقائي.*?'\);\s*\}\);", new_js, c, flags=re.DOTALL)

with open('C:/xampp/htdocs/ecom/admin/ai-product-tab.php', 'w', encoding='utf-8') as f:
    f.write(c)

print('ai-product-tab.php patched.')
