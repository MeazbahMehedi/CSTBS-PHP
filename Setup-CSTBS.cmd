@echo off
setlocal
title CSTBS - Laptop Setup
powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%~dp0bin\setup.ps1"
set "setupResult=%ERRORLEVEL%"
echo.
if not "%setupResult%"=="0" echo Setup could not finish. Read the message above, fix the problem, and run this file again.
pause
exit /b %setupResult%
