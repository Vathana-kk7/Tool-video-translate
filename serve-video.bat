@echo off
REM Kill existing PHP processes on port 8000
for /f "tokens=5" %%t in ('netstat -ano ^| findstr :8000') do (
    taskkill /f /pid %%t 2>nul
)

REM Start new PHP server with increased limits
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t public -d upload_max_filesize=5G -d post_max_size=6G -d max_execution_time=3600 -d max_input_time=3600 -d memory_limit=2G