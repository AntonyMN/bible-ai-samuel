<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Samuel') }} - Your Faith Companion</title>
        <link rel="icon" type="image/png" href="/favicon.png">
        <meta name="description" content="Samuel is your warm, humble, and encouraging Christian brother, offering scriptural advice and comfort based strictly on the Holy Bible.">
        <meta name="theme-color" content="#6B21A8">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Gentium+Book+Plus:ital,wght@0,400;0,700;1,400;1,700&family=Outfit:wght@100..900&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased selection:bg-purple-100 selection:text-purple-900">
        @inertia
    </body>
</html>
