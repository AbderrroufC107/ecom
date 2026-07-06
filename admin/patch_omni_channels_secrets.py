import re

with open('C:/xampp/htdocs/ecom/admin/omni-channels.php', 'r', encoding='utf-8') as f:
    c = f.read()

# Replace the INSERT logic
old_insert = """if (isset($_POST['add_channel'])) {
    $stmt = $pdo->prepare("INSERT INTO tbl_omni_channels (channel_type, provider, account_name, account_id, access_token, webhook_secret) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$_POST['channel_type'], $_POST['provider'], $_POST['account_name'], $_POST['account_id'], $_POST['access_token'], $_POST['webhook_secret']]);
    echo "<script>window.location='omni-channels.php';</script>";
}"""

new_insert = """if (isset($_POST['add_channel'])) {
    require_once('inc/Security/SecretManager.php');
    $secretManager = new \\Security\\SecretManager($pdo);
    
    // Insert into channels without tokens
    $stmt = $pdo->prepare("INSERT INTO tbl_omni_channels (channel_type, provider, account_name, account_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_POST['channel_type'], $_POST['provider'], $_POST['account_name'], $_POST['account_id']]);
    $channelId = $pdo->lastInsertId();
    
    // Save secrets securely
    $provider = $_POST['provider'];
    if (!empty($_POST['access_token'])) {
        $secretManager->setSecret("{$provider}_{$channelId}_access_token", $provider, $_POST['access_token']);
    }
    if (!empty($_POST['webhook_secret'])) {
        $secretManager->setSecret("{$provider}_{$channelId}_webhook_secret", $provider, $_POST['webhook_secret']);
    }
    
    echo "<script>window.location='omni-channels.php';</script>";
}"""

c = c.replace(old_insert, new_insert)

with open('C:/xampp/htdocs/ecom/admin/omni-channels.php', 'w', encoding='utf-8') as f:
    f.write(c)
print('omni-channels.php updated.')
