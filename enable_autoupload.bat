@echo off

rem Require admin privileges to use nssm.
if not "%1"=="am_admin" (powershell start -verb runas '%0' am_admin & exit /b)
cd /D %~dp0

rem Remove old dsr_uploader service.
"%cd%\bin\nssm\nssm.exe" stop dsr_uploader
"%cd%\bin\nssm\nssm.exe" remove dsr_uploader confirm

rem Enable automatic background Direct Strike replays uploads to ds-rating.com
"%cd%\bin\nssm\nssm.exe" install dsr_uploader "%cd%\bin\php\php.exe"
"%cd%\bin\nssm\nssm.exe" set dsr_uploader AppParameters """%cd%\src\service.php"""
rem Service can't know current user SC2 folder path. Passing it into service app directory.
"%cd%\bin\nssm\nssm.exe" set dsr_uploader AppDirectory """%USERPROFILE%"""
"%cd%\bin\nssm\nssm.exe" set dsr_uploader AppExit Default Restart
"%cd%\bin\nssm\nssm.exe" set dsr_uploader AppStdout "%cd%\src\log_uploads.txt"
"%cd%\bin\nssm\nssm.exe" set dsr_uploader AppStderr "%cd%\src\log_errors.txt"
"%cd%\bin\nssm\nssm.exe" set dsr_uploader AppThrottle 10000
"%cd%\bin\nssm\nssm.exe" set dsr_uploader AppTimestampLog 1
"%cd%\bin\nssm\nssm.exe" set dsr_uploader Description "Automatically upload new Direct Strike replays to ds-rating.com"
"%cd%\bin\nssm\nssm.exe" set dsr_uploader DisplayName "DSR Uploader"
"%cd%\bin\nssm\nssm.exe" set dsr_uploader ObjectName LocalSystem
"%cd%\bin\nssm\nssm.exe" set dsr_uploader Start SERVICE_AUTO_START
"%cd%\bin\nssm\nssm.exe" set dsr_uploader Type SERVICE_WIN32_OWN_PROCESS
"%cd%\bin\nssm\nssm.exe" start dsr_uploader
