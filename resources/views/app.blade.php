<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @php
            $isPublicPage = request()->routeIs('login') || request()->is('/');
            $siteName = 'Pelaporan Ekspedisi PPA BIB';
            $seoTitle = 'Portal Pelaporan Ekspedisi & Dokumen Vendor | PPA Site BIB';
            $seoDescription = 'Portal operasional PT Putra Perkasa Abadi Site Borneo Indobara untuk pelaporan kedatangan ekspedisi, pengajuan dokumen vendor, dan monitoring koordinator sarana.';
            $canonicalUrl = rtrim(config('app.url'), '/') . '/login';
        @endphp
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ $seoTitle }}</title>
        <meta name="description" content="{{ $seoDescription }}">
        <meta name="application-name" content="{{ $siteName }}">
        <meta name="author" content="PT Putra Perkasa Abadi Site Borneo Indobara">
        <meta name="theme-color" content="#c81e2a">
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}?v=20260826">
        <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/favicon-ekspedisi-ppa.png') }}?v=20260825-2">
        <link rel="shortcut icon" type="image/png" href="{{ asset('images/favicon-ekspedisi-ppa.png') }}?v=20260825-2">
        <link rel="apple-touch-icon" sizes="512x512" href="{{ asset('images/favicon-ekspedisi-ppa.png') }}?v=20260825-2">
        <meta name="robots" content="{{ $isPublicPage ? 'index, follow, max-image-preview:large, max-snippet:-1' : 'noindex, nofollow, noarchive' }}">
        @if ($isPublicPage)
            <link rel="canonical" href="{{ $canonicalUrl }}">
            <meta property="og:type" content="website">
            <meta property="og:locale" content="id_ID">
            <meta property="og:site_name" content="{{ $siteName }}">
            <meta property="og:title" content="{{ $seoTitle }}">
            <meta property="og:description" content="{{ $seoDescription }}">
            <meta property="og:url" content="{{ $canonicalUrl }}">
            <meta name="twitter:card" content="summary">
            <meta name="twitter:title" content="{{ $seoTitle }}">
            <meta name="twitter:description" content="{{ $seoDescription }}">
            <script type="application/ld+json">{!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'WebApplication',
                'name' => $siteName,
                'url' => $canonicalUrl,
                'description' => $seoDescription,
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'Web',
                'inLanguage' => 'id-ID',
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => 'PT Putra Perkasa Abadi Site Borneo Indobara',
                    'url' => rtrim(config('app.url'), '/'),
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
