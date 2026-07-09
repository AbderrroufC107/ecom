<?php
require_once 'inc/config.php';
require_once 'inc/functions.php';
require_once 'header.php';

// Is any provider ready (has an API key)?
$providerReady = false;
$activeProvider = '';
try {
    $rows = $dbRepo->query("SELECT name, api_key FROM tbl_ai_providers WHERE is_enabled = 1 ORDER BY priority DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        if (!empty($r['api_key'])) { $providerReady = true; $activeProvider = $r['name']; break; }
    }
} catch (\Throwable $e) {}

$csrfToken = $csrf->getToken();
$initialSession = 'admin' . (int)($_SESSION['user']['id'] ?? 0) . '_' . date('Ymd_His');
?>

<section class="content-header">
    <div class="content-header-left">
        <h1>💬 المساعد الذكي <small>مدعوم بـ Gemini</small></h1>
    </div>
</section>

<section class="content">
    <?php if (!$providerReady): ?>
    <div class="alert alert-warning">
        <i class="fa fa-exclamation-triangle"></i>
        لم يتم ضبط مفتاح Gemini بعد — سيعمل المساعد في <b>الوضع التجريبي</b> (ردود وهمية).
        أضف مفتاحك من <a href="ai-agents.php">إدارة العملاء الآليين</a> لتفعيله فعلياً.
    </div>
    <?php else: ?>
    <div class="alert alert-success" style="padding:8px 12px">
        <i class="fa fa-check-circle"></i> المزوّد النشط: <b><?= htmlspecialchars($activeProvider) ?></b>
    </div>
    <?php endif; ?>

    <div class="box box-primary" style="max-width:900px;margin:0 auto">
        <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-comments"></i> محادثة</h3>
            <div class="box-tools pull-left">
                <button id="aiNewChat" class="btn btn-xs btn-default"><i class="fa fa-refresh"></i> محادثة جديدة</button>
            </div>
        </div>
        <div class="box-body" style="padding:0">
            <div id="aiMessages" style="height:460px;overflow-y:auto;padding:16px;background:#f7f7f9"></div>
        </div>
        <div class="box-footer">
            <form id="aiForm" autocomplete="off">
                <div class="input-group">
                    <input type="text" id="aiInput" class="form-control input-lg"
                           placeholder="اكتب سؤالك للمساعد…" maxlength="4000" autofocus>
                    <span class="input-group-btn">
                        <button type="submit" id="aiSend" class="btn btn-primary btn-lg">
                            <i class="fa fa-paper-plane"></i>
                        </button>
                    </span>
                </div>
            </form>
        </div>
    </div>
</section>

<style>
.ai-row{display:flex;margin-bottom:14px}
.ai-row.user{justify-content:flex-start}
.ai-row.bot{justify-content:flex-end}
.ai-bubble{max-width:78%;padding:10px 14px;border-radius:14px;line-height:1.8;
    box-shadow:0 1px 2px rgba(0,0,0,.08);white-space:normal;word-wrap:break-word}
.ai-row.user .ai-bubble{background:#fff;border:1px solid #e3e3e3;border-top-right-radius:4px}
.ai-row.bot  .ai-bubble{background:#3c8dbc;color:#fff;border-top-left-radius:4px}
.ai-bubble code{background:rgba(0,0,0,.12);padding:1px 5px;border-radius:4px;font-size:90%}
.ai-bubble ul{margin:6px 0;padding-inline-start:22px}
.ai-meta{font-size:11px;color:#999;margin-top:3px}
.ai-typing{font-style:italic;opacity:.85}
</style>

<script>
(function () {
    var ENDPOINT = 'ai-assistant-send.php';
    var CSRF = <?= json_encode($csrfToken) ?>;
    var sessionId = <?= json_encode($initialSession) ?>;

    var $msgs = document.getElementById('aiMessages');
    var $form = document.getElementById('aiForm');
    var $input = document.getElementById('aiInput');
    var $send = document.getElementById('aiSend');

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    // Minimal, safe Markdown: escape first, then apply a few inline rules.
    function renderMarkdown(text) {
        var html = escapeHtml(text);
        html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
        html = html.replace(/\*\*([^*]+)\*\*/g, '<b>$1</b>');
        // Bullet lines -> <ul><li>
        var lines = html.split('\n'), out = [], inList = false;
        lines.forEach(function (ln) {
            var m = ln.match(/^\s*[-*]\s+(.*)$/);
            if (m) {
                if (!inList) { out.push('<ul>'); inList = true; }
                out.push('<li>' + m[1] + '</li>');
            } else {
                if (inList) { out.push('</ul>'); inList = false; }
                out.push(ln);
            }
        });
        if (inList) out.push('</ul>');
        return out.join('\n').replace(/\n/g, '<br>');
    }

    function addMessage(role, text, isHtml) {
        var row = document.createElement('div');
        row.className = 'ai-row ' + (role === 'user' ? 'user' : 'bot');
        var bubble = document.createElement('div');
        bubble.className = 'ai-bubble';
        bubble.innerHTML = isHtml ? text : escapeHtml(text);
        row.appendChild(bubble);
        $msgs.appendChild(row);
        $msgs.scrollTop = $msgs.scrollHeight;
        return row;
    }

    function greet() {
        addMessage('bot', 'مرحباً 👋 أنا مساعدك الذكي. اسألني عن الطلبات، المبيعات، أو اطلب مني صياغة نص تسويقي.');
    }
    greet();

    $form.addEventListener('submit', function (e) {
        e.preventDefault();
        var text = $input.value.trim();
        if (!text) return;

        addMessage('user', text);
        $input.value = '';
        $input.disabled = true;
        $send.disabled = true;

        var typing = addMessage('bot', '…يكتب', false);
        typing.querySelector('.ai-bubble').classList.add('ai-typing');

        var body = new URLSearchParams();
        body.append('_csrf', CSRF);
        body.append('message', text);
        body.append('session_id', sessionId);

        fetch(ENDPOINT, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: body.toString(),
            credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            typing.remove();
            if (data && data.success) {
                sessionId = data.session_id || sessionId;
                var row = addMessage('bot', renderMarkdown(data.reply), true);
                if (data.mock) {
                    var meta = document.createElement('div');
                    meta.className = 'ai-meta';
                    meta.textContent = 'وضع تجريبي — أضف مفتاح Gemini للتفعيل';
                    row.appendChild(meta);
                }
            } else {
                addMessage('bot', '⚠️ ' + (data && data.message ? data.message : 'حدث خطأ.'));
            }
        })
        .catch(function () {
            typing.remove();
            addMessage('bot', '⚠️ تعذّر الاتصال بالخادم.');
        })
        .finally(function () {
            $input.disabled = false;
            $send.disabled = false;
            $input.focus();
        });
    });

    document.getElementById('aiNewChat').addEventListener('click', function () {
        sessionId = 'admin<?= (int)($_SESSION['user']['id'] ?? 0) ?>_' + Date.now();
        $msgs.innerHTML = '';
        greet();
        $input.focus();
    });
})();
</script>

<?php require_once 'footer.php'; ?>
