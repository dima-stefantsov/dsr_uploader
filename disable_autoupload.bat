@echo off

rem Require admin privileges to use nssm.
if not "%1"=="am_admin" (powershell start -verb runas '%0' am_admin & exit /b)
cd /D %~dp0

rem Disable/Uninstall automatic background Direct Strike replays uploads to ds-rating.com
"%cd%\bin\nssm\nssm.exe" stop dsr_uploader
"%cd%\bin\nssm\nssm.exe" remove dsr_uploader confirm

rem pause
