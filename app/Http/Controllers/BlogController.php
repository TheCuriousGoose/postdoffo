<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function index(): Response
    {
        $posts = Post::query()
            ->published()
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (Post $post): array => [
                'title' => $post->title,
                'slug' => $post->slug,
                'excerpt' => $post->excerpt,
                'date' => $post->published_at?->format('F j, Y'),
            ]);

        return Inertia::render('blog/Index', [
            'posts' => $posts,
        ]);
    }

    public function show(Post $post): Response
    {
        abort_unless($post->isPublished(), 404);

        return Inertia::render('blog/Show', [
            'post' => [
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'date' => $post->published_at?->format('F j, Y'),
                'html' => $post->toHtml(),
            ],
        ]);
    }
}
