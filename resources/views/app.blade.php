<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Chinese to Khmer Video Translator</title>

        <link rel="stylesheet" crossorigin href="{{ asset('build/assets/index-D-Q2Nb6z.css') }}">
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    </head>
    <body class="antialiased bg-gray-50">
        <div id="root"></div>

        <script type="module" crossorigin src="{{ asset('build/assets/index-D8fmWZHt.js') }}"></script>
    </body>
</html>
