<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * The publicly indexable pages, keyed by route name, with the crawl
     * hints search engines use to prioritise and schedule recrawls.
     *
     * `lastmod` is the date the page's content actually last changed (W3C
     * date, YYYY-MM-DD). Bump it when you meaningfully edit that page so the
     * signal stays honest — do not derive it from the request time, or every
     * crawl reports a fresh change and search engines learn to ignore it.
     *
     * @var array<string, array{lastmod: string, changefreq: string, priority: string}>
     */
    private const PAGES = [
        'home' => ['lastmod' => '2026-07-22', 'changefreq' => 'weekly', 'priority' => '1.0'],
        'docs.scripting' => ['lastmod' => '2026-07-21', 'changefreq' => 'monthly', 'priority' => '0.7'],
        'legal.privacy' => ['lastmod' => '2026-07-01', 'changefreq' => 'yearly', 'priority' => '0.3'],
        'legal.terms' => ['lastmod' => '2026-07-01', 'changefreq' => 'yearly', 'priority' => '0.3'],
    ];

    /**
     * Render the XML sitemap of public marketing/documentation pages.
     */
    public function index(): Response
    {
        $urls = collect(self::PAGES)->map(function (array $meta, string $name): string {
            return implode('', [
                '<url>',
                '<loc>'.e(route($name)).'</loc>',
                '<lastmod>'.$meta['lastmod'].'</lastmod>',
                '<changefreq>'.$meta['changefreq'].'</changefreq>',
                '<priority>'.$meta['priority'].'</priority>',
                '</url>',
            ]);
        })->implode('');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .$urls
            .'</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Render robots.txt, keeping crawlers on the marketing surface and out
     * of the authenticated application, and pointing them at the sitemap.
     */
    public function robots(): Response
    {
        $disallow = [
            '/dashboard',
            '/settings',
            '/workspaces',
            '/admin',
            '/auth/',
            '/api/',
            '/login',
            '/register',
            '/forgot-password',
            '/reset-password',
            '/verify-email',
        ];

        $lines = ['User-agent: *'];

        foreach ($disallow as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.route('sitemap');
        $lines[] = '';

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
