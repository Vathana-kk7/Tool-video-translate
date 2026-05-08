- [x] Replace Google Translate TTS HTTP GET call in `app/Services/TTSService.php` with env-driven Edge TTS / custom API POST call using `TTS_API_KEY` + `TTS_API_URL`.
- [x] Add validation and clear exceptions when TTS env vars are missing.
- [x] Ensure TTSService still writes raw mp3 bytes to the temp mp3 files.
- [x] Attempted to run phpunit; it fails due to environment/library issues unrelated to code changes.



