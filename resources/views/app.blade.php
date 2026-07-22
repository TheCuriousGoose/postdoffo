<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @php
            $appName = config('app.name', 'PostDoffo');
            $component = $page['component'] ?? '';
            $canonical = url()->current();
            $ogImage = url('/og-image.png');

            // Per-page SEO metadata for the publicly indexable pages. Anything
            // not listed here is part of the authenticated app and is marked
            // noindex below.
            $seoPages = [
                'Welcome' => [
                    'title' => 'Free, open-source Postman alternative for teams',
                    'description' => 'PostDoffo is a free, open-source Postman alternative for teams. Import your collections, write request tests, manage environments and share every workspace — in the browser or self-hosted.',
                ],
                'legal/Privacy' => [
                    'title' => 'Privacy Policy',
                    'description' => 'How PostDoffo collects, uses, stores and protects your data.',
                ],
                'legal/Terms' => [
                    'title' => 'Terms of Service',
                    'description' => 'The terms that govern your use of PostDoffo, the free API workspace for teams.',
                ],
                'docs/Scripting' => [
                    'title' => 'Scripting reference',
                    'description' => 'Write pre-request and test scripts in PostDoffo. Reference for assertions, variables, and the request and response objects available to your scripts.',
                ],
                'ImportPostman' => [
                    'title' => 'Import Postman collections',
                    'description' => 'Move off Postman in one step. Import any Postman v2.1 collection — folders, requests, headers and auth land intact. Free and open source.',
                ],
                'SelfHosting' => [
                    'title' => 'Self-host PostDoffo',
                    'description' => 'PostDoffo is open source. Clone the repository, point it at your own database, and run the same app on your own infrastructure.',
                ],
                'vs/Postman' => [
                    'title' => 'PostDoffo vs Postman',
                    'description' => 'PostDoffo covers the daily Postman workflow — requests, collections, team sharing — free, open source and self-hostable.',
                ],
                'blog/Index' => [
                    'title' => 'Blog',
                    'description' => 'Guides on testing APIs, migrating from Postman, and getting the most out of environments and scripting.',
                ],
                'blog/ImportPostmanCollections' => [
                    'title' => 'What actually happens when you import a Postman collection',
                    'description' => 'A walk through the Postman v2.1 collection format: what folders, auth and headers look like on the wire, and where they land in PostDoffo.',
                ],
                'blog/HowToTestARestApi' => [
                    'title' => 'How to test a REST API without writing a test suite',
                    'description' => 'Using PostDoffo\'s pm.test assertions to check status codes, response shape and timing on every request you send.',
                ],
                'blog/EnvironmentVariablesExplained' => [
                    'title' => 'Environment variables in API requests, explained',
                    'description' => 'What environment variables actually solve, how {{variable}} interpolation works, and why secrets need their own handling.',
                ],
            ];

            $isIndexable = array_key_exists($component, $seoPages);
            $seo = $seoPages[$component] ?? [
                'title' => null,
                'description' => 'A fast, focused API workspace for teams. Build, test and share HTTP requests without the bloat.',
            ];

            // Mirror the client-side title template in resources/js/app.ts so
            // the server-rendered <title> matches what Inertia sets on mount.
            $metaTitle = $seo['title'] ? $seo['title'].' - '.$appName : $appName;
        @endphp

        <meta name="description" content="{{ $seo['description'] }}">
        <link rel="canonical" href="{{ $canonical }}">
        @if ($isIndexable)
            <meta name="robots" content="index, follow, max-image-preview:large">
        @else
            <meta name="robots" content="noindex, nofollow">
        @endif

        {{-- Open Graph --}}
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $appName }}">
        <meta property="og:title" content="{{ $metaTitle }}">
        <meta property="og:description" content="{{ $seo['description'] }}">
        <meta property="og:url" content="{{ $canonical }}">
        <meta property="og:image" content="{{ $ogImage }}">
        <meta property="og:image:width" content="2400">
        <meta property="og:image:height" content="1260">
        <meta property="og:image:type" content="image/png">
        <meta property="og:image:alt" content="{{ $appName }} — the free Postman alternative for teams">
        <meta property="og:locale" content="en_US">

        {{-- Twitter / X card --}}
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $metaTitle }}">
        <meta name="twitter:description" content="{{ $seo['description'] }}">
        <meta name="twitter:image" content="{{ $ogImage }}">
        <meta name="twitter:image:alt" content="{{ $appName }} — the free Postman alternative for teams">

        <meta name="theme-color" content="#f97316">
        <meta name="application-name" content="{{ $appName }}">

        @if ($component === 'Welcome')
            @php
                $faqs = [
                    ['q' => 'Is PostDoffo really free?', 'a' => 'Yes. Every feature is on the free plan: unlimited workspaces, collections, environments and team members. There is no paid tier and no seat counting.'],
                    ['q' => 'Can I import my existing Postman collections?', 'a' => 'Import any Postman v2.1 export and the full tree comes across intact: nested folders, requests, headers and auth all land in place.'],
                    ['q' => 'How do environment variables work?', 'a' => 'Reference variables like {{base_url}} or {{token}} anywhere in a request. Switch environments to swap every value at once, and mark sensitive values as secret.'],
                    ['q' => 'Can I write tests for my requests?', 'a' => 'Each request has a pre-request script and a test script. Assertions run every time you send, and results appear next to the response as pass or fail.'],
                    ['q' => 'Who can see my requests and secrets?', 'a' => 'Only you and the teammates you invite to a workspace. Roles decide whether a member can edit or only view. See the privacy policy for how data is stored.'],
                ];

                $jsonLd = [
                    '@context' => 'https://schema.org',
                    '@graph' => [
                        [
                            '@type' => 'WebSite',
                            '@id' => url('/').'#website',
                            'url' => url('/'),
                            'name' => $appName,
                            'description' => $seo['description'],
                        ],
                        [
                            '@type' => 'Organization',
                            '@id' => url('/').'#organization',
                            'name' => $appName,
                            'url' => url('/'),
                            'logo' => $ogImage,
                            'sameAs' => ['https://github.com/TheCuriousGoose/postdoffo'],
                        ],
                        [
                            '@type' => 'SoftwareApplication',
                            'name' => $appName,
                            'applicationCategory' => 'DeveloperApplication',
                            'operatingSystem' => 'Web',
                            'description' => $seo['description'],
                            'url' => url('/'),
                            'sameAs' => ['https://github.com/TheCuriousGoose/postdoffo'],
                            'offers' => [
                                '@type' => 'Offer',
                                'price' => '0',
                                'priceCurrency' => 'USD',
                            ],
                        ],
                        [
                            '@type' => 'FAQPage',
                            'mainEntity' => array_map(fn ($faq) => [
                                '@type' => 'Question',
                                'name' => $faq['q'],
                                'acceptedAnswer' => [
                                    '@type' => 'Answer',
                                    'text' => $faq['a'],
                                ],
                            ], $faqs),
                        ],
                    ],
                ];
            @endphp
            <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: hsl(0 0% 100%);
            }

            html.dark {
                background-color: hsl(20 14.3% 4.1%);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ $metaTitle }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
