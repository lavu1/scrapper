<!DOCTYPE html>
<html lang="en">

<head>
  @php
    $siteName = config('app.name', 'ZinstaBlog');
    $defaultDescription = config('seo.description', 'Latest news, multimedia posts, and jobs.');
    $logoPath = config('seo.publisher_logo', 'arsha/assets/img/logo.webp');
    $publisherLogo = filter_var($logoPath, FILTER_VALIDATE_URL) ? $logoPath : asset($logoPath);
    $decodeSection = fn (string $content): string => html_entity_decode(trim($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $seoTitle = $decodeSection($__env->yieldContent('title', $siteName));
    $seoDescription = $decodeSection($__env->yieldContent('meta_description', $defaultDescription));
    $canonicalUrl = $decodeSection($__env->yieldContent('canonical_url', url()->current()));
    $ogType = $decodeSection($__env->yieldContent('og_type', 'website'));
    $ogImage = $decodeSection($__env->yieldContent('og_image', $publisherLogo));
    $robots = $decodeSection($__env->yieldContent('robots', 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1'));
    $organizationId = url('/').'#organization';
    $websiteId = url('/').'#website';
    $headAdScripts = \App\Models\SiteSetting::enabledValues([
      \App\Models\SiteSetting::MONETAG_HEAD_SCRIPT,
      \App\Models\SiteSetting::ADSENSE_HEAD_SCRIPT,
    ]);

    $organizationSchema = [
      '@type' => 'Organization',
      '@id' => $organizationId,
      'name' => config('seo.publisher_name', $siteName),
      'url' => url('/'),
      'logo' => [
        '@type' => 'ImageObject',
        'url' => $publisherLogo,
      ],
      'areaServed' => [
        '@type' => 'Country',
        'name' => 'Zambia',
      ],
    ];

    if (config('seo.publisher_email')) {
      $organizationSchema['email'] = config('seo.publisher_email');
    }

    if (config('seo.same_as')) {
      $organizationSchema['sameAs'] = config('seo.same_as');
    }

    $siteSchema = [
      '@context' => 'https://schema.org',
      '@graph' => [
        $organizationSchema,
        [
          '@type' => 'WebSite',
          '@id' => $websiteId,
          'url' => url('/'),
          'name' => $siteName,
          'description' => $defaultDescription,
          'publisher' => ['@id' => $organizationId],
          'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => route('search').'?q={search_term_string}',
            'query-input' => 'required name=search_term_string',
          ],
        ],
      ],
    ];
  @endphp
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>{{ $seoTitle }}</title>
  <meta name="description" content="{{ $seoDescription }}">
  <meta name="robots" content="{{ $robots }}">
  @if (config('seo.google_site_verification'))
    <meta name="google-site-verification" content="{{ config('seo.google_site_verification') }}">
  @endif
  <link rel="canonical" href="{{ $canonicalUrl }}">
  <link rel="sitemap" type="application/xml" href="{{ route('sitemap') }}">
  <meta property="og:locale" content="en_ZM">
  <meta name="geo.region" content="ZM">
  <meta property="og:site_name" content="{{ $siteName }}">
  <meta property="og:type" content="{{ $ogType }}">
  <meta property="og:title" content="{{ $seoTitle }}">
  <meta property="og:description" content="{{ $seoDescription }}">
  <meta property="og:url" content="{{ $canonicalUrl }}">
  <meta property="og:image" content="{{ $ogImage }}">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="{{ $seoTitle }}">
  <meta name="twitter:description" content="{{ $seoDescription }}">
  <meta name="twitter:image" content="{{ $ogImage }}">

  <link href="{{ asset('favicon.ico') }}" rel="icon" sizes="any">
  <link href="{{ asset('arsha/assets/img/favicon.png') }}" rel="icon" type="image/png" sizes="32x32">
  <link href="{{ asset('arsha/assets/img/apple-touch-icon.png') }}" rel="apple-touch-icon" sizes="180x180">
  <link href="{{ asset('site.webmanifest') }}" rel="manifest">
  <meta name="theme-color" content="#24364f">
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,300;1,400;1,500;1,600;1,700;1,800&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Jost:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

  <link href="{{ asset('arsha/assets/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
  <link href="{{ asset('arsha/assets/vendor/bootstrap-icons/bootstrap-icons.css') }}" rel="stylesheet">
  <link href="{{ asset('arsha/assets/vendor/aos/aos.css') }}" rel="stylesheet">
  <link href="{{ asset('arsha/assets/vendor/glightbox/css/glightbox.min.css') }}" rel="stylesheet">
  <link href="{{ asset('arsha/assets/vendor/swiper/swiper-bundle.min.css') }}" rel="stylesheet">
  <link href="{{ asset('arsha/assets/css/main.css') }}" rel="stylesheet">
  <link href="{{ asset('arsha/assets/css/news.css') }}?v={{ filemtime(public_path('arsha/assets/css/news.css')) }}" rel="stylesheet">
  <link href="{{ asset('arsha/assets/css/sports-scoreboard.css') }}?v={{ filemtime(public_path('arsha/assets/css/sports-scoreboard.css')) }}" rel="stylesheet">
  <link href="{{ asset('arsha/assets/css/elections.css') }}?v={{ filemtime(public_path('arsha/assets/css/elections.css')) }}" rel="stylesheet">
  <script type="application/ld+json">{!! json_encode($siteSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
  @foreach ($headAdScripts as $headAdScript)
    {!! $headAdScript !!}
  @endforeach
  @stack('structured_data')
  @stack('head')
</head>

<body class="@yield('body_class', 'blog-page')">
  @include('partials.topbar')
  @include('partials.header')

  <main class="main">
    @yield('content')
  </main>

  @include('partials.footer')
  @include('partials.notifications')

  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>
  <div id="preloader"></div>

  <script src="{{ asset('arsha/assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
  <script src="{{ asset('arsha/assets/vendor/aos/aos.js') }}"></script>
  <script src="{{ asset('arsha/assets/vendor/glightbox/js/glightbox.min.js') }}"></script>
  <script src="{{ asset('arsha/assets/vendor/swiper/swiper-bundle.min.js') }}"></script>
  <script src="{{ asset('arsha/assets/vendor/waypoints/noframework.waypoints.js') }}"></script>
  <script src="{{ asset('arsha/assets/vendor/imagesloaded/imagesloaded.pkgd.min.js') }}"></script>
  <script src="{{ asset('arsha/assets/vendor/isotope-layout/isotope.pkgd.min.js') }}"></script>
  <script src="{{ asset('arsha/assets/js/main.js') }}"></script>
  <script src="{{ asset('arsha/assets/js/elections.js') }}" defer></script>
  <script>
    (() => {
      const key = 'zinstablog_device_id';
      let deviceId = localStorage.getItem(key);

      if (!deviceId) {
        deviceId = crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
        localStorage.setItem(key, deviceId);
      }

      document.querySelectorAll('input[name="device_id"]').forEach((input) => {
        input.value = deviceId;
      });
    })();
  </script>
  @include('partials.webmcp')
  @stack('scripts')
</body>

</html>
