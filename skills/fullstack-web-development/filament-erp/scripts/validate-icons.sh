#!/bin/bash
# validate-icons.sh — Check all Filament navigationIcon references against installed blade-heroicons
# Run from project root: bash scripts/validate-icons.sh
#
# Exit codes:
#   0 = all icons valid
#   1 = one or more icons missing

set -euo pipefail

SVG_DIR="vendor/blade-ui-kit/blade-heroicons/resources/svg"
FILAMENT_DIR="app/Filament"

if [ ! -d "$SVG_DIR" ]; then
    echo "ERROR: $SVG_DIR not found. Run from project root."
    exit 2
fi

# Get all available SVG names (strip .svg, already have prefix like c-, m-, s-)
AVAILABLE=$(ls "$SVG_DIR" | sed 's/\.svg//' | sort)

# Extract all heroicon references from Filament code
ICONS=$(grep -rn "heroicon" "$FILAMENT_DIR" --include="*.php" | \
    grep -oP "heroicon-[oms]-[a-z0-9-]+" | sort -u)

MISSING=0
TOTAL=0

echo "=== Filament Icon Validation ==="
echo "Available SVGs: $(echo "$AVAILABLE" | wc -l)"
echo "Icon references in code: $(echo "$ICONS" | grep -c .)"
echo ""

for ICON in $ICONS; do
    TOTAL=$((TOTAL + 1))
    # Map Filament icon to SVG filename
    # heroicon-o-X → c-X (outline 24x24)
    # heroicon-m-X → m-X (mini 16x16)
    # heroicon-s-X → s-X (solid 24x24)
    PREFIX=$(echo "$ICON" | grep -oP 'heroicon-\K[oms]')
    NAME=$(echo "$ICON" | sed "s/heroicon-$PREFIX-//")
    
    case "$PREFIX" in
        o) SVG_NAME="c-$NAME" ;;
        m) SVG_NAME="m-$NAME" ;;
        s) SVG_NAME="s-$NAME" ;;
    esac
    
    if echo "$AVAILABLE" | grep -qx "$SVG_NAME"; then
        echo "  ✅ $ICON → $SVG_NAME.svg"
    else
        echo "  ❌ $ICON → $SVG_NAME.svg NOT FOUND"
        MISSING=$((MISSING + 1))
    fi
done

echo ""
echo "=== Result: $TOTAL icons checked, $MISSING missing ==="

if [ $MISSING -gt 0 ]; then
    echo "ACTION REQUIRED: Fix missing icons or entire admin panel will crash."
    exit 1
else
    echo "All icons valid! ✅"
    exit 0
fi
