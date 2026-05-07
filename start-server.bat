@echo off
echo Stopping existing PHP servers...
for /f "tokens=5" %%t in ('netstat -ano ^| findstr :8000') do (
    taskkill /f /pid %%t 2>nul
)
timeout /t 2 /nobreak >nul

echo Starting PHP server with video upload support...
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t public ^
    -d upload_max_filesize=5G ^
    -d post_max_size=6G ^
    -d max_execution_time=3600 ^
    -d max_input_time=3600 ^
    -d memory_limit=2G

echo Server started on http://127.0.0.1:8000