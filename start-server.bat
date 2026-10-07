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
powershell -NoProfile -Command "$legacy = Get-CimInstance Win32_Process | Where-Object { $_.Name -ieq 'php.exe' -and $_.CommandLine -like '*-S 127.0.0.1:8000*' -and $_.CommandLine -like '*resources/server.php*' }; foreach ($process in $legacy) { Stop-Process -Id $process.ProcessId -Force }; $oldProjectServer = Get-CimInstance Win32_Process | Where-Object { $_.Name -ieq 'php.exe' -and $_.CommandLine -like '*-S 0.0.0.0:8000*' -and $_.CommandLine -like '*storage\framework\uploads*' -and ($_.CommandLine -notlike '*max_execution_time=0*' -or $_.CommandLine -notlike '*max_input_time=0*') }; foreach ($process in $oldProjectServer) { Stop-Process -Id $process.ProcessId -Force }; Start-Sleep -Milliseconds 500; $server = Get-CimInstance Win32_Process | Where-Object { $_.Name -ieq 'php.exe' -and $_.CommandLine -like '*-S 0.0.0.0:8000*' -and $_.CommandLine -like '*upload_max_filesize=5G*' -and $_.CommandLine -like '*post_max_size=6G*' -and $_.CommandLine -like '*max_execution_time=0*' -and $_.CommandLine -like '*max_input_time=0*' }; if ($server) { exit 0 }; $listener = Get-NetTCPConnection -State Listen -LocalPort 8000 -ErrorAction SilentlyContinue; if ($listener) { exit 2 }; exit 1"
if errorlevel 2 (
    echo Port 8000 is occupied by a server without the required upload limits.
    echo Stop that server and run start-server.bat again.
    exit /b 1
)
if errorlevel 1 goto start_api

echo API server is running with support for uploads up to 5GB without a request time limit.
echo Queue worker started.
exit /b 0

:start_api
echo Starting PHP server with video upload support...
C:\xampp\php\php.exe -d "upload_tmp_dir=%~dp0storage\framework\uploads" -d upload_max_filesize=5G -d post_max_size=6G -d max_execution_time=0 -d max_input_time=0 -d memory_limit=2G -S 0.0.0.0:8000 -t public