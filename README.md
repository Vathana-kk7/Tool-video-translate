# Chinese to Khmer Video Translator

A full-stack web application that translates Chinese speech in videos to natural-sounding Khmer voice while preserving the original video.

## 🌟 Features

- Upload MP4, AVI, MOV, MKV, WebM videos (max 500MB)
- Automatic Chinese speech-to-text using OpenAI Whisper
- Translation to Khmer using Google Translate
- Natural Khmer voice generation using Azure Text-to-Speech
- Merge Khmer audio with original video using FFmpeg
- Real-time processing status updates
- Download translated videos
- Modern, responsive UI with Tailwind CSS

## 🛠️ Tech Stack

**Backend (Laravel)**
- Laravel 10
- PHP 8.2+
- MySQL
- Redis (for queues)
- FFmpeg (with PHP-FFMpeg library)

**AI Services**
- OpenAI Whisper API (speech-to-text)
- Google Translate API (translation)
- Azure Cognitive Services TTS (text-to-speech)

**Frontend (React)**
- React 18 + Vite
- Tailwind CSS
- Zustand (state management)
- React Router v6
- Axios (HTTP client)
- React Hot Toast (notifications)
- React Dropzone (file upload)

## 📋 Prerequisites

Before running the application, ensure you have:

- **PHP 8.2+** with extensions: pdo_mysql, mbstring, bcmath, gd, exif, fileinfo
- **Composer** (dependency manager for PHP)
- **Node.js 18+** and npm
- **FFmpeg** installed and accessible in your PATH
- **MySQL 8+** database
- **Redis** (for queue processing)
- **API Keys**:
  - OpenAI API key (Whisper)
  - Google Cloud Translation API key
  - Azure Cognitive Services TTS key

## 🚀 Installation

### 1. Clone and Setup Backend

```bash
# Navigate to Laravel directory
cd /path/to/laravel_php/tool_ai

# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 2. Configure Environment Variables

Edit the `.env` file and add your API keys:

```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password

# AI Services
OPENAI_API_KEY=your_openai_api_key_here
GOOGLE_TRANSLATE_API_KEY=your_google_translate_api_key
AZURE_TTS_KEY=your_azure_tts_key
AZURE_TTS_REGION=eastasia

# FFmpeg Paths (Windows)
FFMPEG_PATH=C:\\ffmpeg\\bin\\ffmpeg.exe
FFPROBE_PATH=C:\\ffmpeg\\bin\\ffprobe.exe

# Queue
QUEUE_CONNECTION=database
```

### 3. Set Up Database

```bash
# Create the database
mysql -u username -p
# CREATE DATABASE video_translation;
# exit

# Run migrations
php artisan migrate

# (Optional) Seed database
php artisan db:seed
```

### 4. Configure Storage

```bash
# Create storage symlink (for public access)
php artisan storage:link

# Set proper permissions (Linux/Mac)
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

### 5. Install Frontend Dependencies

```bash
cd frontend
npm install
```

### 6. Configure Frontend

Edit `frontend/.env`:

```env
VITE_API_URL=http://localhost:8000/api
```

### 7. Build Frontend Assets

```bash
cd frontend
npm run build

# Or for development, use:
npm run dev
```

## 🔧 Configuration

### FFmpeg Setup

1. Download FFmpeg from https://ffmpeg.org/download.html
2. Extract and add to PATH:
   - Windows: Add `C:\ffmpeg\bin` to system PATH
   - Linux: `sudo apt install ffmpeg`
   - Mac: `brew install ffmpeg`

### Queue Worker

Start the queue worker to process videos in the background:

```bash
# In a separate terminal
php artisan queue:work --tries=3 --timeout=3600
```

### Laravel Development Server

```bash
# Start the Laravel server
php artisan serve --host=0.0.0.0 --port=8000
```

## 📡 API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/videos/upload` | Upload video for translation |
| GET | `/api/videos/{id}/status` | Get processing status |
| GET | `/api/videos/{id}/download` | Download translated video |
| GET | `/api/videos` | List all videos |
| GET | `/api/videos/{id}` | Get video details |

### Example Upload Request

```bash
curl -X POST http://localhost:8000/api/videos/upload \
  -F "video=@/path/to/video.mp4"
```

### Example Status Request

```bash
curl http://localhost:8000/api/videos/1/status
```

## 🏃 Running the Application

### Development Mode

**Terminal 1 - Laravel Backend:**
```bash
cd /path/to/laravel_php/tool_ai
php artisan serve
```

**Terminal 2 - Queue Worker:**
```bash
cd /path/to/laravel_php/tool_ai
php artisan queue:work
```

**Terminal 3 - Frontend:**
```bash
cd /path/to/laravel_php/tool_ai/frontend
npm run dev
```

Access the app at: http://localhost:5173

### Production Build

```bash
cd frontend
npm run build

# The built assets will be in frontend/dist/
# Copy to Laravel public directory or configure web server to serve them
```

## 🚀 Deployment

### Using Apache

1. Set up virtual host pointing to Laravel's `public` directory
2. Enable mod_rewrite
3. Configure SSL certificate for HTTPS
4. Set proper file permissions:
   ```bash
   chown -R www-data:www-data /path/to/laravel
   chmod -R 755 /path/to/laravel/storage
   ```

### Using Nginx

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/video-translator/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Database Migration (Production)

```bash
php artisan migrate --force
```

### Starting Queue Worker in Production

```bash
# Using Supervisor for process management
sudo nano /etc/supervisor/conf.d/video-translator.conf
```

```ini
[program:video-translator-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/video-translator/artisan queue:work --sleep=3 --tries=3 --timeout=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/video-translator/queue.log
```

```bash
# Update supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start video-translator-queue:*
```

## 🐛 Troubleshooting

### FFmpeg Not Found

If you see "FFmpeg not found" errors:
- Ensure FFmpeg is installed and in your PATH
- Update `.env` with absolute paths to ffmpeg and ffprobe
- Test with `ffmpeg -version` in terminal

### Queue Jobs Failing

```bash
# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

### Storage Permissions

```bash
# Fix permissions (Linux/Mac)
chmod -R 775 storage
chmod -R 775 bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### API Limits

Be aware of API rate limits:
- **OpenAI Whisper**: ~50 requests/hour for free tier
- **Google Translate**: ~500,000 chars/month free
- **Azure TTS**: 5M characters/month free

Consider implementing:
- Request queuing with rate limiting
- User authentication for quota management
- Caching of translations

## 🔐 Security Considerations

1. **Validate Uploads**: Only accept video formats (mp4, avi, mov, mkv, webm)
2. **File Size Limits**: Current limit is 500MB per upload
3. **API Keys**: Never commit to git, use environment variables
4. **Authentication**: Add Laravel Sanctum for user auth in production
5. **Rate Limiting**: Implement on API endpoints
6. **HTTPS**: Always use SSL in production

## 📁 Project Structure

```
tool_ai/
├── app/
│   ├── Http/Controllers/Api/VideoController.php
│   ├── Jobs/ProcessVideoJob.php
│   ├── Services/
│   │   ├── FFmpegService.php
│   │   ├── WhisperService.php
│   │   ├── TranslateService.php
│   │   └── TTSService.php
│   └── Models/Video.php
├── database/migrations/
│   ├── create_videos_table.php
│   └── create_jobs_table.php
├── routes/api.php
├── storage/
│   ├── app/audio/
│   ├── app/videos/
│   └── app/processed/
└── frontend/
    ├── src/
    │   ├── components/
    │   │   ├── Navbar.jsx
    │   │   ├── UploadCard.jsx
    │   │   ├── ProgressBar.jsx
    │   │   ├── VideoPreview.jsx
    │   │   ├── AudioPlayer.jsx
    │   │   ├── DownloadButton.jsx
    │   │   └── SubtitleDisplay.jsx
    │   ├── pages/
    │   │   ├── Home.jsx
    │   │   ├── Upload.jsx
    │   │   ├── Result.jsx
    │   │   └── Videos.jsx
    │   ├── services/api.js
    │   ├── services/videoService.js
    │   ├── contexts/videoStore.js
    │   └── App.jsx
    ├── package.json
    └── vite.config.js
```

## 📊 Database Schema

```sql
CREATE TABLE videos (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    original_video VARCHAR(255),
    extracted_audio VARCHAR(255) NULL,
    transcribed_text TEXT NULL,
    translated_text TEXT NULL,
    khmer_audio VARCHAR(255) NULL,
    final_video VARCHAR(255) NULL,
    subtitle_file VARCHAR(255) NULL,
    status ENUM('pending','processing','extracting_audio','transcribing','translating','generating_tts','merging','completed','failed') DEFAULT 'pending',
    error_message TEXT NULL,
    progress INT DEFAULT 0,
    user_id BIGINT NULL,
    segments JSON NULL,
    audio_segments JSON NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

## 🔄 Processing Pipeline

1. **Upload** → Video saved to storage
2. **Extract Audio** → FFmpeg extracts audio track
3. **Transcribe** → OpenAI Whisper converts Chinese speech to text
4. **Translate** → Google Translate converts Chinese to Khmer
5. **Generate Speech** → Azure TTS creates Khmer audio
6. **Merge** → FFmpeg combines Khmer audio with original video
7. **Results** → Final video ready for download

## 📈 Performance Considerations

- **Queue Processing**: Heavy tasks run in background queues
- **File Storage**: Store in cloud (S3, Wasabi) for production
- **CDN**: Serve translated videos via CDN
- **Caching**: Cache API responses (Redis)
- **Database Indexing**: Add indexes on `status`, `user_id`, `created_at`

## 🧪 Testing

```bash
# Run backend tests
php artisan test

# Run frontend tests
cd frontend
npm test

# Test FFmpeg installation
ffmpeg -version

# Test Redis connection
redis-cli ping
```

## 📚 Additional Resources

- [Laravel Documentation](https://laravel.com/docs)
- [React Documentation](https://react.dev)
- [Tailwind CSS](https://tailwindcss.com/docs)
- [OpenAI Whisper API](https://platform.openai.com/docs/guides/speech-to-text)
- [Google Translate API](https://cloud.google.com/translate/docs)
- [Azure Text-to-Speech](https://learn.microsoft.com/en-us/azure/cognitive-services/speech-service/)

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Open a Pull Request

## 📄 License

This project is open source and available under the MIT License.

## 💡 Future Enhancements

- Support for multiple source/target languages
- Subtitle generation in multiple formats (SRT, VTT)
- User authentication and history
- Batch processing of multiple videos
- Video editing interface
- Voice cloning for consistent speaker tone
- Real-time translation preview
- Webhook notifications for completion

---

**Built with ❤️ using Laravel + React + AI**