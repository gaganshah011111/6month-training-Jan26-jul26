@echo off
REM Tyre ERP — export complete database backup for disaster recovery
cd /d "%~dp0.."
echo Exporting full database to database\sql\FULL_DATABASE_BACKUP.sql ...
c:\xampp\php\php.exe tools\db_sync.php backup
if errorlevel 1 (
    echo.
    echo FAILED. Start MySQL in XAMPP Control Panel, then run this file again.
    pause
    exit /b 1
)
echo.
echo Done. Keep a copy of database\sql\FULL_DATABASE_BACKUP.sql in a safe place.
pause
