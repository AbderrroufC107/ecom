import os
import re

for root, dirs, files in os.walk('C:/xampp/htdocs/ecom/admin'):
    for file in files:
        if file.endswith('.php'):
            path = os.path.join(root, file)
            with open(path, 'r', encoding='utf-8', errors='ignore') as f:
                content = f.read()
                if re.search(r'JOIN\s+tbl_[a-z_]+\s+[a-zA-Z1-9_]*\s+ON', content, re.I):
                    print(file)
