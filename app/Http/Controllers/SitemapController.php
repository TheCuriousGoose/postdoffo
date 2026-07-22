<?php

namespace App\Http\Controllers;

use App\Models\Post;
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
     * Only rendered when `marketing.enabled` — see routes/web.php. A
     * self-hosted instance has none of these routes registered.
     *
     * @var array<string, array{lastmod: string, changefreq: string, priority: string}>
     */
    private const PAGES = [
        'home' => ['lastmod' => '2026-07-22', 'changefreq' => 'weekly', 'priority' => '1.0'],
        'vs.postman' => ['lastmod' => '2026-07-22', 'changefreq' => 'monthly', 'priority' => '0.8'],
        'import.postman' => ['lastmod' => '2026-07-22', 'changefreq' => 'monthly', 'priority' => '0.8'],
        'self-hosting' => ['lastmod' => '2026-07-22', 'changefreq' => 'monthly', 'priority' => '0.7'],
        'blog.index' => ['lastmod' => '2026-07-22', 'changefreq' => 'weekly', 'priority' => '0.6'],
        'docs.scripting' => ['lastmod' => '2026-07-21', 'changefreq' => 'monthly', 'priority' => '0.7'],
        'legal.privacy' => ['lastmod' => '2026-07-01', 'changefreq' => 'yearly', 'priority' => '0.3'],
        'legal.terms' => ['lastmod' => '2026-07-01', 'changefreq' => 'yearly', 'priority' => '0.3'],
    ];

    /**
     * Render the XML sitemap of public marketing/documentation pages.
     */
    public function index(): Response
    {
        if (! config('marketing.enabled')) {
            $urls = '';
        } else {
            $urls = collect(self::PAGES)->map(function (array $meta, string $name): string {
                return $this->url(route($name), $meta['lastmod'], $meta['changefreq'], $meta['priority']);
            })->implode('');

            // Published blog posts are database-backed, so append them from
            // the DB rather than the static list above.
            $urls .= Post::query()
                ->published()
                ->orderByDesc('published_at')
                ->get()
                ->map(fn (Post $post): string => $this->url(
                    route('blog.show', $post->slug),
                    $post->updated_at->toDateString(),
                    'monthly',
                    '0.5',
                ))->implode('');
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .$urls
            .'</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function url(string $loc, string $lastmod, string $changefreq, string $priority): string
    {
        return implode('', [
            '<url>',
            '<loc>'.e($loc).'</loc>',
            '<lastmod>'.$lastmod.'</lastmod>',
            '<changefreq>'.$changefreq.'</changefreq>',
            '<priority>'.$priority.'</priority>',
            '</url>',
        ]);
    }

    /**
     * Render robots.txt. On the hosted marketing site this keeps crawlers on
     * the public surface and out of the authenticated app. On a self-hosted
     * instance — someone's private team tool, not meant to rank anywhere —
     * it blocks crawling entirely.
     */
    public function robots(): Response
    {
        if (! config('marketing.enabled')) {
            return response("User-agent: *\nDisallow: /\n", 200, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Cache-Control' => 'public, max-age=3600',
            ]);
        }

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
