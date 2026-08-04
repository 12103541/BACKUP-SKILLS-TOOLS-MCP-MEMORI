@echo off
setlocal enabledelayedexpansion
title MY SERVICE - STOP
cd /d "%~dp0"

echo ============================================================
echo   MY SERVICE - Menghentikan server...
echo ============================================================

set "DITEMUKAN=0"

REM Cari PID yang mendengarkan port
for /f "tokens=5" %%p in ('netstat -ano ^| findstr ":8090" ^| findstr "LISTENING"') do (
    set "DITEMUKAN=1"
    echo [INFO] Menghentikan proses PID %%p - port 8090...
    taskkill /PID %%p /F >nul 2>&1
    if !errorlevel!==0 (
        echo [OK] Server dihentikan.
    ) else (
        echo [ERROR] Gagal menghentikan PID %%p.
    )
)

if "!DITEMUKAN!"=="0" (
    echo [INFO] Tidak ada proses di port 8090 - server memang tidak berjalan.
)

REM Verifikasi akhir
netstat -ano | findstr ":8090" | findstr "LISTENING" >nul 2>&1
if %errorlevel%==0 (
    echo [WARN] Port 8090 masih terpakai. Periksa manual.
) else (
    echo [OK] Port 8090 kosong - server berhenti total.
)

pause
endlocal
