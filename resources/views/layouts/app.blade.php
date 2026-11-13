<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="{{ !empty($uiPreferences['large_text']) ? 'large-text' : '' }} {{ !empty($uiPreferences['high_contrast']) ? 'high-contrast' : '' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'NAPC Connect')</title>
    <meta name="description" content="Find verified ayuda, scholarships, jobs and TESDA training programs near you.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="flex min-h-screen flex-col">
    <a href="#main-content" class="sr-only focus:not-sr-only">{{ __('Skip to content') }}</a>

    {{-- Each page picks its own navbar: @section('navbar') <x-navbar.poster /> @endsection --}}
    @yield('navbar')

    @if (session('status'))
        <div class="container-page w-full pt-4">
            <x-alert type="success">{{ session('status') }}</x-alert>
        </div>
    @endif

    @if (session('warning'))
        <div class="container-page w-full pt-4">
            <x-alert type="warning">{{ session('warning') }}</x-alert>
        </div>
    @endif

    <main id="main-content" class="flex-1" tabindex="-1">
        @yield('content')
    </main>

    <footer class="border-t-2 border-[var(--navy)]">
        <div class="container-page flex flex-col gap-3 py-8 md:flex-row md:items-center md:justify-between">
            <p class="font-bold text-[var(--navy)]">NAPC Connect</p>
            <p class="muted">{{ __('Built for SDG 1 (No Poverty), SDG 4 (Quality Education) and SDG 8 (Decent Work and Economic Growth).') }}</p>
        </div>
    </footer>

</body>

</html>
