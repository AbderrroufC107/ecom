import re
with open('C:/xampp/htdocs/ecom/admin/product-edit.php', 'r', encoding='utf-8') as f:
    c = f.read()

matches = re.findall(r'<ul class="nav nav-tabs[^"]*">.*?</ul>', c, re.DOTALL)
if matches:
    print('TABS HTML:')
    print(matches[0][:1000])
else:
    print('No tabs found')

print('\nContent size:', len(c))
