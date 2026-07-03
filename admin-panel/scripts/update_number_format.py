import re
from pathlib import Path

base = Path(r'd:\KARN FOLDER\New folder\food\FoodFlow\admin-panel')
patterns = [
    # number_format($x, 2, '.', '')
    (re.compile(r"number_format\(\s*(.+?)\s*,\s*2\s*,\s*'([^']*)'\s*,\s*'([^']*)'\s*\)", re.S),
     r"number_format(\1, App\\Models\\AppSetting::getValue('currency_decimals', 3), '\2', '\3')"),
    (re.compile(r"number_format\(\s*(.+?)\s*,\s*2\s*,\s*\"([^\"]*)\"\s*,\s*\"([^\"]*)\"\s*\)", re.S),
     r"number_format(\1, App\\Models\\AppSetting::getValue('currency_decimals', 3), \"\2\", \"\3\")"),
    # number_format($x, 2, '.')
    (re.compile(r"number_format\(\s*(.+?)\s*,\s*2\s*,\s*'([^']*)'\s*\)", re.S),
     r"number_format(\1, App\\Models\\AppSetting::getValue('currency_decimals', 3), '\2')"),
    (re.compile(r"number_format\(\s*(.+?)\s*,\s*2\s*,\s*\"([^\"]*)\"\s*\)", re.S),
     r"number_format(\1, App\\Models\\AppSetting::getValue('currency_decimals', 3), \"\2\")"),
    # number_format($x, 2)
    (re.compile(r"number_format\(\s*(.+?)\s*,\s*2\s*\)", re.S),
     r"number_format(\1, App\\Models\\AppSetting::getValue('currency_decimals', 3))"),
]

changed_files = []
for path in base.rglob('*.php'):
    if 'vendor' in path.parts:
        continue
    text = path.read_text(encoding='utf8')
    new = text
    for pat, repl in patterns:
        new = pat.sub(repl, new)
    if new != text:
        path.write_text(new, encoding='utf8')
        changed_files.append(str(path.relative_to(base)))

print('Modified files:', len(changed_files))
for f in changed_files:
    print(f)
