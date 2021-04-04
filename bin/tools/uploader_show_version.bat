@echo off
"%~dp0\..\php\php.exe" "%~dp0\..\..\src\uploader.php" get_version %*
echo.
pause
