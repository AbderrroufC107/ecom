import re

with open('C:/xampp/htdocs/ecom/api/ai/index.php', 'r', encoding='utf-8') as f:
    c = f.read()

knowledge_route = """
if ($parts[0] === 'knowledge') {
    require_once __DIR__ . '/knowledge.php';
    exit;
}
"""

c = c.replace("if (empty($parts[0]) || $parts[0] !== 'products') {", knowledge_route + "\nif (empty($parts[0]) || $parts[0] !== 'products') {")

with open('C:/xampp/htdocs/ecom/api/ai/index.php', 'w', encoding='utf-8') as f:
    f.write(c)

print('api/ai/index.php updated.')
