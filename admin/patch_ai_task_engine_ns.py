import re

with open('C:/xampp/htdocs/ecom/admin/inc/AI/AiTaskEngine.php', 'r', encoding='utf-8') as f:
    c = f.read()

c = c.replace('new \\KnowledgeContextBuilder', 'new \\AI\\KnowledgeContextBuilder')

with open('C:/xampp/htdocs/ecom/admin/inc/AI/AiTaskEngine.php', 'w', encoding='utf-8') as f:
    f.write(c)

print("Namespace fixed.")
