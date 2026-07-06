<?php
require_once 'inc/config.php';

$p_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$p_id) {
    echo '<div class="alert alert-danger">معرف المنتج غير صالح.</div>';
    exit;
}

// Fetch basic AI product data
$stmt = $dbRepo->prepare("SELECT * FROM tbl_ai_product WHERE p_id = ?");
$stmt->execute([$p_id]);
$ai_product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ai_product) {
    $ai_product = [
        'ai_version' => 1,
        'selling_title' => '',
        'short_pitch' => '',
        'long_pitch' => '',
        'cta' => '',
        'negotiable' => 0,
        'lowest_price' => '0.00',
        'max_discount_pct' => '0.00',
        'discount_conditions' => ''
    ];
}

// Fetch Keywords
$stmt = $dbRepo->prepare("SELECT keyword, is_synonym FROM tbl_ai_keyword WHERE p_id = ?");
$stmt->execute([$p_id]);
$keywords = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch FAQs
$stmt = $dbRepo->prepare("SELECT question, answer FROM tbl_ai_faq WHERE p_id = ?");
$stmt->execute([$p_id]);
$faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Objections
$stmt = $dbRepo->prepare("SELECT objection, best_reply, priority FROM tbl_ai_objection WHERE p_id = ? ORDER BY priority DESC");
$stmt->execute([$p_id]);
$objections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Training
$stmt = $dbRepo->prepare("SELECT topic, training_reply FROM tbl_ai_training WHERE p_id = ?");
$stmt->execute([$p_id]);
$trainings = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<style>
.ai-section-title { font-weight: bold; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-top: 30px; margin-bottom: 20px; color: #4f46e5; }
.ai-card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 15px; background: #f8fafc; }
.ai-btn-generate { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
.ai-btn-generate:hover { background: linear-gradient(135deg, #764ba2 0%, #667eea 100%); color: white; box-shadow: 0 6px 8px rgba(0,0,0,0.15); }
.remove-row-btn { color: #ef4444; cursor: pointer; }
.remove-row-btn:hover { color: #dc2626; }
</style>

<div class="row">
    <div class="col-md-12 text-left" style="margin-bottom: 20px;">
        <button type="button" class="ai-btn-generate" id="btn-generate-ai">
            <i class="fa fa-magic"></i> Generate AI Content
        </button>
        <div class="pull-right text-muted" style="margin-top: 10px;">
            <i class="fa fa-code-fork"></i> الإصدار (AI Version): <strong><?= $ai_product['ai_version'] ?></strong>
        </div>
    </div>
</div>

<form id="aiProductForm">
    <input type="hidden" name="p_id" value="<?= $p_id ?>">

    <h4 class="ai-section-title"><i class="fa fa-cogs"></i> المهام المطلوبة للتوليد (AI Tasks)</h4>
    <div class="row ai-card" style="margin-bottom: 20px;">
        <div class="col-md-2"><label><input type="checkbox" name="ai_tasks[]" value="keywords" checked> الكلمات المفتاحية</label></div>
        <div class="col-md-2"><label><input type="checkbox" name="ai_tasks[]" value="faqs" checked> الأسئلة الشائعة</label></div>
        <div class="col-md-2"><label><input type="checkbox" name="ai_tasks[]" value="objections" checked> الاعتراضات</label></div>
        <div class="col-md-2"><label><input type="checkbox" name="ai_tasks[]" value="sales_pitch" checked> Sales Pitch</label></div>
        <div class="col-md-3"><label><input type="checkbox" name="ai_tasks[]" value="conversation_training" checked> تدريب الوكلاء</label></div>
    </div>

    
    <h4 class="ai-section-title"><i class="fa fa-bullseye"></i> بيانات المبيعات (Selling Data)</h4>
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label>عنوان البيع (Selling Title)</label>
                <input type="text" class="form-control" name="selling_title" value="<?= htmlspecialchars($ai_product['selling_title']) ?>">
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label>الدعوة للإجراء (CTA)</label>
                <input type="text" class="form-control" name="cta" value="<?= htmlspecialchars($ai_product['cta']) ?>">
            </div>
        </div>
    </div>
    <div class="form-group">
        <label>الوصف القصير (Short Pitch)</label>
        <textarea class="form-control" name="short_pitch" rows="2"><?= htmlspecialchars($ai_product['short_pitch']) ?></textarea>
    </div>
    <div class="form-group">
        <label>الوصف الطويل (Long Pitch)</label>
        <textarea class="form-control" name="long_pitch" rows="4"><?= htmlspecialchars($ai_product['long_pitch']) ?></textarea>
    </div>

    <h4 class="ai-section-title"><i class="fa fa-money"></i> التسعير والتفاوض (Negotiation & Discounts)</h4>
    <div class="row">
        <div class="col-md-3">
            <div class="form-group">
                <label>قابل للتفاوض؟</label>
                <select class="form-control" name="negotiable">
                    <option value="0" <?= $ai_product['negotiable'] == 0 ? 'selected' : '' ?>>لا</option>
                    <option value="1" <?= $ai_product['negotiable'] == 1 ? 'selected' : '' ?>>نعم</option>
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>أقل سعر ممكن (Lowest Price)</label>
                <input type="number" step="0.01" class="form-control" name="lowest_price" value="<?= $ai_product['lowest_price'] ?>">
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>أقصى نسبة خصم (%)</label>
                <input type="number" step="0.01" class="form-control" name="max_discount_pct" value="<?= $ai_product['max_discount_pct'] ?>">
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>شروط الخصم</label>
                <input type="text" class="form-control" name="discount_conditions" value="<?= htmlspecialchars($ai_product['discount_conditions']) ?>" placeholder="مثال: عند شراء 3 قطع">
            </div>
        </div>
    </div>

    <h4 class="ai-section-title"><i class="fa fa-tags"></i> الكلمات المفتاحية (Keywords & Synonyms)</h4>
    <div id="keywords-container">
        <?php foreach ($keywords as $kw): ?>
        <div class="row ai-card">
            <div class="col-md-5">
                <input type="text" class="form-control" name="keywords[]" value="<?= htmlspecialchars($kw['keyword']) ?>" placeholder="الكلمة">
            </div>
            <div class="col-md-5">
                <select class="form-control" name="is_synonym[]">
                    <option value="0" <?= $kw['is_synonym'] == 0 ? 'selected' : '' ?>>كلمة مفتاحية</option>
                    <option value="1" <?= $kw['is_synonym'] == 1 ? 'selected' : '' ?>>مرادف (Synonym)</option>
                </select>
            </div>
            <div class="col-md-2 text-center" style="padding-top: 8px;">
                <i class="fa fa-trash fa-lg remove-row-btn"></i>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-default btn-sm" id="btn-add-keyword"><i class="fa fa-plus"></i> إضافة كلمة</button>

    <h4 class="ai-section-title"><i class="fa fa-question-circle"></i> الأسئلة الشائعة (FAQs)</h4>
    <div id="faqs-container">
        <?php foreach ($faqs as $faq): ?>
        <div class="row ai-card">
            <div class="col-md-5">
                <input type="text" class="form-control" name="faq_questions[]" value="<?= htmlspecialchars($faq['question']) ?>" placeholder="السؤال">
            </div>
            <div class="col-md-6">
                <textarea class="form-control" name="faq_answers[]" rows="1" placeholder="الجواب"><?= htmlspecialchars($faq['answer']) ?></textarea>
            </div>
            <div class="col-md-1 text-center" style="padding-top: 8px;">
                <i class="fa fa-trash fa-lg remove-row-btn"></i>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-default btn-sm" id="btn-add-faq"><i class="fa fa-plus"></i> إضافة سؤال</button>

    <h4 class="ai-section-title"><i class="fa fa-shield"></i> اعتراضات العملاء (Objections)</h4>
    <div id="objections-container">
        <?php foreach ($objections as $obj): ?>
        <div class="row ai-card">
            <div class="col-md-4">
                <input type="text" class="form-control" name="obj_objections[]" value="<?= htmlspecialchars($obj['objection']) ?>" placeholder="الاعتراض">
            </div>
            <div class="col-md-5">
                <textarea class="form-control" name="obj_replies[]" rows="1" placeholder="أفضل رد"><?= htmlspecialchars($obj['best_reply']) ?></textarea>
            </div>
            <div class="col-md-2">
                <input type="number" class="form-control" name="obj_priorities[]" value="<?= $obj['priority'] ?>" placeholder="الأولوية">
            </div>
            <div class="col-md-1 text-center" style="padding-top: 8px;">
                <i class="fa fa-trash fa-lg remove-row-btn"></i>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-default btn-sm" id="btn-add-objection"><i class="fa fa-plus"></i> إضافة اعتراض</button>

    <h4 class="ai-section-title"><i class="fa fa-graduation-cap"></i> تدريب الوكلاء (Conversation Training)</h4>
    <div id="training-container">
        <?php foreach ($trainings as $tr): ?>
        <div class="row ai-card">
            <div class="col-md-4">
                <select class="form-control" name="tr_topics[]">
                    <option value="price" <?= $tr['topic'] === 'price' ? 'selected' : '' ?>>السعر مرتفع</option>
                    <option value="origin" <?= $tr['topic'] === 'origin' ? 'selected' : '' ?>>هل المنتج أصلي؟</option>
                    <option value="delivery" <?= $tr['topic'] === 'delivery' ? 'selected' : '' ?>>مدة التوصيل</option>
                    <option value="size" <?= $tr['topic'] === 'size' ? 'selected' : '' ?>>المقاسات</option>
                    <option value="color" <?= $tr['topic'] === 'color' ? 'selected' : '' ?>>الألوان</option>
                    <option value="return" <?= $tr['topic'] === 'return' ? 'selected' : '' ?>>الإرجاع والاستبدال</option>
                    <option value="custom" <?= $tr['topic'] === 'custom' ? 'selected' : '' ?>>مخصص</option>
                </select>
            </div>
            <div class="col-md-7">
                <textarea class="form-control" name="tr_replies[]" rows="2" placeholder="الرد المعتمد للوكيل"><?= htmlspecialchars($tr['training_reply']) ?></textarea>
            </div>
            <div class="col-md-1 text-center" style="padding-top: 20px;">
                <i class="fa fa-trash fa-lg remove-row-btn"></i>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-default btn-sm" id="btn-add-training"><i class="fa fa-plus"></i> إضافة تدريب</button>

    <div style="margin-top: 30px; text-align: left;">
        <button type="button" class="btn btn-primary btn-lg" id="btn-save-ai">
            <i class="fa fa-save"></i> حفظ بيانات الذكاء الاصطناعي
        </button>
    </div>
</form>

<script>
$(document).ready(function() {
    
    $(document).on('click', '.remove-row-btn', function() {
        $(this).closest('.row').remove();
    });

    $('#btn-add-keyword').click(function() {
        $('#keywords-container').append(
            '<div class="row ai-card"><div class="col-md-5"><input type="text" class="form-control" name="keywords[]" placeholder="الكلمة"></div>' +
            '<div class="col-md-5"><select class="form-control" name="is_synonym[]"><option value="0">كلمة مفتاحية</option><option value="1">مرادف (Synonym)</option></select></div>' +
            '<div class="col-md-2 text-center" style="padding-top: 8px;"><i class="fa fa-trash fa-lg remove-row-btn"></i></div></div>'
        );
    });

    $('#btn-add-faq').click(function() {
        $('#faqs-container').append(
            '<div class="row ai-card"><div class="col-md-5"><input type="text" class="form-control" name="faq_questions[]" placeholder="السؤال"></div>' +
            '<div class="col-md-6"><textarea class="form-control" name="faq_answers[]" rows="1" placeholder="الجواب"></textarea></div>' +
            '<div class="col-md-1 text-center" style="padding-top: 8px;"><i class="fa fa-trash fa-lg remove-row-btn"></i></div></div>'
        );
    });

    $('#btn-add-objection').click(function() {
        $('#objections-container').append(
            '<div class="row ai-card"><div class="col-md-4"><input type="text" class="form-control" name="obj_objections[]" placeholder="الاعتراض"></div>' +
            '<div class="col-md-5"><textarea class="form-control" name="obj_replies[]" rows="1" placeholder="أفضل رد"></textarea></div>' +
            '<div class="col-md-2"><input type="number" class="form-control" name="obj_priorities[]" value="0" placeholder="الأولوية"></div>' +
            '<div class="col-md-1 text-center" style="padding-top: 8px;"><i class="fa fa-trash fa-lg remove-row-btn"></i></div></div>'
        );
    });

    $('#btn-add-training').click(function() {
        $('#training-container').append(
            '<div class="row ai-card"><div class="col-md-4"><select class="form-control" name="tr_topics[]">' +
            '<option value="price">السعر مرتفع</option><option value="origin">هل المنتج أصلي؟</option><option value="delivery">مدة التوصيل</option>' +
            '<option value="size">المقاسات</option><option value="color">الألوان</option><option value="return">الإرجاع والاستبدال</option><option value="custom">مخصص</option>' +
            '</select></div><div class="col-md-7"><textarea class="form-control" name="tr_replies[]" rows="2" placeholder="الرد المعتمد للوكيل"></textarea></div>' +
            '<div class="col-md-1 text-center" style="padding-top: 20px;"><i class="fa fa-trash fa-lg remove-row-btn"></i></div></div>'
        );
    });

    $('#btn-save-ai').click(function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> جاري الحفظ...');
        
        $.ajax({
            url: 'ajax-ai-product-save.php',
            type: 'POST',
            data: $('#aiProductForm').serialize(),
            success: function(response) {
                btn.prop('disabled', false).html('<i class="fa fa-save"></i> حفظ بيانات الذكاء الاصطناعي');
                let res = JSON.parse(response);
                if(res.status === 'success') {
                    alert('تم حفظ البيانات بنجاح!');
                } else {
                    alert('حدث خطأ: ' + res.message);
                }
            },
            error: function() {
                btn.prop('disabled', false).html('<i class="fa fa-save"></i> حفظ بيانات الذكاء الاصطناعي');
                alert('حدث خطأ غير متوقع.');
            }
        });
    });

    
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

    function pollTaskStatus(taskId, btn) { global $dbRepo;
    global $dbRepo;

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

});
</script>
