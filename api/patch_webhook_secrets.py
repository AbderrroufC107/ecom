import re

with open('C:/xampp/htdocs/ecom/api/omni/webhook.php', 'r', encoding='utf-8') as f:
    c = f.read()

# Add SecretManager inclusion
if 'Security/SecretManager.php' not in c:
    c = c.replace('require_once __DIR__ . \'/../../admin/inc/Omni/MessageRouter.php\';', 
                  'require_once __DIR__ . \'/../../admin/inc/Omni/MessageRouter.php\';\nrequire_once __DIR__ . \'/../../admin/inc/Security/SecretManager.php\';')

# Replace webhook secret lookup
old_secret_check = """        $stmt = $pdo->prepare("SELECT id, webhook_secret FROM tbl_omni_channels WHERE provider = 'meta' AND account_id = ? AND status = 'ACTIVE'");
        $stmt->execute([$pageId]);
        $channel = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$channel) {
            http_response_code(404);
            exit('Channel not found or inactive');
        }

        if (!$adapter->validateSignature($payload, $signature, $channel['webhook_secret'] ?? '')) {"""

new_secret_check = """        $stmt = $pdo->prepare("SELECT id FROM tbl_omni_channels WHERE provider = 'meta' AND account_id = ? AND status = 'ACTIVE'");
        $stmt->execute([$pageId]);
        $channel = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$channel) {
            http_response_code(404);
            exit('Channel not found or inactive');
        }

        $secretManager = new \\Security\\SecretManager($pdo);
        $webhookSecret = $secretManager->getSecret("meta_{$channel['id']}_webhook_secret");

        if (!$adapter->validateSignature($payload, $signature, $webhookSecret ?? '')) {"""

c = c.replace(old_secret_check, new_secret_check)

with open('C:/xampp/htdocs/ecom/api/omni/webhook.php', 'w', encoding='utf-8') as f:
    f.write(c)

print('webhook.php updated.')
