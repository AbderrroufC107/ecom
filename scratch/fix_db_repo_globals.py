import os
import re

def fix_file(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # Find all function/method declarations that contain $dbRepo inside them
    # We will simply look for { and insert `global $dbRepo;` if $dbRepo-> is used.
    
    # Actually, a safer regex: 
    # Match `function anyName(...) {` and if the block contains `$dbRepo->`, insert `global $dbRepo;` at the start.
    
    lines = content.split('\n')
    in_function = False
    function_start_idx = -1
    brace_level = 0
    
    modified = False
    
    for i, line in enumerate(lines):
        if re.search(r'\bfunction\b\s+.*?\(.*?\)\s*(\{)?', line) or (in_function == False and '{' in line and function_start_idx != -1):
            if not in_function:
                # Naive: just check if line has 'function' and '{'
                # If not, maybe next line has '{'
                pass
                
    # Instead of complex parsing, let's just do:
    # Find all lines with $dbRepo->
    # For each file, if it has $dbRepo->, we can just replace `function xxx(...) {` with `function xxx(...) { global $dbRepo;`
    
    new_content = re.sub(
        r'(function\s+[^{]+\{)',
        r'\1 global $dbRepo;',
        content
    )
    
    if new_content != content:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(new_content)
        return True
    return False

# Since the regex `function\s+[^{]+\{` might be too aggressive, let's refine:
def fix_file_better(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
        
    if '$dbRepo->' not in content:
        return False
        
    # Find functions that don't have global $dbRepo;
    # We will just inject it into every function in the file to be safe if the file uses $dbRepo.
    # PHP allows `global $var;` even if unused.
    
    new_content = re.sub(
        r'(function\s+[a-zA-Z0-9_]+\s*\([^)]*\)\s*(?::\s*[a-zA-Z0-9_?|]+)?\s*\{)',
        r'\1 global $dbRepo;',
        content
    )
    
    if new_content != content:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(new_content)
        return True
    return False

count = 0
for root, dirs, files in os.walk('C:/xampp/htdocs/ecom/admin'):
    for file in files:
        if file.endswith('.php'):
            if fix_file_better(os.path.join(root, file)):
                count += 1
print(f"Fixed {count} files.")
