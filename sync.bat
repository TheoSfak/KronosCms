@echo off
:: =============================================================
:: sync.bat — Copy KronosCMS to XAMPP htdocs for local testing
:: Usage:  sync.bat
:: =============================================================

set "SRC=%~dp0"
set "DEST=C:\xampp\htdocs\KronosCMS"

echo.
echo [KronosCMS] Syncing to %DEST% ...
echo.

robocopy "%SRC%" "%DEST%" ^
  /E ^
  /XD ".git" "vendor" "node_modules" "storage\cache\update-tmp" "storage\cache\pkg-tmp" ^
  /XF ".env" "config\app.php" "*.bat" "*.sh" ^
  /NFL /NDL /NJH /NJS /nc /ns /np

echo.
echo [KronosCMS] Sync complete.
echo.
pause
