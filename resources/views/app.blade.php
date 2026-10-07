<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#087cf0">

        <title>{{ config('app.name', 'SuportIA') }}</title>
        <link rel="icon" type="image/png" href="/app-icon.png?v=20261006">
        <link rel="apple-touch-icon" href="/app-icon.png?v=20261006">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased">
        <div id="app"></div>
    </body>
</html>
