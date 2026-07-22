<?php

namespace App\Console\Commands;

use App\Enums\BodyType;
use App\Enums\HttpMethod;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Seeds a workspace with a large, realistic tree of collections/folders and
 * requests via bulk inserts (bypassing Eloquent per-row overhead) so that
 * thousands of rows land in seconds — the seeding itself isn't what's under
 * test, the resulting page load is.
 */
class SeedPerformanceData extends Command
{
    protected $signature = 'perf:seed-requests
        {--workspace= : Workspace ID to seed into (a fresh one is created if omitted)}
        {--collections=40 : Number of collections/folders to create}
        {--requests=5000 : Number of requests to spread across them}
        {--fresh : Delete the workspace\'s existing collections/requests first}';

    protected $description = 'Seed a workspace with a large volume of fake collections and requests, for load-time performance testing';

    public function handle(): int
    {
        $workspace = $this->resolveWorkspace();

        if ($this->option('fresh')) {
            $workspace->collections()->whereNull('parent_id')->delete();
        }

        $collectionCount = max(1, (int) $this->option('collections'));
        $requestCount = max(1, (int) $this->option('requests'));

        $this->info("Seeding workspace #{$workspace->id} (\"{$workspace->name}\") with {$collectionCount} collections and {$requestCount} requests…");

        $start = microtime(true);

        $collectionIds = $this->seedCollections($workspace, $collectionCount);
        $this->seedRequests($collectionIds, $requestCount);

        $elapsed = round(microtime(true) - $start, 2);

        $this->newLine();
        $this->info("Done in {$elapsed}s — open workspace #{$workspace->id} in the app to see the effect.");

        return self::SUCCESS;
    }

    private function resolveWorkspace(): Workspace
    {
        if ($id = $this->option('workspace')) {
            return Workspace::findOrFail((int) $id);
        }

        $owner = User::first() ?? User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $existing = Workspace::where('name', 'Performance Test Workspace')
            ->where('owner_id', $owner->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        // owner_id isn't mass-assignable (Workspace only fills 'name'), so it's
        // set directly the same way WorkspaceController::store() does.
        $workspace = new Workspace(['name' => 'Performance Test Workspace']);
        $workspace->owner_id = $owner->id;
        $workspace->save();

        return $workspace;
    }

    /**
     * Builds a folder tree (root collections + nested subfolders 1-3 deep) so
     * the sidebar has to render something shaped like a real, sprawling
     * workspace rather than one flat list.
     *
     * @return list<int>
     */
    private function seedCollections(Workspace $workspace, int $count): array
    {
        $now = now();
        $ids = [];
        $parents = [];
        $rootCount = max(1, (int) ceil($count / 8));

        $bar = $this->output->createProgressBar($count);
        $bar->setMessage('collections');

        for ($i = 0; $i < $count; $i++) {
            $isRoot = $i < $rootCount || $parents === [];
            $parentId = $isRoot ? null : fake()->randomElement($parents);

            $id = DB::table('collections')->insertGetId([
                'workspace_id' => $workspace->id,
                'parent_id' => $parentId,
                'name' => ucfirst(fake()->words(random_int(1, 3), true)).($isRoot ? ' API' : ''),
                'order' => $i,
                'variables' => null,
                'headers' => null,
                'auth_type' => null,
                'auth' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $ids[] = $id;
            $parents[] = $id;

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $ids;
    }

    /**
     * @param  list<int>  $collectionIds
     */
    private function seedRequests(array $collectionIds, int $count): void
    {
        $methods = HttpMethod::cases();
        $now = now();
        $chunkSize = 500;
        $chunk = [];

        $bar = $this->output->createProgressBar($count);
        $bar->setMessage('requests');

        for ($i = 0; $i < $count; $i++) {
            $chunk[] = $this->fakeRequestRow($collectionIds, $methods, $i, $now);

            if (count($chunk) >= $chunkSize) {
                DB::table('requests')->insert($chunk);
                $chunk = [];
            }

            $bar->advance();
        }

        if ($chunk !== []) {
            DB::table('requests')->insert($chunk);
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * @param  list<int>  $collectionIds
     * @param  list<HttpMethod>  $methods
     * @return array<string, mixed>
     */
    private function fakeRequestRow(array $collectionIds, array $methods, int $order, Carbon $now): array
    {
        $method = fake()->randomElement($methods);
        $bodyType = $method === HttpMethod::Get || $method === HttpMethod::Head
            ? BodyType::None
            : fake()->randomElement([BodyType::None, BodyType::Json, BodyType::Json, BodyType::UrlEncoded]);

        return [
            'collection_id' => fake()->randomElement($collectionIds),
            'name' => ucfirst(fake()->words(random_int(2, 4), true)),
            'method' => $method->value,
            'url' => 'https://'.fake()->domainWord().'.example.com/api/'.fake()->word().'/'.fake()->randomNumber(4),
            'order' => $order,
            'headers' => json_encode($this->fakeKeyValueRows(random_int(0, 4), fn () => fake()->word())),
            'query_params' => json_encode($this->fakeKeyValueRows(random_int(0, 3), fn () => fake()->word())),
            'body' => $this->fakeBody($bodyType),
            'body_type' => $bodyType->value,
            'auth_type' => null,
            'auth' => null,
            'pre_request_script' => null,
            'test_script' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @return array<int, array{key: string, value: string, enabled: bool}>
     */
    private function fakeKeyValueRows(int $count, callable $key): array
    {
        return collect(range(1, $count))
            ->map(fn () => ['key' => $key(), 'value' => fake()->word(), 'enabled' => true])
            ->all();
    }

    private function fakeBody(BodyType $bodyType): ?string
    {
        return match ($bodyType) {
            BodyType::Json => json_encode(['json' => [
                'id' => fake()->randomNumber(5),
                'name' => fake()->name(),
                'note' => fake()->sentence(),
            ]]),
            BodyType::UrlEncoded => json_encode(['fields' => $this->fakeKeyValueRows(random_int(1, 3), fn () => fake()->word())]),
            default => null,
        };
    }
}
