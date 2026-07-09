<?php
/**
 * ai-assistant-send.php
 * AJAX endpoint for the admin AI assistant (Gemini-backed).
 * Receives a message, calls the enabled AI provider, logs the exchange
 * to tbl_ai_chat_log, and returns the reply as JSON.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once 'inc/config.php';
require_once 'inc/functions.php';
require_once 'inc/CSRF_Protect.php';
require_once 'inc/AI/AiTaskEngine.php';
require_once 'inc/AI/AssistantContext.php';

header('Content-Type: application/json; charset=UTF-8');

/** Uniform JSON error/exit helper. */
function ai_json($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Auth: admin only ---
if (!isset($_SESSION['user'])) {
    ai_json(['success' => false, 'message' => 'غير مصرّح. سجّل الدخول كمسؤول.'], 401);
}

// --- Method ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ai_json(['success' => false, 'message' => 'طريقة غير صحيحة.'], 405);
}

// --- CSRF ---
$csrf = new CSRF_Protect();
if (!isset($_POST['_csrf']) || !$csrf->isTokenValid($_POST['_csrf'])) {
    ai_json(['success' => false, 'message' => 'فشل التحقق الأمني (CSRF).'], 403);
}

// --- Input ---
$message   = trim((string) ($_POST['message'] ?? ''));
$sessionId = trim((string) ($_POST['session_id'] ?? ''));
if ($message === '') {
    ai_json(['success' => false, 'message' => 'الرسالة فارغة.'], 422);
}
if (mb_strlen($message) > 4000) {
    $message = mb_substr($message, 0, 4000);
}
$adminId = (int) ($_SESSION['user']['id'] ?? 0);
if ($sessionId === '') {
    $sessionId = 'admin' . $adminId . '_' . date('Ymd');
}
// Constrain session id to a safe, bounded token.
$sessionId = substr(preg_replace('/[^A-Za-z0-9_\-]/', '', $sessionId), 0, 100);

try {
    // --- Pick a provider: prefer an enabled one that has an API key (Gemini first) ---
    $engine    = new \AI\AiTaskEngine($pdo);
    $providers = $engine->getProviders(); // enabled, priority DESC

    $chosen = null;
    foreach ($providers as $prov) {
        if (stripos($prov['name'], 'gemini') !== false && !empty($prov['api_key'])) { $chosen = $prov; break; }
    }
    if (!$chosen) {
        foreach ($providers as $prov) {
            if (!empty($prov['api_key'])) { $chosen = $prov; break; }
        }
    }
    if (!$chosen) {
        // Fall back to the Gemini row (mock mode) so the UI still responds.
        foreach ($providers as $prov) {
            if (stripos($prov['name'], 'gemini') !== false) { $chosen = $prov; break; }
        }
    }
    if (!$chosen) {
        ai_json(['success' => false, 'message' => 'لا يوجد مزوّد AI مفعّل. فعّل مزوّداً من صفحة إدارة العملاء الآليين.'], 400);
    }

    $provider = $engine->createProviderInstance($chosen);
    if (!$provider || !method_exists($provider, 'chat')) {
        ai_json(['success' => false, 'message' => 'المزوّد المختار لا يدعم المحادثة الحرّة. اختر Gemini.'], 400);
    }

    // --- Build role-scoped live context (best-effort; never fatal) ---
    $scope   = 'admin';
    $ctxText = '';
    try {
        $ctx     = assistant_build_context($pdo, $_SESSION['user']);
        $scope   = $ctx['scope'];
        $ctxText = $ctx['context'];
    } catch (\Throwable $e) {
        error_log('[ai-assistant] context: ' . $e->getMessage());
    }

    if ($scope === 'employee') {
        $persona =
            "أنت مساعد ذكي داخلي لمتجر إلكتروني في الجزائر، تخاطب موظفاً.\n" .
            "- مهم جداً: لا تكشف أي بيانات عن موظفين آخرين، ولا إجماليات المتجر العامة، ولا بيانات لا تخص هذا الموظف.\n" .
            "- أجبه فقط عن طلباته المُسندة إليه، أدائه، وأرباحه/عمولاته.\n" .
            "- إن سأل عن بيانات خارج نطاقه، اعتذر بلطف ووضّح أنها غير متاحة لصلاحيته.";
    } else {
        $persona =
            "أنت مساعد ذكي داخلي لمتجر إلكتروني في الجزائر، تخاطب مسؤول المتجر (أدمن) بوصول كامل.\n" .
            "- ساعد في تحليل الطلبات والمبيعات والموظفين والولايات، واكتشاف المشاكل واقتراح حلول عملية.";
    }

    $systemPrompt =
        $persona . "\n\n" .
        "قواعد عامة:\n" .
        "- أجب دائماً بالعربية الفصحى المبسّطة وبإيجاز ووضوح.\n" .
        "- اعتمد على «البيانات الحيّة» أدناه عند الإجابة عن أرقام المتجر، ولا تختلق أرقاماً.\n" .
        "- إن كانت المعلومة غير موجودة في البيانات، قل ذلك بوضوح بدل التخمين.\n" .
        "- استخدم تنسيق Markdown بسيط عند الحاجة (قوائم، تعداد).\n\n" .
        "===== البيانات الحيّة (محدّثة الآن، مقيّدة بصلاحيتك) =====\n" .
        $ctxText . "\n" .
        "===== نهاية البيانات =====";

    // --- Load recent history for this session (last 10 turns) ---
    $history = [];
    try {
        $h = $dbRepo->prepare("SELECT role, content FROM tbl_ai_chat_log
            WHERE session_id = ? AND role IN ('user','assistant')
            ORDER BY id DESC LIMIT 10");
        $h->execute([$sessionId]);
        $rows = array_reverse($h->fetchAll(PDO::FETCH_ASSOC));
        foreach ($rows as $r) {
            $history[] = ['role' => $r['role'], 'content' => $r['content']];
        }
    } catch (\Throwable $e) {
    }

    // --- Log the user message ---
    $ins = $dbRepo->prepare("INSERT INTO tbl_ai_chat_log (session_id, customer_identifier, role, content, created_at)
        VALUES (?, ?, 'user', ?, NOW())");
    $ins->execute([$sessionId, 'admin:' . $adminId, $message]);

    // --- Call the provider ---
    $model = $chosen['model'] ?? 'gemini-2.5-flash';
    // Safety net: upgrade Gemini models whose free-tier quota is 0 / retired.
    $deadGeminiModels = ['gemini-2.0-flash', 'gemini-2.0-flash-lite', 'gemini-1.5-pro', 'gemini-1.5-flash', 'gemini-pro'];
    if (stripos($chosen['name'], 'gemini') !== false && in_array(strtolower($model), $deadGeminiModels, true)) {
        $model = 'gemini-2.5-flash';
    }
    $opts = [
        'model'       => $model,
        'temperature' => (float) ($chosen['temperature'] ?? 0.7),
        'max_tokens'  => (int) ($chosen['max_tokens'] ?? 2000),
    ];
    $result = $provider->chat($message, $history, $systemPrompt, $opts);
    $reply  = trim((string) ($result['content'] ?? ''));
    if ($reply === '') {
        $reply = 'تعذّر توليد رد. حاول مرة أخرى.';
    }

    // --- Log the assistant reply ---
    $ins2 = $dbRepo->prepare("INSERT INTO tbl_ai_chat_log (session_id, customer_identifier, role, content, created_at)
        VALUES (?, ?, 'assistant', ?, NOW())");
    $ins2->execute([$sessionId, 'admin:' . $adminId, $reply]);

    ai_json([
        'success'    => true,
        'reply'      => $reply,
        'session_id' => $sessionId,
        'provider'   => $chosen['name'],
        'mock'       => empty($chosen['api_key']),
        'usage'      => $result['usage'] ?? null,
    ]);
} catch (\Throwable $e) {
    error_log('[ai-assistant] ' . $e->getMessage());
    ai_json(['success' => false, 'message' => 'حدث خطأ أثناء الاتصال بالمزوّد: ' . $e->getMessage()], 500);
}
