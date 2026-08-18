<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(User::AVATAR_DISK);
    }

    public function test_a_profile_picture_can_be_uploaded()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('profile.avatar.store'), [
                'avatar' => UploadedFile::fake()->image('me.jpg', 900, 900),
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertNotNull($user->avatar_path);
        Storage::disk(User::AVATAR_DISK)->assertExists($user->avatar_path);
    }

    public function test_an_uploaded_picture_is_squared_and_capped()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('wide.jpg', 1600, 900),
        ]);

        [$width, $height, $type] = $this->storedImage($user->refresh());

        $this->assertSame(512, $width);
        $this->assertSame(512, $height);
        $this->assertSame(IMAGETYPE_JPEG, $type);
    }

    public function test_a_picture_smaller_than_the_cap_is_not_upscaled()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('small.png', 120, 200),
        ]);

        [$width, $height] = $this->storedImage($user->refresh());

        $this->assertSame(120, $width);
        $this->assertSame(120, $height);
    }

    public function test_uploading_a_new_picture_removes_the_previous_file()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('first.jpg', 300, 300),
        ]);

        $first = $user->refresh()->avatar_path;

        $this->actingAs($user)->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('second.jpg', 300, 300),
        ]);

        $second = $user->refresh()->avatar_path;

        $this->assertNotSame($first, $second);
        Storage::disk(User::AVATAR_DISK)->assertMissing($first);
        Storage::disk(User::AVATAR_DISK)->assertExists($second);
    }

    public function test_the_avatar_url_is_shared_with_the_front_end()
    {
        $user = User::factory()->create();

        $this->assertNull($user->avatar);

        $this->actingAs($user)->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('me.jpg', 300, 300),
        ]);

        $user->refresh();

        $this->assertSame(
            '/avatars/'.$user->id.'/'.basename((string) $user->avatar_path),
            $user->avatar,
        );
        $this->assertArrayNotHasKey('avatar_path', $user->toArray());
    }

    public function test_a_stored_picture_is_served_to_signed_in_users()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('me.jpg', 300, 300),
        ]);

        $response = $this->actingAs($user)->get($user->refresh()->avatar);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
    }

    public function test_a_replaced_picture_is_no_longer_served()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('first.jpg', 300, 300),
        ]);

        $stale = $user->refresh()->avatar;

        $this->actingAs($user)->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('second.jpg', 300, 300),
        ]);

        $this->actingAs($user)->get($stale)->assertNotFound();
    }

    public function test_guests_cannot_view_a_picture()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('me.jpg', 300, 300),
        ]);

        $url = $user->refresh()->avatar;

        $this->post(route('logout'));

        $this->get($url)->assertRedirect(route('login'));
    }

    public function test_a_file_that_is_not_an_image_is_rejected()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.avatar.store'), [
                'avatar' => UploadedFile::fake()->create('resume.pdf', 40, 'application/pdf'),
            ]);

        $response->assertSessionHasErrors('avatar');

        $this->assertNull($user->refresh()->avatar_path);
    }

    public function test_an_svg_is_rejected()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.avatar.store'), [
                'avatar' => UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml'),
            ]);

        $response->assertSessionHasErrors('avatar');
    }

    public function test_a_picture_below_the_minimum_size_is_rejected()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.avatar.store'), [
                'avatar' => UploadedFile::fake()->image('tiny.jpg', 32, 32),
            ]);

        $response->assertSessionHasErrors('avatar');
    }

    public function test_an_oversized_upload_is_rejected()
    {
        $user = User::factory()->create();

        $picture = UploadedFile::fake()->image('huge.jpg', 900, 900)->size(9000);

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.avatar.store'), ['avatar' => $picture]);

        $response->assertSessionHasErrors('avatar');
    }

    public function test_a_profile_picture_can_be_removed()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('me.jpg', 300, 300),
        ]);

        $path = $user->refresh()->avatar_path;

        $response = $this->actingAs($user)->delete(route('profile.avatar.destroy'));

        $response->assertRedirect(route('profile.edit'));

        $this->assertNull($user->refresh()->avatar_path);
        Storage::disk(User::AVATAR_DISK)->assertMissing($path);
    }

    public function test_removing_a_picture_a_user_never_had_is_harmless()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete(route('profile.avatar.destroy'))
            ->assertRedirect(route('profile.edit'));

        $this->assertNull($user->refresh()->avatar_path);
    }

    public function test_deleting_the_account_removes_the_picture()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('me.jpg', 300, 300),
        ]);

        $path = $user->refresh()->avatar_path;

        $this->actingAs($user)->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

        Storage::disk(User::AVATAR_DISK)->assertMissing($path);
    }

    public function test_guests_cannot_upload_a_picture()
    {
        $this->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('me.jpg', 300, 300),
        ])->assertRedirect(route('login'));
    }

    /**
     * Dimensions and type of the user's stored avatar.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function storedImage(User $user): array
    {
        $contents = Storage::disk(User::AVATAR_DISK)->get($user->avatar_path);

        $size = getimagesizefromstring((string) $contents);

        $this->assertNotFalse($size);

        return [$size[0], $size[1], $size[2]];
    }
}
