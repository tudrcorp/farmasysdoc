<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1" />
<meta name="author" content="Farmadoc" />
<meta name="theme-color" content="#18ACB2" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="default" />
<meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Farmadoc') }}" />

@php
    $defaultTitle = config('app.name', 'Farmadoc');
    $pageTitle = filled($title ?? null) ? (string) $title : $defaultTitle;
    $fullTitle = filled($title ?? null) ? $pageTitle.' - '.$defaultTitle : $defaultTitle;
    $metaDescription = $description ?? 'Sistema integral de gestion farmaceutica, ventas, inventario, compras y panel administrativo Farmadoc.';
    $canonicalUrl = url()->current();
    $ogImage = asset('images/logos/favicon.png');
@endphp

<title>
    {{ $fullTitle }}
</title>
<meta name="description" content="{{ $metaDescription }}" />
<link rel="canonical" href="{{ $canonicalUrl }}" />

<meta property="og:type" content="website" />
<meta property="og:site_name" content="{{ $defaultTitle }}" />
<meta property="og:title" content="{{ $fullTitle }}" />
<meta property="og:description" content="{{ $metaDescription }}" />
<meta property="og:url" content="{{ $canonicalUrl }}" />
<meta property="og:image" content="{{ $ogImage }}" />
<meta property="og:image:type" content="image/png" />
<meta property="og:image:width" content="1024" />
<meta property="og:image:height" content="1024" />
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="{{ $fullTitle }}" />
<meta name="twitter:description" content="{{ $metaDescription }}" />
<meta name="twitter:image" content="{{ $ogImage }}" />

<link rel="icon" type="image/png" sizes="1024x1024" href="{{ asset('images/logos/favicon.png') }}">
<link rel="apple-touch-icon" sizes="1024x1024" href="{{ asset('images/logos/favicon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
