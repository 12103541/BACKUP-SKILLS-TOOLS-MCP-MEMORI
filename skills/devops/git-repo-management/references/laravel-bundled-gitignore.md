# .gitignore for Laravel + Bundled PHP Runtime
# Use this when the repo contains a bundled PHP binary (e.g. portable .exe app)
# that should NOT be tracked.

# ─── Windows ───
nul
Thumbs.db
Desktop.ini
*.log

# ─── PHP Binaries (bundled runtime) ───
*.exe
*.dat
*.vbs
*.dll
*.lib
php/

# ─── Laravel Core ───
/node_modules/
/vendor/
.env
storage/logs/*
storage/framework/cache/data/*
storage/framework/views/*.php
!storage/framework/views/.gitkeep
storage/framework/sessions/*
!storage/framework/sessions/.gitkeep

# ─── User Uploads (can be large, sensitive) ───
storage/app/public/content-assignments/

# ─── Large / Sensitive Files ───
*.sql
*.bat
unins000.*
install.log

# ─── Git Embedded ───
.freebuff/

# ─── OS / IDE ───
.DS_Store
.idea/
.vscode/
