<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seo['title'] ?? config('app.name') }}</title>
    <meta name="description" content="{{ $seo['description'] ?? '' }}">
    @isset($seo['canonical'])
        <link rel="canonical" href="{{ $seo['canonical'] }}">
    @endisset
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header class="site-header">
        <nav class="nav">
            <a class="brand" href="{{ route('home') }}">{{ config('app.name') }}</a>
            <div class="nav-links">
                <a href="{{ route('articles.index') }}">Articles</a>
                <a href="{{ url('/admin') }}">Dashboard</a>
            </div>
        </nav>
    </header>

    <main class="page">
        @yield('content')
    </main>
</body>
</html>
