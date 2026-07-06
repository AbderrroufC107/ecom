import re
from pathlib import Path

root = Path('.').resolve()
files = []
for pattern in ['**/*.php', '**/*.html', '**/*.htm']:
    files.extend(root.glob(pattern))

link_re = re.compile(r'''(?:href|src)\s*=\s*(["'])(.*?)\1''', re.I)
ignore_prefixes = ('http://', 'https://', 'mailto:', 'tel:', 'javascript:', 'data:', '//', '#')
issues = []
for f in files:
    try:
        text = f.read_text(encoding='utf-8', errors='ignore')
    except Exception:
        continue
    for m in link_re.finditer(text):
        target = m.group(2).strip()
        if not target or any(target.startswith(p) for p in ignore_prefixes):
            continue
        cleaned = target.split('#', 1)[0].split('?', 1)[0]
        if target.startswith('/'):
            candidate = root / cleaned.lstrip('/')
        else:
            candidate = (f.parent / cleaned).resolve()
        if not candidate.exists():
            issues.append((str(f.relative_to(root)), target))

for rel, target in issues[:200]:
    print(f'{rel} -> {target}')
print(f'TOTAL_BROKEN_LINKS={len(issues)}')
