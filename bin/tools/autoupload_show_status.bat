@echo off

rem Require admin privileges to use nssm.
if not "%1"=="am_admin" (powershell start -verb runas '%0' am_admin & exit /b)
cd /D %~dp0

"%cd%\..\nssm\nssm.exe" status dsr_uploader
pause
