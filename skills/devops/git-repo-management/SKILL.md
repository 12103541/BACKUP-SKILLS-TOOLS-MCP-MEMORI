---
name: git-repo-management
description: >
  Git repository hygiene, cleanup, and push workflow. Covers cleaning up
  tracked binaries/large files with git rm --cached, fixing .gitignore for
  projects that accumulated junk, presenting a plan before destructive
  operations, and safe push to remote. Use for any git repo management
  task: cleanup, restructure, push, branch management.
tags: [git, github, cleanup, .gitignore, push, repo-hygiene]
---

# Git Repository Management

## Workflow: Concept First

**RULE: For ANY destructive git operation (delete, force-push, rebase, large
untrack, rewrite history), present a concept/plan to the user BEFORE executing.**

The plan should include:
1. Current state summary (remote, branch, what's changed)
2. What will be done (step by step)
3. What will NOT be touched (reassurance about existing data)
4. Ask for approval before proceeding

This is especially important for Indonesian-speaking users who express it as
"berikan saya konsep dahulu" — they want to understand before committing.

For trivial operations (status check, small commit, log), proceed directly.

## Cleaning Up Tracked Files

When a project has files committed before `.gitignore` rules existed (common
with bundled runtimes, binaries, SQL dumps):

### Step 1: Audit tracked files vs desired .gitignore

```bash
# See what should NOT be tracked
git ls-files | grep -E '\.(exe|dat|dll|sql|log)$|vendor/|node_modules/'
```

### Step 2: Fix .gitignore FIRST

Always update `.gitignore` before running `git rm --cached`. If you remove
files from tracking before updating .gitignore, git will warn about files
that will be untracked.

### Step 3: Untrack without deleting

```bash
# Single file
git rm --cached filename.sql

# Entire directory
git rm --cached -r php/

# Multiple patterns
git ls-files | grep -E '\.(exe|dll)$' | xargs git rm --cached
```

**CRITICAL: `git rm --cached` only removes from git index — local files
are NOT deleted.** The user's working copy stays intact.

### Step 4: Verify before commit

```bash
git status --short   # Check D (deleted from index) and M (modified)
```

### Step 5: Commit and push

```bash
git add -A
git commit -m "chore: cleanup repo — untrack binaries, fix .gitignore"
git push origin main
```

## Common .gitignore Patterns

### Laravel + PHP Bundled App (e.g. standalone .exe with PHP)

See `references/laravel-bundled-gitignore.md` for a complete template.

Key exclusions:
- `php/` directory (bundled PHP runtime — binary bloat, not source)
- `*.exe`, `*.dll`, `*.lib`, `*.dat` (binaries)
- `*.sql` (database dumps — sensitive, large)
- `*.bat`, `*.vbs` (Windows scripts — deployment-specific)
- `vendor/`, `node_modules/` (dependencies — regenerate via composer/npm)
- `.env` (credentials — never commit)
- `storage/app/public/content-assignments/` (user uploads)
- `unins000.*` (installer files)
- `install.log`

### Standard Laravel

```gitignore
/node_modules/
/vendor/
.env
storage/logs/*
storage/framework/cache/data/*
storage/framework/views/*.php
!storage/framework/views/.gitkeep
storage/framework/sessions/*
!storage/framework/sessions/.gitkeep
```

## Pitfalls

1. **Order matters**: Fix .gitignore BEFORE `git rm --cached`. Otherwise you
   get "will be untracked" warnings and might accidentally re-add them.

2. **Already-pushed files stay in history**: `git rm --cached` removes from
   the next commit, but the file remains in git history. If the file contains
   secrets, you need `git filter-branch` or BFG Repo-Cleaner (separate task).

3. **.gitignore doesn't retroactively untrack**: Adding a pattern to
   `.gitignore` only prevents future tracking. Already-tracked files must
   be explicitly `git rm --cached`.

4. **Large files in history**: Even after untracking, the git history still
   contains the binary blobs. For repos with many large historical commits,
   consider `git gc --aggressive` or starting fresh (for personal projects).

5. **On Windows (MSYS/git-bash)**: Use forward slashes or MSYS paths
   (`/c/Users/...`). `git rm --cached` works the same.

6. **Never force-push shared repos**: Only use `git push --force` on personal
   repos or after team coordination.

7. **GitHub PAT `workflow` scope**: push rejected with `refusing to allow a
   Personal Access Token to create or update workflow '.github/workflows/...'
   without 'workflow' scope` when the commit touches `.github/workflows/`.
   Fix: user adds `workflow` scope to the token (GitHub Settings → Developer
   settings → Personal access tokens → check `workflow`). Quick unblock:
   `git rm -r --cached .github/`, `git commit --amend`, push — file stays on
   disk, CI just isn't versioned.

8. **MSYS `C:...` junk files at repo root**: in git-bash, redirecting output
   to a Windows absolute path (`cmd > C:\path\file.log`) writes a file
   literally named `C:path\file.log` in the cwd (e.g. `C:laragon....log`).
   Always use forward-slash/MSYS paths in bash redirects; ignore leftover
   files with a `C*` glob. Same class: stray `nul` files from `> nul`-style
   redirects.

## Verification

After push, verify clean state:

```bash
git status          # Should be clean
git ls-files | wc -l   # Confirm reduced file count
git log --oneline -3   # Confirm commit appeared
curl -s -o /dev/null -w "%{http_code}" https://github.com/USER/REPO
```
