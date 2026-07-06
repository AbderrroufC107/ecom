import re

with open('C:/xampp/htdocs/ecom/api/omni/webhook.php', 'r', encoding='utf-8') as f:
    c = f.read()

c = c.replace('$router->route($msg);', '$router->routeIncoming($msg);')

with open('C:/xampp/htdocs/ecom/api/omni/webhook.php', 'w', encoding='utf-8') as f:
    f.write(c)

print('Fixed webhook.php to use routeIncoming.')
