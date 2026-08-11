<?php

namespace Tests\Feature;

use App\Enums\BodyType;
use App\Models\Collection;
use App\Models\Request;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportCollectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function samplePostman(): array
    {
        return [
            'info' => ['name' => 'Demo API'],
            'variable' => [['key' => 'base_url', 'value' => 'https://api.example.com']],
            'auth' => ['type' => 'bearer', 'bearer' => [['key' => 'token', 'value' => '{{token}}']]],
            'item' => [
                [
                    'name' => 'Users',
                    'item' => [
                        [
                            'name' => 'Create User',
                            'request' => [
                                'method' => 'POST',
                                'header' => [['key' => 'Accept', 'value' => 'application/json']],
                                'url' => '{{base_url}}/users',
                                'body' => [
                                    'mode' => 'raw',
                                    'raw' => '{"name": "Ada"}',
                                    'options' => ['raw' => ['language' => 'json']],
                                ],
                            ],
                            'event' => [
                                ['listen' => 'test', 'script' => ['exec' => ['pm.test("ok", pm.response.status == 201)']]],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_it_exports_a_collection_as_a_postman_v21_document(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)
            ->postJson(route('api.collections.import', $workspace), ['collection' => $this->samplePostman()])
            ->assertOk();

        $collection = Collection::roots()->where('name', 'Demo API')->firstOrFail();

        $export = $this->actingAs($user)
            ->get(route('api.collections.download', $collection))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="demo-api.postman_collection.json"')
            ->json();

        $this->assertSame('Demo API', $export['info']['name']);
        $this->assertStringContainsString('v2.1.0', $export['info']['schema']);
        $this->assertSame([['key' => 'base_url', 'value' => 'https://api.example.com']], $export['variable']);
        $this->assertSame('bearer', $export['auth']['type']);
        $this->assertSame('{{token}}', $export['auth']['bearer'][0]['value']);

        $folder = $export['item'][0];
        $this->assertSame('Users', $folder['name']);

        $request = $folder['item'][0];
        $this->assertSame('Create User', $request['name']);
        $this->assertSame('POST', $request['request']['method']);
        $this->assertSame('{{base_url}}/users', $request['request']['url']['raw']);
        $this->assertSame('json', $request['request']['body']['options']['raw']['language']);
        $this->assertSame('test', $request['event'][0]['listen']);
    }

    public function test_an_exported_collection_can_be_re_imported_intact(): void
    {
        $user = User::factory()->create();
        $source = Workspace::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)
            ->postJson(route('api.collections.import', $source), ['collection' => $this->samplePostman()])
            ->assertOk();

        $collection = Collection::roots()->where('name', 'Demo API')->firstOrFail();

        $export = $this->actingAs($user)
            ->get(route('api.collections.download', $collection))
            ->json();

        // Round-trip: importing the export into a fresh workspace rebuilds it.
        $target = Workspace::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)
            ->postJson(route('api.collections.import', $target), ['collection' => $export])
            ->assertOk();

        $reimported = Collection::roots()->where('workspace_id', $target->id)->firstOrFail();
        $this->assertSame('Demo API', $reimported->name);
        $this->assertSame(['base_url' => 'https://api.example.com'], $reimported->variables);

        $createUser = Request::where('name', 'Create User')
            ->whereHas('collection', fn ($q) => $q->where('workspace_id', $target->id))
            ->firstOrFail();
        $this->assertSame(BodyType::Json, $createUser->body_type);
        $this->assertSame(['name' => 'Ada'], $createUser->body['json']);
        $this->assertStringContainsString('pm.test', $createUser->test_script);
    }

    public function test_form_data_file_fields_survive_an_export_import_round_trip(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $collection = $workspace->collections()->create(['name' => 'Uploads', 'order' => 0]);

        $collection->requests()->create([
            'name' => 'Upload Avatar',
            'method' => 'POST',
            'url' => 'https://api.example.com/avatars',
            'order' => 0,
            'body_type' => BodyType::FormData,
            'body' => ['fields' => [
                ['key' => 'name', 'value' => 'Ada', 'enabled' => true],
                ['key' => 'avatar', 'value' => '', 'enabled' => true, 'type' => 'file', 'file_id' => 7, 'filename' => 'avatar.png'],
                ['key' => 'note', 'value' => 'skip me', 'enabled' => false],
            ]],
        ]);

        $export = $this->actingAs($user)
            ->get(route('api.collections.download', $collection))
            ->json();

        $formdata = $export['item'][0]['request']['body']['formdata'];

        $this->assertSame(['key' => 'name', 'value' => 'Ada'], $formdata[0]);
        // The upload itself can't travel in JSON, so the row names the file instead.
        $this->assertSame(['key' => 'avatar', 'type' => 'file', 'src' => 'avatar.png'], $formdata[1]);
        $this->assertTrue($formdata[2]['disabled']);

        $target = Workspace::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)
            ->postJson(route('api.collections.import', $target), ['collection' => $export])
            ->assertOk();

        $reimported = Request::where('name', 'Upload Avatar')
            ->whereHas('collection', fn ($q) => $q->where('workspace_id', $target->id))
            ->firstOrFail();

        $this->assertSame(BodyType::FormData, $reimported->body_type);
        // Re-imported without a file behind it, so it shows as one to re-pick
        // rather than as a text field that quietly sends nothing.
        $this->assertSame('file', $reimported->body['fields'][1]['type']);
        $this->assertSame('avatar.png', $reimported->body['fields'][1]['filename']);
        $this->assertNull($reimported->body['fields'][1]['file_id']);
        $this->assertSame('Ada', $reimported->body['fields'][0]['value']);
        $this->assertFalse($reimported->body['fields'][2]['enabled']);
    }

    public function test_a_non_member_cannot_export_a_collection(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $collection = $workspace->collections()->create(['name' => 'Private', 'order' => 0]);

        $this->actingAs($stranger)
            ->get(route('api.collections.download', $collection))
            ->assertForbidden();
    }
}
