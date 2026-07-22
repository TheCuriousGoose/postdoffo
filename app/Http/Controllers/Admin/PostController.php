<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function index(): Response
    {
        $posts = Post::query()
            ->orderByDesc('created_at')
            ->paginate(15)
            ->through(fn (Post $post): array => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'is_published' => $post->isPublished(),
                'published_at' => $post->published_at?->toDateString(),
                'updated_at' => $post->updated_at,
            ]);

        return Inertia::render('admin/posts/Index', [
            'posts' => $posts,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/posts/Edit', [
            'post' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $post = new Post;
        $this->fill($post, $data);
        $post->slug = Post::uniqueSlug($data['slug'] !== '' ? $data['slug'] : $data['title']);
        $post->save();

        return to_route('admin.posts.index');
    }

    public function edit(Post $post): Response
    {
        return Inertia::render('admin/posts/Edit', [
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'excerpt' => $post->excerpt,
                'body' => $post->body,
                'published' => $post->published_at !== null,
            ],
        ]);
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $data = $this->validated($request);

        $this->fill($post, $data);
        if ($data['slug'] !== '' && $data['slug'] !== $post->slug) {
            $post->slug = Post::uniqueSlug($data['slug'], $post->id);
        }
        $post->save();

        return to_route('admin.posts.index');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $post->delete();

        return back();
    }

    /**
     * @return array{title: string, slug: string, excerpt: string, body: string, published: bool}
     */
    private function validated(Request $request): array
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9-]*$/'],
            'excerpt' => ['required', 'string', 'max:300'],
            'body' => ['required', 'string'],
            'published' => ['boolean'],
        ]);

        return [
            'title' => (string) $request->string('title'),
            'slug' => (string) $request->string('slug'),
            'excerpt' => (string) $request->string('excerpt'),
            'body' => (string) $request->input('body', ''),
            'published' => $request->boolean('published'),
        ];
    }

    /**
     * @param  array{title: string, slug: string, excerpt: string, body: string, published: bool}  $data
     */
    private function fill(Post $post, array $data): void
    {
        $post->title = $data['title'];
        $post->excerpt = $data['excerpt'];
        $post->body = $data['body'];

        // Toggling published on keeps an existing date, or stamps now for a
        // first publish; toggling off returns the post to draft.
        if ($data['published']) {
            $post->published_at ??= now();
        } else {
            $post->published_at = null;
        }
    }
}
