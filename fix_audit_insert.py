import re

with open('C:/xampp/htdocs/ecom/admin/inc/audit.php', 'r', encoding='utf-8') as f:
    c = f.read()

# I will replace the insert statement inside _audit_insert
c = c.replace("""        $stmt = $pdo->prepare("
            INSERT INTO tbl_audit_log
            (entity_type, entity_id, action_type, performed_by_type, performed_by_id, old_value, new_value, ip_address, user_agent, source, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$entity_type, $entity_id, $action_type, $performed_by_type, $performed_by_id, $old_value, $new_value, $ip_address, $user_agent, $source]);""", """        $audit_ref = strtoupper(uniqid('AUD-'));
        $stmt = $pdo->prepare("
            INSERT INTO tbl_audit_log
            (audit_ref, entity_type, entity_id, action_type, performed_by_type, performed_by_id, old_value, new_value, ip_address, user_agent, source, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$audit_ref, $entity_type, $entity_id, $action_type, $performed_by_type, $performed_by_id, $old_value, $new_value, $ip_address, $user_agent, $source]);""")

with open('C:/xampp/htdocs/ecom/admin/inc/audit.php', 'w', encoding='utf-8') as f:
    f.write(c)

print('Fixed _audit_insert.')
