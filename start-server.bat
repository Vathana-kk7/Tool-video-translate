@echo off
cd /d "%~dp0"

if not exist "%~dp0storage\framework\uploads" mkdir "%~dp0storage\framework\uploads"

set QUEUE_CONNECTION=database
powershell -NoProfile -Command "$worker = Get-CimInstance Win32_Process | Where-Object { $_.Name -ieq 'php.exe' -and $_.CommandLine -like '*artisan queue:work database*' -and $_.CommandLine -like '*--queue=high,default*' }; if ($worker) { exit 0 } else { exit 1 }"
if errorlevel 1 (
    C:\xampp\php\php.exe artisan queue:restart
    timeout /t 3 /nobreak >nul
    start "Video translation queue worker" /D "%~dp0" C:\xampp\php\php.exe artisan queue:work database --queue=high,default --sleep=2 --timeout=3600 --tries=1000 --backoff=60
)
netstat -ano | findstr /R /C:":8000 .*LISTENING" >nul
if not errorlevel 1 (
    echo API server is already listening on port 8000.
    echo Restart the existing API server to apply the optimized same-drive upload settings.
    echo Queue worker started.
    exit /b 0
)

echo Starting PHP server with video upload support...
C:\xampp\php\php.exe -d "upload_tmp_dir=%~dp0storage\framework\uploads" -d upload_max_filesize=5G -d post_max_size=6G -d max_execution_time=3600 -d max_input_time=3600 -d memory_limit=2G -S 0.0.0.0:8000 -t public