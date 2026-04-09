@echo off
:: =============================================================
:: release.bat — Tag and publish a KronosCMS GitHub release
:: Usage:  release.bat
:: Requires: git, gh (GitHub CLI) — both on PATH
:: =============================================================

setlocal EnableDelayedExpansion

echo.
echo ============================================
echo  KronosCMS GitHub Release Tool
echo ============================================
echo.

:: ── 1. Ask for version ──────────────────────────────────────
set /p VERSION="Enter release version (e.g. 0.2.0): "
if "!VERSION!"=="" (
    echo ERROR: Version cannot be empty.
    pause & exit /b 1
)

:: Normalise — strip leading "v" if user typed it
set "VERSION=!VERSION:v=!"
set "TAG=v!VERSION!"

:: ── 2. Confirm ───────────────────────────────────────────────
echo.
echo You are about to release: !TAG!
echo This will:
echo   1. Update KronosVersion.php to !VERSION!
echo   2. git add, commit, push
echo   3. git tag !TAG! and push the tag
echo   4. Create a GitHub release with gh
echo.
set /p CONFIRM="Continue? (y/N): "
if /i NOT "!CONFIRM!"=="y" (
    echo Aborted.
    pause & exit /b 0
)

:: ── 3. Update version constant ───────────────────────────────
set "VER_FILE=%~dp0src\Core\KronosVersion.php"
if not exist "!VER_FILE!" (
    echo ERROR: Cannot find !VER_FILE!
    pause & exit /b 1
)

:: Use PowerShell to do the string replacement safely
powershell -NoProfile -Command ^
  "(Get-Content '!VER_FILE!') -replace \"const VERSION = '[^']+'\", \"const VERSION = '!VERSION!'\" | Set-Content '!VER_FILE!'"

echo [1/5] KronosVersion.php updated to !VERSION!

:: ── 4. Git commit ─────────────────────────────────────────────
git add .
git commit -m "release: !TAG!"
if errorlevel 1 (
    echo ERROR: git commit failed. Are there staged changes?
    pause & exit /b 1
)
echo [2/5] Git commit created.

:: ── 5. Git push ───────────────────────────────────────────────
git push
if errorlevel 1 (
    echo ERROR: git push failed. Check remote and credentials.
    pause & exit /b 1
)
echo [3/5] Pushed to remote.

:: ── 6. Tag ────────────────────────────────────────────────────
git tag "!TAG!"
git push origin "!TAG!"
if errorlevel 1 (
    echo ERROR: Failed to push tag !TAG!.
    pause & exit /b 1
)
echo [4/5] Tag !TAG! pushed.

:: ── 7. GitHub Release via gh CLI ─────────────────────────────
gh release create "!TAG!" ^
  --title "KronosCMS !TAG!" ^
  --notes "## KronosCMS !TAG!^

See [CHANGELOG](https://github.com/TheoSfak/KronosCms/blob/main/CHANGELOG.md) for details." ^
  --latest

if errorlevel 1 (
    echo WARNING: gh release create failed. You can create it manually on GitHub.
) else (
    echo [5/5] GitHub release created.
)

echo.
echo ✓ KronosCMS !TAG! released successfully!
echo   https://github.com/TheoSfak/KronosCms/releases/tag/!TAG!
echo.
pause
endlocal
