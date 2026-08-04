#!/usr/bin/env bash
# Inventory a cloned agent-skill repo. Usage: bash inventory.sh <repo-dir>
# Prints: skill counts per agent dir, SKILL.md line stats, sizes, frontmatter, overlap.
set -u
D="${1:?usage: inventory.sh <repo-dir>}"
cd "$D" || exit 1

echo "=== SKILL.md count (excl node_modules) ==="
find . -name "SKILL.md" -not -path "*/node_modules/*" | wc -l

echo "=== agent dirs present ==="
for d in .agents/skills .claude/skills .cursor/rules .codex/prompts .claude/commands .github/prompts; do
  [ -d "$d" ] && echo "$(ls "$d" | wc -l) $d"
done

echo "=== root agent contract files ==="
find . -maxdepth 2 \( -name "AGENTS.md" -o -name "CLAUDE.md" -o -name ".cursorrules" \) -not -path "*/node_modules/*" 2>/dev/null

echo "=== biggest SKILL.md files ==="
find . -name "SKILL.md" -not -path "*/node_modules/*" -exec wc -l {} + | sort -rn | head -10

echo "=== total size + heaviest skill dirs ==="
du -sh . 2>/dev/null | cut -f1
find . -mindepth 2 -maxdepth 3 -name "SKILL.md" -not -path "*/node_modules/*" -exec dirname {} \; | while read -r s; do du -sh "$s" 2>/dev/null; done | sort -rh | head -8

echo "=== frontmatter coverage (name/description) ==="
total=$(find . -name "SKILL.md" -not -path "*/node_modules/*" | wc -l)
with_name=$(grep -l "^name:" $(find . -name "SKILL.md" -not -path "*/node_modules/*") 2>/dev/null | wc -l)
with_desc=$(grep -l "^description:" $(find . -name "SKILL.md" -not -path "*/node_modules/*") 2>/dev/null | wc -l)
echo "name: $with_name/$total  description: $with_desc/$total"

echo "=== PROVENANCE.md (vendored skills) ==="
find . -name "PROVENANCE.md" -not -path "*/node_modules/*"

echo "=== scripts/ dirs ==="
find . -maxdepth 3 -type d -name scripts -not -path "*/node_modules/*" | wc -l
