@echo off
echo ============================================
echo   E-Com WebSocket Server
echo ============================================
echo.
echo Starting WebSocket server on port 9001...
echo.
echo Usage:
echo   1. Double-click this file to start the server
echo   2. Keep this window open while using the admin panel
echo   3. Press Ctrl+C to stop the server
echo.

"C:\xampp\php\php.exe" "%~dp0server.php" %*

pause
