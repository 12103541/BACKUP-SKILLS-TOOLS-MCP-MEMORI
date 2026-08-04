@echo off
setlocal enabledelayedexpansion
title MY SERVICE - START
cd /d "%~dp0"

echo ============================================================
echo   MY SERVICE - Menjalankan...
echo ============================================================

REM Cek apakah port sudah terpakai
netstat -ano | findstr ":8090" | findstr "LISTENING" >nul 2>&1
if %errorlevel%==0 (
    echo [INFO] Server sudah berjalan di port 8090.
    pause
    exit /b 0
)

where python >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Python tidak ditemukan di PATH.
    pause
    exit /b 1
)

echo [INFO] Server aktif di http://localhost:8090
echo [INFO] Tutup jendela ini untuk menghentikan server (atau pakai stop.bat)
echo.
python run.py
pause
