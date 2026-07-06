import re

with open('C:/xampp/htdocs/ecom/admin/inc/AI/AiTaskEngine.php', 'r', encoding='utf-8') as f:
    c = f.read()

old_kb_call = """$knowledge = $kb->buildContext(null, $payload['channel_id'] ?? null, 'ar');"""
new_kb_call = """$knowledge = $kb->buildContext(['platform' => 'meta', 'language' => 'ar']);"""

c = c.replace(old_kb_call, new_kb_call)

with open('C:/xampp/htdocs/ecom/admin/inc/AI/AiTaskEngine.php', 'w', encoding='utf-8') as f:
    f.write(c)
print('Fixed KB call')
