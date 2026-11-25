@echo off
title PayTrack Payroll System (Offline)
cd /d "%~dp0"

echo Starting PayTrack (Offline Mode)...

powershell -NoProfile -Command "$c = New-Object System.Net.Sockets.TcpClient; try { $c.Connect('127.0.0.1', 3306); $c.Close(); exit 0 } catch { exit 1 }"
if errorlevel 1 (
    echo.
    echo [WARNING] MySQL is not running on port 3306.
    echo Please start MySQL in XAMPP or Laragon first, then press any key to continue.
    pause
)

powershell -NoProfile -Command "$c = New-Object System.Net.Sockets.TcpClient; try { $c.Connect('127.0.0.1', 8080); $c.Close(); exit 0 } catch { exit 1 }"
if errorlevel 1 (
    start "PayTrack Local Server" /min cmd /c "php spark serve --host 127.0.0.1 --port 8080"
    timeout /t 2 /nobreak >nul
)

start "" "http://127.0.0.1:8080"
exit /b 0
