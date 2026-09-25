# Search for premium slides in p.php
with open('p.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

for idx, line in enumerate(lines):
    if 'PREMIUM:' in line or '_images' in line or '_video' in line:
        if 'elseif ($slide_type ===' in line or 'if ($slide_type ===' in line or '$imgs =' in line or '$slide_key =' in line:
            print(f"Line {idx+1}: {line.strip()}")
