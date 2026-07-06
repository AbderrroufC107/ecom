import os
import secrets
import base64

config_path = 'C:/xampp/htdocs/ecom/admin/inc/config.php'
with open(config_path, 'r', encoding='utf-8') as f:
    c = f.read()

if 'APP_SECRET_KEY' not in c:
    key = base64.b64encode(secrets.token_bytes(32)).decode('utf-8')
    if '?>' in c:
        c = c.replace('?>', f"define('APP_SECRET_KEY', '{key}');\n?>")
    else:
        c += f"\ndefine('APP_SECRET_KEY', '{key}');\n"
    
    with open(config_path, 'w', encoding='utf-8') as f:
        f.write(c)
    print('APP_SECRET_KEY added.')
else:
    print('APP_SECRET_KEY already exists.')
