#!/bin/bash
# Verify all imports in a React project resolve correctly
# Usage: ./verify_imports.sh [project_root]

PROJECT_ROOT="${1:-.}"
cd "$PROJECT_ROOT/frontend/src" || exit 1

echo "=== Checking React Import Paths ==="
echo ""

ERRORS=0

# Find all JSX files and check their imports
for file in $(find . -name "*.jsx" -o -name "*.js" | grep -v node_modules); do
  # Extract import statements
  imports=$(grep "^import.*from" "$file" | grep -v "react" | grep -v "lucide" | grep -v "recharts")
  
  if [ -z "$imports" ]; then
    continue
  fi
  
  # Get directory of current file
  file_dir=$(dirname "$file")
  
  while IFS= read -r import_line; do
    # Extract path from import statement
    import_path=$(echo "$import_line" | sed -n "s/.*from ['\"]\\(.*\\)['\"].*/\\1/p")
    
    # Skip node_modules and external packages
    if [[ "$import_path" != "../*" ]] && [[ "$import_path" != "."/* ]]; then
      continue
    fi
    
    # Resolve relative path
    resolved_path=$(cd "$file_dir" && realpath -m "$import_path" 2>/dev/null || echo "")
    
    # Check if file exists (try .jsx, .js extensions)
    if [ -f "${resolved_path}.jsx" ] || [ -f "${resolved_path}.js" ] || [ -f "$resolved_path" ]; then
      echo "✓ $file → $import_path"
    else
      echo "❌ MISSING: $file imports '$import_path' but file not found"
      ((ERRORS++))
    fi
  done <<< "$imports"
done

echo ""
echo "=== Summary ==="
if [ $ERRORS -eq 0 ]; then
  echo "✅ All imports resolve correctly!"
  exit 0
else
  echo "🚨 Found $ERRORS import errors"
  exit 1
fi