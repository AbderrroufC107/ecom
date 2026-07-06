<?php
require_once('header.php');
require_once('inc/config.php');

// Fetch all active conversations
$stmt = $dbRepo->query("
    SELECT c.id, c.current_status, c.ai_status, c.last_activity, cust.first_name, cust.last_name, ch.provider 
    FROM tbl_omni_conversations c 
    JOIN tbl_omni_customers cust ON c.customer_id = cust.id
    JOIN tbl_omni_channels ch ON c.current_channel_id = ch.id
    ORDER BY c.last_activity DESC
");
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<style>
.inbox-wrapper { display: flex; height: 80vh; border: 1px solid #ddd; background: #fff; }
.inbox-sidebar { width: 300px; border-left: 1px solid #ddd; overflow-y: auto; }
.inbox-main { flex: 1; display: flex; flex-direction: column; }
.inbox-360 { width: 350px; border-right: 1px solid #ddd; background: #f9f9f9; padding: 15px; overflow-y: auto; }
.conv-item { padding: 15px; border-bottom: 1px solid #eee; cursor: pointer; }
.conv-item:hover { background: #f4f4f4; }
.conv-item.active { background: #e0f7fa; border-right: 4px solid #00bcd4; }
.chat-timeline { flex: 1; padding: 20px; overflow-y: auto; background: #fdfdfd; }
.chat-input-area { padding: 15px; border-top: 1px solid #ddd; background: #fff; }
.timeline-event { margin-bottom: 15px; }
.msg-customer { background: #f1f0f0; padding: 10px 15px; border-radius: 15px 15px 0 15px; display: inline-block; max-width: 70%; float: right; clear: both; margin-bottom: 10px;}
.msg-agent { background: #0084ff; color: white; padding: 10px 15px; border-radius: 15px 15px 15px 0; display: inline-block; max-width: 70%; float: left; clear: both; margin-bottom: 10px;}
.msg-system { text-align: center; color: #888; font-size: 0.9em; clear: both; margin: 15px 0; }
</style>

<section class="content-header">
    <h1>صندوق الوارد الموحد</h1>
</section>

<section class="content" style="padding-top:10px;">
    <div class="inbox-wrapper">
        <!-- Sidebar: Conversations List -->
        <div class="inbox-sidebar" dir="rtl">
            <div style="padding: 10px; background: #f4f4f4; border-bottom: 1px solid #ddd; font-weight: bold;">المحادثات النشطة</div>
            <?php foreach($conversations as $c): ?>
            <div class="conv-item" onclick="loadConversation(<?= $c['id'] ?>)">
                <div style="display:flex; justify-content: space-between;">
                    <strong><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?: 'عميل #' . $c['id'] ?></strong>
                    <span class="label label-default"><?= strtoupper($c['provider']) ?></span>
                </div>
                <div style="font-size: 0.85em; color: #666; margin-top: 5px;">
                    <?= $c['ai_status'] === 'ACTIVE' ? '<i class="fa fa-robot text-success"></i> AI' : '<i class="fa fa-user text-primary"></i> موظف' ?>
                    | منذ <?= date('H:i', strtotime($c['last_activity'])) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Main: Timeline -->
        <div class="inbox-main">
            <div class="chat-timeline" id="chatTimeline">
                <div class="text-center" style="margin-top: 50px; color: #999;">
                    <i class="fa fa-comments-o" style="font-size: 50px;"></i>
                    <p>اختر محادثة للبدء</p>
                </div>
            </div>
            <div class="chat-input-area" style="display: none;" id="chatInputArea">
                <div class="input-group">
                    <input type="text" id="replyText" class="form-control" placeholder="اكتب ردك هنا... سيؤدي ذلك لإيقاف الـ AI تلقائياً">
                    <span class="input-group-btn">
                        <button class="btn btn-primary" type="button" onclick="sendReply()">إرسال</button>
                    </span>
                </div>
            </div>
        </div>

        <!-- Right Sidebar: Customer 360 -->
        <div class="inbox-360" dir="rtl" id="customer360Panel" style="display: none;">
            <h3 style="margin-top:0;">Customer 360</h3>
            <hr>
            <p><strong>الاسم:</strong> <span id="c360_name"></span></p>
            <p><strong>التقييم (Lead Score):</strong> <span id="c360_score" class="label label-warning"></span></p>
            <p><strong>مرحلة الشراء:</strong> <span id="c360_stage" class="label label-info"></span></p>
            <p><strong>رقم الهاتف:</strong> <span id="c360_phone"></span></p>
            <p><strong>الإيميل:</strong> <span id="c360_email"></span></p>
            <hr>
            <h4>المسار الشرائي (Journey)</h4>
            <ul style="padding-right: 20px; font-size:0.9em; color:#555;">
                <li>دخل من إعلان: <span id="c360_ad"></span></li>
                <li>تحدث مع AI</li>
            </ul>
            <hr>
            <button class="btn btn-block btn-success"><i class="fa fa-shopping-cart"></i> إنشاء طلب جديد</button>
            <button class="btn btn-block btn-default"><i class="fa fa-robot"></i> إعادة تفعيل AI</button>
        </div>
    </div>
</section>

<script>
let currentConvId = null;

function loadConversation(id) { global $dbRepo;
    global $dbRepo;

    currentConvId = id;
    $('.conv-item').removeClass('active');
    $(event.currentTarget).addClass('active');
    
    $('#chatInputArea').show();
    $('#customer360Panel').show();
    
    // In a real scenario, make AJAX calls to fetch timeline and customer 360 data
    // Mock for UI demonstration
    $('#chatTimeline').html(`
        <div class="msg-system">تم إنشاء المحادثة عبر Meta (Facebook)</div>
        <div class="msg-customer">مرحباً، هل يتوفر هذا المنتج باللون الأسود؟</div>
        <div class="msg-agent"><i class="fa fa-robot"></i> نعم متوفر باللون الأسود! يمكنك الطلب الآن، هل أساعدك في إتمام الطلب؟</div>
        <div class="msg-customer">كم السعر وهل يوجد شحن لمكة؟</div>
    `);
    
    $('#c360_name').text('أحمد عبد الله');
    $('#c360_score').text('85 (متفاعل جداً)');
    $('#c360_stage').text('IN_CART');
    $('#c360_phone').text('+966500000000');
    $('#c360_ad').text('حملة خصومات الصيف');
}

function sendReply() {    let txt = $('#replyText').val();
    if(!txt) return;
    
    // Append to UI immediately
    $('#chatTimeline').append(`<div class="msg-agent">${txt}</div>`);
    $('#chatTimeline').append(`<div class="msg-system">تم إيقاف الرد الآلي مؤقتاً لتدخل الموظف البشري</div>`);
    $('#replyText').val('');
    $('#chatTimeline').scrollTop($('#chatTimeline')[0].scrollHeight);
    
    // Here we would call ajax-omni-reply.php which hits the Adapter to send out the message and updates db
}
</script>

<?php require_once('footer.php'); ?>
