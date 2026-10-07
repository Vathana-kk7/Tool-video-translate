<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Text To Speech (eidosSpeech)
    |--------------------------------------------------------------------------
    */
    'tts' => [
        'key'   => env('TTS_API_KEY'),
        'url'   => env('TTS_API_URL'),
        'voice' => env('TTS_VOICE', 'km-KH-SreymomNeural'),
    ],

    'azure_tts' => [
        'key'    => env('AZURE_TTS_KEY'),
        'region' => env('AZURE_TTS_REGION', 'eastasia'),
    ],

    'google_translate' => [
        'key' => env('GOOGLE_TRANSLATE_API_KEY'),
    ],

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'translation_model' => env('GROQ_TRANSLATION_MODEL', 'qwen/qwen3.8-27b'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'translation_model' => env('GEMINI_TRANSLATION_MODEL', 'gemini-3.5-flash-lite'),
    ],

];
