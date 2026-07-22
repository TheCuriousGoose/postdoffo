<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_blog_index_lists_published_posts_only(): void
    {
        Post::create([
            'title' => 'Published Post',
            'slug' => 'published-post',
            'excerpt' => 'Visible.',
            'body' => 'Body.',
            'published_at' => now()->subDay(),
        ]);

        Post::create([
            'title' => 'Draft Post',
            'slug' => 'draft-post',
            'excerpt' => 'Hidden.',
            'body' => 'Body.',
            'published_at' => null,
        ]);

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('blog/Index')
                ->has('posts', 1)
                ->where('posts.0.slug', 'published-post'));
    }

    public function test_a_published_post_is_reachable(): void
    {
        Post::create([
            'title' => 'Published Post',
            'slug' => 'published-post',
            'excerpt' => 'Visible.',
            'body' => '# Heading',
            'published_at' => now()->subDay(),
        ]);

        $this->get('/blog/published-post')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('blog/Show')
                ->where('post.title', 'Published Post'));
    }

    public function test_a_draft_post_returns_404_publicly(): void
    {
        Post::create([
            'title' => 'Draft Post',
            'slug' => 'draft-post',
            'excerpt' => 'Hidden.',
            'body' => 'Body.',
            'published_at' => null,
        ]);

        $this->get('/blog/draft-post')->assertNotFound();
    }

    public function test_a_future_dated_post_returns_404_publicly(): void
    {
        Post::create([
            'title' => 'Scheduled Post',
            'slug' => 'scheduled-post',
            'excerpt' => 'Not yet.',
            'body' => 'Body.',
            'published_at' => now()->addWeek(),
        ]);

        $this->get('/blog/scheduled-post')->assertNotFound();
    }

    public function test_markdown_body_is_rendered_and_raw_html_is_stripped(): void
    {
        $post = Post::create([
            'title' => 'Rendered',
            'slug' => 'rendered',
            'excerpt' => 'x',
            'body' => "## A heading\n\n<script>alert('xss')</script>",
            'published_at' => now()->subDay(),
        ]);

        $html = $post->toHtml();

        $this->assertStringContainsString('<h2>A heading</h2>', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_a_non_admin_cannot_manage_posts(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.posts.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.posts.store'), [
            'title' => 'Nope',
            'excerpt' => 'x',
            'body' => 'x',
        ])->assertForbidden();
    }

    public function test_an_admin_can_create_a_post_and_the_slug_is_generated(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.posts.store'), [
            'title' => 'My First Post',
            'slug' => '',
            'excerpt' => 'An excerpt.',
            'body' => '# Hello',
            'published' => true,
        ])->assertRedirect(route('admin.posts.index'));

        $post = Post::firstOrFail();
        $this->assertSame('my-first-post', $post->slug);
        $this->assertTrue($post->isPublished());
    }

    public function test_an_admin_can_update_a_post_and_toggle_it_back_to_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $post = Post::create([
            'title' => 'Original',
            'slug' => 'original',
            'excerpt' => 'x',
            'body' => 'x',
            'published_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)->patch(route('admin.posts.update', $post->id), [
            'title' => 'Updated',
            'slug' => 'original',
            'excerpt' => 'x',
            'body' => 'x',
            'published' => false,
        ])->assertRedirect(route('admin.posts.index'));

        $post->refresh();
        $this->assertSame('Updated', $post->title);
        $this->assertNull($post->published_at);
    }
}
