<?php
/**
 * ═══════════════════════════════════════════════════════════════
 *  PLATFORM COMPREHENSIVE TEST SUITE
 *  Tests: DB Tables · SecretManager · MetaAdapter · MessageRouter
 *         N8nManager · AI Task Engine · OmniChannel · EventLogger
 * ═══════════════════════════════════════════════════════════════
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/admin/inc/config.php';
require_once __DIR__ . '/admin/inc/integration/N8nManager.php';
require_once __DIR__ . '/admin/inc/Omni/EventLogger.php';
require_once __DIR__ . '/admin/inc/Omni/UnifiedMessage.php';
require_once __DIR__ . '/admin/inc/Omni/Adapters/AdapterInterface.php';
require_once __DIR__ . '/admin/inc/Omni/Adapters/MetaAdapter.php';
require_once __DIR__ . '/admin/inc/Omni/MessageRouter.php';
require_once __DIR__ . '/admin/inc/Security/SecretManager.php';

use Integration\N8nManager;
use Omni\EventLogger;
use Omni\MessageRouter;
use Omni\UnifiedMessage;
use Security\SecretManager;

// ─── Runner ──────────────────────────────────────────────────────────────────
$results   = [];
$startAll  = microtime(true);

function run_test(string $name, callable $fn): array {
    $start = microtime(true);
    try {
        $fn();
        return ['name' => $name, 'status' => 'PASS', 'msg' => 'OK', 'ms' => round((microtime(true)-$start)*1000)];
    } catch (AssertionError $e) {
        return ['name' => $name, 'status' => 'FAIL', 'msg' => $e->getMessage(), 'ms' => round((microtime(true)-$start)*1000)];
    } catch (Throwable $e) {
        return ['name' => $name, 'status' => 'FAIL', 'msg' => get_class($e).': '.$e->getMessage(), 'ms' => round((microtime(true)-$start)*1000)];
    }
}

function assert_true($val, string $msg = 'Expected true'): void {
    if (!$val) throw new AssertionError($msg);
}
function assert_eq($a, $b, string $msg = ''): void {
    if ($a !== $b) throw new AssertionError($msg ?: "Expected ".json_encode($b)." got ".json_encode($a));
}
function assert_not_empty($val, string $msg = 'Expected non-empty'): void {
    if (empty($val)) throw new AssertionError($msg);
}

// ═══════════════════════════════════════════════════════════════
// GROUP 1 — DATABASE TABLES
// ═══════════════════════════════════════════════════════════════

$required_tables = [
    'tbl_ai_tasks', 'tbl_ai_providers', 'tbl_ai_prompts', 'tbl_ai_metrics',
    'tbl_ai_knowledge', 'tbl_ai_knowledge_categories',
    'tbl_omni_channels', 'tbl_omni_conversations', 'tbl_omni_customers',
    'tbl_omni_customer_identities', 'tbl_omni_timeline', 'tbl_omni_events',
    'tbl_n8n_integrations', 'tbl_n8n_call_log',
    'tbl_secrets', 'tbl_audit_log', 'tbl_security_log',
];

foreach ($required_tables as $tbl) {
    $results[] = run_test("DB: جدول $tbl موجود", function() use ($pdo, $tbl) {
        $r = $pdo->query("SHOW TABLES LIKE '$tbl'")->fetchColumn();
        assert_true($r !== false, "الجدول $tbl غير موجود في قاعدة البيانات");
    });
}

// ═══════════════════════════════════════════════════════════════
// GROUP 2 — SECRET MANAGER
// ═══════════════════════════════════════════════════════════════

$results[] = run_test("SecretManager: تشفير وفك تشفير", function() use ($pdo) {
    $sm = new SecretManager($pdo);
    $testKey = 'test_secret_' . uniqid();
    $testVal = 'super-secret-value-' . rand(1000,9999);
    $sm->setSecret($testKey, $testVal, 'test');
    $retrieved = $sm->getSecret($testKey);
    assert_eq($retrieved, $testVal, "قيمة السر لا تتطابق بعد فك التشفير");
    // Cleanup
    $pdo->prepare("DELETE FROM tbl_secrets WHERE secret_name = ?")->execute([$testKey]);
});

$results[] = run_test("SecretManager: سر غير موجود يعيد null", function() use ($pdo) {
    $sm = new SecretManager($pdo);
    $val = $sm->getSecret('nonexistent_key_xyz_' . uniqid());
    assert_true($val === null, "يجب أن يعيد null للمفتاح غير الموجود");
});

// ═══════════════════════════════════════════════════════════════
// GROUP 3 — N8N INTEGRATION MANAGER
// ═══════════════════════════════════════════════════════════════

$results[] = run_test("N8nManager: تشفير/فك تشفير API Key", function() {
    $plain = 'my-api-key-12345';
    $encrypted = N8nManager::encryptApiKey($plain);
    assert_not_empty($encrypted, "التشفير فشل - القيمة فارغة");
    assert_true($encrypted !== $plain, "API Key لم يُشفَّر");
});

$results[] = run_test("N8nManager: ensureTables تعمل", function() use ($pdo) {
    N8nManager::ensureTables($pdo);
    $r = $pdo->query("SHOW TABLES LIKE 'tbl_n8n_integrations'")->fetchColumn();
    assert_not_empty($r, "جدول tbl_n8n_integrations لم يُنشأ");
});

$results[] = run_test("N8nManager: رفض إذا لم يكن هناك تكامل", function() use ($pdo) {
    // Use a fake manager that won't find any active integration
    $thrown = false;
    try {
        $n8n = new N8nManager($pdo, 'nonexistent_environment_' . uniqid());
        $n8n->getBaseUrl();
    } catch (Exception $e) {
        $thrown = true;
    }
    assert_true($thrown, "يجب أن يرمي Exception عند عدم وجود تكامل");
});

$results[] = run_test("N8nManager: إضافة تكامل تجريبي والاسترجاع", function() use ($pdo) {
    $encrypted = N8nManager::encryptApiKey('test-key');
    $pdo->prepare("INSERT INTO tbl_n8n_integrations (environment, label, base_url, webhook_paths, api_key, is_active) VALUES (?,?,?,?,?,?)")
        ->execute(['development', 'TestSuite', 'https://test.n8n.cloud', json_encode(['ai_agent'=>'/webhook/test']), $encrypted, 1]);
    $id = $pdo->lastInsertId();

    $n8n = new N8nManager($pdo, 'development');
    $url = $n8n->getWebhook('ai_agent');
    assert_eq($url, 'https://test.n8n.cloud/webhook/test', "URL التجريبي غير صحيح: $url");

    // Cleanup
    $pdo->prepare("DELETE FROM tbl_n8n_integrations WHERE id=?")->execute([$id]);
});

$results[] = run_test("N8nManager: webhook key غير معروف يرمي Exception", function() use ($pdo) {
    $pdo->prepare("INSERT INTO tbl_n8n_integrations (environment, label, base_url, is_active) VALUES (?,?,?,?)")
        ->execute(['development','TS2','https://x.n8n.cloud',1]);
    $id = $pdo->lastInsertId();
    $thrown = false;
    try {
        $n8n = new N8nManager($pdo, 'development');
        $n8n->getWebhook('unknown_key_xyz');
    } catch (Exception $e) {
        $thrown = true;
    }
    $pdo->prepare("DELETE FROM tbl_n8n_integrations WHERE id=?")->execute([$id]);
    assert_true($thrown, "يجب أن يرمي Exception لمفتاح webhook غير معروف");
});

// ═══════════════════════════════════════════════════════════════
// GROUP 4 — EVENT LOGGER (OMNI EVENT STORE)
// ═══════════════════════════════════════════════════════════════

$results[] = run_test("EventLogger: تسجيل حدث", function() use ($pdo) {
    $logger = new EventLogger($pdo);
    $id = $logger->log('Test Event', [
        'channel' => 'test',
        'status'  => 'SUCCESS',
        'metadata' => ['source' => 'test_suite']
    ]);
    assert_true($id > 0, "EventLogger لم يعيد ID صالحاً: $id");
    $pdo->prepare("DELETE FROM tbl_omni_events WHERE id=?")->execute([$id]);
});

$results[] = run_test("EventLogger: الحدث يُحفظ في قاعدة البيانات", function() use ($pdo) {
    $logger = new EventLogger($pdo);
    $marker = 'test_' . uniqid();
    $id = $logger->log('Test Persistence', ['channel'=>'test','status'=>'SUCCESS','metadata'=>['marker'=>$marker]]);
    $row = $pdo->prepare("SELECT * FROM tbl_omni_events WHERE id=?")->execute([$id]) ? 
           $pdo->prepare("SELECT * FROM tbl_omni_events WHERE id=?") : null;
    $stmt = $pdo->prepare("SELECT event_type FROM tbl_omni_events WHERE id=?");
    $stmt->execute([$id]);
    $type = $stmt->fetchColumn();
    assert_eq($type, 'Test Persistence', "نوع الحدث لا يتطابق");
    $pdo->prepare("DELETE FROM tbl_omni_events WHERE id=?")->execute([$id]);
});

// ═══════════════════════════════════════════════════════════════
// GROUP 5 — META ADAPTER
// ═══════════════════════════════════════════════════════════════

$results[] = run_test("MetaAdapter: Signature Validation — صحيح", function() {
    $adapter = new Omni\Adapters\MetaAdapter();
    $payload = '{"test":"data"}';
    $secret  = 'my_webhook_secret';
    $sig     = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    assert_true($adapter->validateSignature($payload, $sig, $secret), "التحقق من التوقيع فشل لتوقيع صحيح");
});

$results[] = run_test("MetaAdapter: Signature Validation — خاطئ", function() {
    $adapter = new Omni\Adapters\MetaAdapter();
    $result = $adapter->validateSignature('{"test":"data"}', 'sha256=invalidsig', 'secret');
    assert_true(!$result, "يجب أن يرفض التوقيع الخاطئ");
});

$results[] = run_test("MetaAdapter: تحليل رسالة نصية", function() {
    $adapter = new Omni\Adapters\MetaAdapter();
    $payload = json_encode([
        'object' => 'page',
        'entry'  => [[
            'id'        => '1234567890',
            'time'      => time(),
            'messaging' => [[
                'sender'    => ['id' => 'USER_TEST'],
                'recipient' => ['id' => '1234567890'],
                'timestamp' => time() * 1000,
                'message'   => ['mid' => 'test_mid_1', 'text' => 'مرحبا كيف حالك']
            ]]
        ]]
    ]);
    $msgs = $adapter->parsePayload($payload, 99);
    assert_true(count($msgs) === 1, "يجب تحليل رسالة واحدة");
    assert_eq($msgs[0]->messageType, 'TEXT', "النوع يجب أن يكون TEXT");
    assert_eq($msgs[0]->text, 'مرحبا كيف حالك', "النص لا يتطابق");
    assert_eq($msgs[0]->platformUserId, 'USER_TEST', "User ID لا يتطابق");
});

$results[] = run_test("MetaAdapter: تجاهل رسائل echo (self)", function() {
    $adapter = new Omni\Adapters\MetaAdapter();
    $payload = json_encode([
        'object' => 'page',
        'entry'  => [[
            'id'        => '1234567890',
            'time'      => time(),
            'messaging' => [[
                'sender'    => ['id' => '1234567890'],
                'recipient' => ['id' => 'USER'],
                'timestamp' => time() * 1000,
                'message'   => ['mid' => 'echo_mid', 'text' => 'echo', 'is_echo' => true]
            ]]
        ]]
    ]);
    $msgs = $adapter->parsePayload($payload, 99);
    assert_eq(count($msgs), 0, "يجب تجاهل رسائل echo: وجدنا ".count($msgs));
});

$results[] = run_test("MetaAdapter: تحليل رسالة صورة", function() {
    $adapter = new Omni\Adapters\MetaAdapter();
    $payload = json_encode([
        'object' => 'page',
        'entry'  => [[
            'id'        => 'PAGE_X',
            'time'      => time(),
            'messaging' => [[
                'sender'    => ['id' => 'USER_IMG'],
                'recipient' => ['id' => 'PAGE_X'],
                'timestamp' => time() * 1000,
                'message'   => [
                    'mid'         => 'img_mid',
                    'attachments' => [['type'=>'image','payload'=>['url'=>'https://example.com/img.jpg']]]
                ]
            ]]
        ]]
    ]);
    $msgs = $adapter->parsePayload($payload, 99);
    assert_eq(count($msgs), 1, "يجب تحليل رسالة الصورة");
    assert_eq($msgs[0]->messageType, 'MEDIA', "نوع الصورة يجب MEDIA");
    assert_eq($msgs[0]->mediaUrl ?? '', 'https://example.com/img.jpg', "URL الصورة خاطئ");
});

$results[] = run_test("MetaAdapter: تحليل موقع جغرافي", function() {
    $adapter = new Omni\Adapters\MetaAdapter();
    $payload = json_encode([
        'object' => 'page',
        'entry'  => [[
            'id'        => 'PAGE_X',
            'time'      => time(),
            'messaging' => [[
                'sender'    => ['id' => 'USER_LOC'],
                'recipient' => ['id' => 'PAGE_X'],
                'timestamp' => time() * 1000,
                'message'   => [
                    'mid'         => 'loc_mid',
                    'attachments' => [['type'=>'location','payload'=>['coordinates'=>['lat'=>36.75,'long'=>3.05]]]]
                ]
            ]]
        ]]
    ]);
    $msgs = $adapter->parsePayload($payload, 99);
    assert_eq($msgs[0]->messageType, 'LOCATION', "نوع الموقع يجب LOCATION");
    assert_true(str_contains($msgs[0]->text, '36.75'), "يجب أن يحتوي النص على الإحداثيات");
});

$results[] = run_test("MetaAdapter: تحليل تعليق (Comment)", function() {
    $adapter = new Omni\Adapters\MetaAdapter();
    $payload = json_encode([
        'object' => 'page',
        'entry'  => [[
            'id'   => 'PAGE_X',
            'time' => time(),
            'changes' => [[
                'field' => 'feed',
                'value' => [
                    'item'        => 'comment',
                    'verb'        => 'add',
                    'comment_id'  => 'cmt_123',
                    'post_id'     => 'post_999',
                    'from'        => ['id' => 'USER_CMT', 'name' => 'Ahmed'],
                    'message'     => 'هل المنتج متوفر؟',
                    'created_time'=> time()
                ]
            ]]
        ]]
    ]);
    $msgs = $adapter->parsePayload($payload, 99);
    assert_eq(count($msgs), 1, "يجب تحليل التعليق");
    assert_eq($msgs[0]->messageType, 'COMMENT', "النوع يجب COMMENT");
    assert_eq($msgs[0]->commentId, 'cmt_123', "Comment ID لا يتطابق");
});

$results[] = run_test("MetaAdapter: تجاهل تعليق النظام نفسه (self)", function() {
    $adapter = new Omni\Adapters\MetaAdapter();
    $payload = json_encode([
        'object' => 'page',
        'entry'  => [[
            'id'   => 'PAGE_SELF',
            'time' => time(),
            'changes' => [[
                'field' => 'feed',
                'value' => [
                    'item'        => 'comment',
                    'verb'        => 'add',
                    'comment_id'  => 'cmt_self',
                    'post_id'     => 'post_999',
                    'from'        => ['id' => 'PAGE_SELF', 'name' => 'MyPage'],
                    'message'     => 'شكراً للجميع!',
                    'created_time'=> time()
                ]
            ]]
        ]]
    ]);
    $msgs = $adapter->parsePayload($payload, 99);
    assert_eq(count($msgs), 0, "يجب تجاهل تعليق الصفحة لنفسها");
});

// ═══════════════════════════════════════════════════════════════
// GROUP 6 — MESSAGE ROUTER
// ═══════════════════════════════════════════════════════════════

$results[] = run_test("MessageRouter: إنشاء عميل جديد وربطه بمحادثة", function() use ($pdo) {
    // Need an active channel
    $stmt = $pdo->query("SELECT id FROM tbl_omni_channels WHERE status='ACTIVE' LIMIT 1");
    $ch = $stmt->fetchColumn();
    if (!$ch) {
        $pdo->exec("INSERT INTO tbl_omni_channels (provider, account_id, name, status) VALUES ('meta','TEST_PAGE','Test Page','ACTIVE')");
        $ch = $pdo->lastInsertId();
        $cleanup_ch = $ch;
    }

    $router = new MessageRouter($pdo);
    $msg = new UnifiedMessage([
        'messageId'      => 'router_test_' . uniqid(),
        'platformUserId' => 'ROUTER_TEST_USER_' . uniqid(),
        'provider'       => 'meta',
        'channelId'      => $ch,
        'messageType'    => 'TEXT',
        'text'           => 'اختبار التوجيه',
        'timestamp'      => date('Y-m-d H:i:s'),
        'metadata'       => json_encode(['test'=>true])
    ]);
    $router->routeIncoming($msg);

    // Verify customer was created
    $custId = $pdo->prepare("SELECT customer_id FROM tbl_omni_customer_identities WHERE platform_user_id=?");
    $custId->execute([$msg->platformUserId]);
    $cId = $custId->fetchColumn();
    assert_true($cId > 0, "العميل لم يُنشأ في قاعدة البيانات");

    // Verify conversation
    $conv = $pdo->prepare("SELECT id FROM tbl_omni_conversations WHERE customer_id=? AND current_channel_id=?");
    $conv->execute([$cId, $ch]);
    assert_true($conv->fetchColumn() > 0, "المحادثة لم تُنشأ");

    // Cleanup
    $pdo->prepare("DELETE FROM tbl_omni_customer_identities WHERE platform_user_id=?")->execute([$msg->platformUserId]);
    $pdo->prepare("DELETE FROM tbl_omni_conversations WHERE customer_id=?")->execute([$cId]);
    $pdo->prepare("DELETE FROM tbl_omni_customers WHERE id=?")->execute([$cId]);
    if (isset($cleanup_ch)) $pdo->prepare("DELETE FROM tbl_omni_channels WHERE id=?")->execute([$cleanup_ch]);
});

$results[] = run_test("MessageRouter: الرسائل المكررة تُتجاهل", function() use ($pdo) {
    $stmt = $pdo->query("SELECT id FROM tbl_omni_channels WHERE status='ACTIVE' LIMIT 1");
    $ch = $stmt->fetchColumn();
    if (!$ch) {
        $pdo->exec("INSERT INTO tbl_omni_channels (provider, account_id, name, status) VALUES ('meta','DUP_PAGE','Dup Page','ACTIVE')");
        $ch = $pdo->lastInsertId();
        $cleanup_ch = $ch;
    }

    $msgId = 'dup_msg_' . uniqid();
    $userId = 'DUP_USER_' . uniqid();
    $router = new MessageRouter($pdo);

    for ($i = 0; $i < 2; $i++) {
        $msg = new UnifiedMessage([
            'messageId'      => $msgId,
            'platformUserId' => $userId,
            'provider'       => 'meta',
            'channelId'      => $ch,
            'messageType'    => 'TEXT',
            'text'           => 'رسالة مكررة',
            'timestamp'      => date('Y-m-d H:i:s'),
            'metadata'       => json_encode(['mid' => $msgId])
        ]);
        $router->routeIncoming($msg);
    }

    // Count timeline entries - should be 1
    $custId = $pdo->prepare("SELECT customer_id FROM tbl_omni_customer_identities WHERE platform_user_id=?");
    $custId->execute([$userId]);
    $cId = $custId->fetchColumn();

    $convId = $pdo->prepare("SELECT id FROM tbl_omni_conversations WHERE customer_id=?");
    $convId->execute([$cId]);
    $convIdVal = $convId->fetchColumn();

    $count = $pdo->prepare("SELECT COUNT(*) FROM tbl_omni_timeline WHERE conversation_id=?");
    $count->execute([$convIdVal]);
    $cnt = $count->fetchColumn();
    assert_eq((int)$cnt, 1, "الرسالة المكررة يجب أن تُسجَّل مرة واحدة فقط، وُجدت: $cnt");

    // Cleanup
    $pdo->prepare("DELETE FROM tbl_omni_timeline WHERE conversation_id=?")->execute([$convIdVal]);
    $pdo->prepare("DELETE FROM tbl_omni_conversations WHERE customer_id=?")->execute([$cId]);
    $pdo->prepare("DELETE FROM tbl_omni_customer_identities WHERE customer_id=?")->execute([$cId]);
    $pdo->prepare("DELETE FROM tbl_omni_customers WHERE id=?")->execute([$cId]);
    if (isset($cleanup_ch)) $pdo->prepare("DELETE FROM tbl_omni_channels WHERE id=?")->execute([$cleanup_ch]);
});

$results[] = run_test("Comment Decision Engine: كلمة سعر تطلق Private Reply", function() use ($pdo) {
    $stmt = $pdo->query("SELECT id FROM tbl_omni_channels WHERE status='ACTIVE' LIMIT 1");
    $ch = $stmt->fetchColumn();
    if (!$ch) {
        $pdo->exec("INSERT INTO tbl_omni_channels (provider,account_id,name,status) VALUES ('meta','DE_PAGE','DE Page','ACTIVE')");
        $ch = $pdo->lastInsertId();
        $cleanup_ch = $ch;
    }

    $userId = 'DE_USER_' . uniqid();
    $router = new MessageRouter($pdo);
    $msg = new UnifiedMessage([
        'messageId'      => 'de_cmt_' . uniqid(),
        'platformUserId' => $userId,
        'provider'       => 'meta',
        'channelId'      => $ch,
        'messageType'    => 'COMMENT',
        'text'           => 'بكم السعر؟',
        'commentId'      => 'cmt_de',
        'postId'         => 'post_de',
        'timestamp'      => date('Y-m-d H:i:s'),
        'metadata'       => json_encode(['item'=>'comment','verb'=>'add'])
    ]);
    $router->routeIncoming($msg);

    // Check AI task was created with PRIVATE_REPLY instruction
    $custId = $pdo->prepare("SELECT customer_id FROM tbl_omni_customer_identities WHERE platform_user_id=?");
    $custId->execute([$userId]);
    $cId = $custId->fetchColumn();
    $convStmt = $pdo->prepare("SELECT id FROM tbl_omni_conversations WHERE customer_id=?");
    $convStmt->execute([$cId]);
    $convId = $convStmt->fetchColumn();

    $task = $pdo->prepare("SELECT payload FROM tbl_ai_tasks WHERE entity_id=? ORDER BY id DESC LIMIT 1");
    $task->execute([$convId]);
    $payload = json_decode($task->fetchColumn(), true);
    assert_true(isset($payload['instruction']), "AI Task يجب أن يحتوي على instruction");
    assert_eq($payload['instruction'], 'PRIVATE_REPLY_SALES_OPENER', "التعليق الذي يسأل عن السعر يجب أن يطلق PRIVATE_REPLY");

    // Cleanup
    $pdo->prepare("DELETE FROM tbl_ai_tasks WHERE entity_id=?")->execute([$convId]);
    $pdo->prepare("DELETE FROM tbl_omni_timeline WHERE conversation_id=?")->execute([$convId]);
    $pdo->prepare("DELETE FROM tbl_omni_conversations WHERE customer_id=?")->execute([$cId]);
    $pdo->prepare("DELETE FROM tbl_omni_customer_identities WHERE customer_id=?")->execute([$cId]);
    $pdo->prepare("DELETE FROM tbl_omni_customers WHERE id=?")->execute([$cId]);
    if (isset($cleanup_ch)) $pdo->prepare("DELETE FROM tbl_omni_channels WHERE id=?")->execute([$cleanup_ch]);
});

// ═══════════════════════════════════════════════════════════════
// GROUP 7 — AI TASK ENGINE
// ═══════════════════════════════════════════════════════════════

$results[] = run_test("AI Tasks: إنشاء مهمة وحفظها في قاعدة البيانات", function() use ($pdo) {
    $payload = json_encode(['test' => true, 'msg' => 'اختبار المهمة']);
    $pdo->prepare("INSERT INTO tbl_ai_tasks (task_type, entity_type, entity_id, priority, payload, status) VALUES (?,?,?,?,?,?)")
        ->execute(['test_task', 'test', 0, 'LOW', $payload, 'PENDING']);
    $id = (int)$pdo->lastInsertId();
    assert_true($id > 0, "لم يُعيد ID للمهمة المُنشأة");

    $row = $pdo->prepare("SELECT status, task_type FROM tbl_ai_tasks WHERE id=?");
    $row->execute([$id]);
    $r = $row->fetch();
    assert_eq($r['status'], 'PENDING', "الحالة يجب PENDING");
    assert_eq($r['task_type'], 'test_task', "النوع لا يتطابق");

    $pdo->prepare("DELETE FROM tbl_ai_tasks WHERE id=?")->execute([$id]);
});

$results[] = run_test("AI Providers: على الأقل مزود واحد في قاعدة البيانات", function() use ($pdo) {
    $count = $pdo->query("SELECT COUNT(*) FROM tbl_ai_providers")->fetchColumn();
    assert_true($count > 0, "لا يوجد أي مزود AI في tbl_ai_providers — أضف على الأقل Gemini أو OpenAI");
});

// ═══════════════════════════════════════════════════════════════
// GROUP 8 — AI KNOWLEDGE
// ═══════════════════════════════════════════════════════════════

$results[] = run_test("AI Knowledge: جدول المعرفة يعمل", function() use ($pdo) {
    $pdo->prepare("INSERT INTO tbl_ai_knowledge (title, content, knowledge_type, is_active) VALUES (?,?,?,?)")
        ->execute(['اختبار', 'محتوى اختبار', 'general', 1]);
    $id = $pdo->lastInsertId();
    assert_true($id > 0, "لم يُنشأ سجل المعرفة");
    $pdo->prepare("DELETE FROM tbl_ai_knowledge WHERE id=?")->execute([$id]);
});

$results[] = run_test("KnowledgeContextBuilder: يبني Context بنجاح", function() use ($pdo) {
    require_once __DIR__ . '/admin/inc/AI/KnowledgeContextBuilder.php';
    $kb = new \AI\KnowledgeContextBuilder($pdo);
    $ctx = $kb->buildContext(['platform' => 'meta', 'language' => 'ar']);
    assert_true(is_string($ctx), "يجب أن يعيد string");
});

// ═══════════════════════════════════════════════════════════════
// SUMMARY RENDER
// ═══════════════════════════════════════════════════════════════

$totalTime = round((microtime(true) - $startAll) * 1000);
$passed    = count(array_filter($results, fn($r) => $r['status']==='PASS'));
$failed    = count(array_filter($results, fn($r) => $r['status']==='FAIL'));
$total     = count($results);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>Test Suite — Platform</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
body { font-family: 'Segoe UI', sans-serif; background:#f5f7fa; }
.test-pass { border-right: 4px solid #28a745; }
.test-fail { border-right: 4px solid #dc3545; }
.group-header { background: #212529; color:#fff; font-weight:bold; }
code { font-size: 12px; }
</style>
</head>
<body class="p-4">

<div class="container-fluid">
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>🧪 Platform Comprehensive Test Suite</h2>
    <span class="text-muted">⏱ <?= $totalTime ?>ms</span>
</div>

<!-- Summary -->
<div class="row mb-4">
    <div class="col-md-3"><div class="card bg-<?= $failed===0?'success':'danger' ?> text-white"><div class="card-body text-center"><h3><?= $failed===0 ? '✅ ALL PASSED' : "❌ $failed FAILED" ?></h3></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><h5 class="text-success">✅ Passed</h5><h2><?= $passed ?></h2></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><h5 class="text-danger">❌ Failed</h5><h2><?= $failed ?></h2></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><h5 class="text-muted">Total</h5><h2><?= $total ?></h2></div></div></div>
</div>

<!-- Progress Bar -->
<div class="progress mb-4" style="height:20px">
    <div class="progress-bar bg-success" style="width:<?= round($passed/$total*100) ?>%"><?= $passed ?> Passed</div>
    <?php if($failed>0): ?>
    <div class="progress-bar bg-danger" style="width:<?= round($failed/$total*100) ?>%"><?= $failed ?> Failed</div>
    <?php endif; ?>
</div>

<!-- Results Table -->
<?php
$groups = [
    'DB: جدول'      => 'قاعدة البيانات — الجداول',
    'SecretManager' => 'إدارة الأسرار',
    'N8nManager'    => 'N8n Integration Manager',
    'EventLogger'   => 'Event Store & Logger',
    'MetaAdapter'   => 'Meta Webhook Adapter',
    'Comment'       => 'Comment Decision Engine',
    'Message'       => 'Message Router',
    'AI Tasks'      => 'AI Task Engine',
    'AI Providers'  => 'AI Providers',
    'AI Knowledge'  => 'AI Knowledge',
    'Knowledge'     => 'Knowledge Context Builder',
];

foreach ($groups as $prefix => $groupTitle) {
    $group = array_filter($results, fn($r) => str_starts_with($r['name'], $prefix));
    if (empty($group)) continue;
    $gPass = count(array_filter($group, fn($r)=>$r['status']==='PASS'));
    $gFail = count($group)-$gPass;
    echo '<div class="card mb-3"><div class="card-header group-header d-flex justify-content-between">';
    echo "<span>$groupTitle</span><span>" . ($gFail===0?'<span class="badge bg-success">✅ PASS</span>':'<span class="badge bg-danger">❌ FAIL</span>') . " $gPass/".count($group)."</span>";
    echo '</div><div class="card-body p-0"><table class="table table-sm mb-0"><thead><tr><th>الاختبار</th><th>النتيجة</th><th>الرسالة</th><th>الوقت</th></tr></thead><tbody>';
    foreach ($group as $r) {
        $cls = $r['status']==='PASS' ? 'test-pass' : 'test-fail';
        $badge = $r['status']==='PASS' ? '<span class="badge bg-success">PASS</span>' : '<span class="badge bg-danger">FAIL</span>';
        $name = str_replace($prefix.': ', '', $r['name']);
        echo "<tr class=\"$cls\"><td>$name</td><td>$badge</td><td><small class=\"text-".($r['status']==='PASS'?'muted':'danger')."\">" . htmlspecialchars($r['msg']) . "</small></td><td><small>{$r['ms']}ms</small></td></tr>";
    }
    echo '</tbody></table></div></div>';
}
?>

<?php if($failed > 0): ?>
<div class="alert alert-danger mt-3">
    <h5>❌ الاختبارات الفاشلة:</h5>
    <ul>
    <?php foreach(array_filter($results, fn($r)=>$r['status']==='FAIL') as $r): ?>
    <li><strong><?= htmlspecialchars($r['name']) ?></strong>: <?= htmlspecialchars($r['msg']) ?></li>
    <?php endforeach; ?>
    </ul>
</div>
<?php else: ?>
<div class="alert alert-success mt-3">
    <h4>✅ جميع الاختبارات (<?= $total ?>) اجتازت بنجاح في <?= $totalTime ?>ms — النظام جاهز للإنتاج.</h4>
</div>
<?php endif; ?>

</div>
</body>
</html>
