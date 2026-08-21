<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Phase 1 of the workspaces/collections/requests UUID migration (see the
 * follow-up "swap" migration for phase 2+). Purely additive: every column
 * added here is nullable and every existing bigint id/FK column is left
 * untouched, so this migration can be rolled back with no data loss even
 * after it has run against real data.
 *
 * Own-identity uuids (workspaces.uuid, collections.uuid, requests.uuid) are
 * generated in PHP via Str::orderedUuid() rather than a DB-native UUID
 * function, since this app targets both MySQL/MariaDB (no such function) and
 * SQLite (used by the test suite, which also has none). FK-pointing uuid
 * columns are backfilled with a single correlated-subquery UPDATE per
 * column, which only works once the column it correlates against is already
 * populated — hence the strict ordering below: workspaces/collections/requests
 * own uuids first, then every column that references them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addColumns();
        $this->backfillOwnUuids();
        $this->backfillForeignUuids();
    }

    private function addColumns(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->uuid('workspace_uuid')->nullable()->after('workspace_id');
            $table->uuid('parent_uuid')->nullable()->after('parent_id');
        });

        Schema::table('requests', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->uuid('collection_uuid')->nullable()->after('collection_id');
        });

        Schema::table('workspace_members', function (Blueprint $table) {
            $table->uuid('workspace_uuid')->nullable()->after('workspace_id');
        });

        Schema::table('environments', function (Blueprint $table) {
            $table->uuid('workspace_uuid')->nullable()->after('workspace_id');
        });

        Schema::table('request_history', function (Blueprint $table) {
            $table->uuid('workspace_uuid')->nullable()->after('workspace_id');
            $table->uuid('request_uuid')->nullable()->after('request_id');
        });

        Schema::table('workspace_invitations', function (Blueprint $table) {
            $table->uuid('workspace_uuid')->nullable()->after('workspace_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->uuid('last_workspace_uuid')->nullable()->after('last_workspace_id');
        });

        Schema::table('workspace_variables', function (Blueprint $table) {
            $table->uuid('workspace_uuid')->nullable()->after('workspace_id');
        });

        Schema::table('request_cookies', function (Blueprint $table) {
            $table->uuid('workspace_uuid')->nullable()->after('workspace_id');
        });

        Schema::table('request_files', function (Blueprint $table) {
            $table->uuid('request_uuid')->nullable()->after('request_id');
        });
    }

    private function backfillOwnUuids(): void
    {
        foreach (['workspaces', 'collections', 'requests'] as $table) {
            DB::table($table)->select('id')->orderBy('id')->chunkById(500, function ($rows) use ($table) {
                DB::transaction(function () use ($table, $rows) {
                    foreach ($rows as $row) {
                        DB::table($table)->where('id', $row->id)->update([
                            'uuid' => (string) Str::orderedUuid(),
                        ]);
                    }
                });
            });
        }
    }

    private function backfillForeignUuids(): void
    {
        // collections.workspace_uuid — straight join against the now-populated workspaces.uuid.
        DB::statement(
            'UPDATE collections SET workspace_uuid = ('.
            'SELECT uuid FROM workspaces WHERE workspaces.id = collections.workspace_id'.
            ')'
        );

        // collections.parent_uuid is self-referential. MySQL/MariaDB refuse to select
        // from the same table being updated unless it's wrapped as a derived table —
        // SQLite has no such restriction but accepts this form too, so it's portable.
        DB::statement(
            'UPDATE collections SET parent_uuid = ('.
            'SELECT p.uuid FROM (SELECT id, uuid FROM collections) AS p WHERE p.id = collections.parent_id'.
            ') WHERE collections.parent_id IS NOT NULL'
        );

        // requests.collection_uuid — collections.uuid is populated above.
        DB::statement(
            'UPDATE requests SET collection_uuid = ('.
            'SELECT uuid FROM collections WHERE collections.id = requests.collection_id'.
            ')'
        );

        DB::statement(
            'UPDATE workspace_members SET workspace_uuid = ('.
            'SELECT uuid FROM workspaces WHERE workspaces.id = workspace_members.workspace_id'.
            ')'
        );

        DB::statement(
            'UPDATE environments SET workspace_uuid = ('.
            'SELECT uuid FROM workspaces WHERE workspaces.id = environments.workspace_id'.
            ')'
        );

        DB::statement(
            'UPDATE request_history SET workspace_uuid = ('.
            'SELECT uuid FROM workspaces WHERE workspaces.id = request_history.workspace_id'.
            ')'
        );
        DB::statement(
            'UPDATE request_history SET request_uuid = ('.
            'SELECT uuid FROM requests WHERE requests.id = request_history.request_id'.
            ') WHERE request_history.request_id IS NOT NULL'
        );

        DB::statement(
            'UPDATE workspace_invitations SET workspace_uuid = ('.
            'SELECT uuid FROM workspaces WHERE workspaces.id = workspace_invitations.workspace_id'.
            ')'
        );

        DB::statement(
            'UPDATE users SET last_workspace_uuid = ('.
            'SELECT uuid FROM workspaces WHERE workspaces.id = users.last_workspace_id'.
            ') WHERE users.last_workspace_id IS NOT NULL'
        );

        DB::statement(
            'UPDATE workspace_variables SET workspace_uuid = ('.
            'SELECT uuid FROM workspaces WHERE workspaces.id = workspace_variables.workspace_id'.
            ')'
        );

        DB::statement(
            'UPDATE request_cookies SET workspace_uuid = ('.
            'SELECT uuid FROM workspaces WHERE workspaces.id = request_cookies.workspace_id'.
            ')'
        );

        DB::statement(
            'UPDATE request_files SET request_uuid = ('.
            'SELECT uuid FROM requests WHERE requests.id = request_files.request_id'.
            ')'
        );
    }

    public function down(): void
    {
        Schema::table('request_files', fn (Blueprint $table) => $table->dropColumn('request_uuid'));
        Schema::table('request_cookies', fn (Blueprint $table) => $table->dropColumn('workspace_uuid'));
        Schema::table('workspace_variables', fn (Blueprint $table) => $table->dropColumn('workspace_uuid'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('last_workspace_uuid'));
        Schema::table('workspace_invitations', fn (Blueprint $table) => $table->dropColumn('workspace_uuid'));
        Schema::table('request_history', fn (Blueprint $table) => $table->dropColumn(['workspace_uuid', 'request_uuid']));
        Schema::table('environments', fn (Blueprint $table) => $table->dropColumn('workspace_uuid'));
        Schema::table('workspace_members', fn (Blueprint $table) => $table->dropColumn('workspace_uuid'));
        Schema::table('requests', fn (Blueprint $table) => $table->dropColumn(['uuid', 'collection_uuid']));
        Schema::table('collections', fn (Blueprint $table) => $table->dropColumn(['uuid', 'workspace_uuid', 'parent_uuid']));
        Schema::table('workspaces', fn (Blueprint $table) => $table->dropColumn('uuid'));
    }
};
