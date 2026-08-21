<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 (final) of the workspaces/collections/requests UUID migration: drops
 * every `legacy_*` bigint column the swap migration kept around as a safety
 * net. This is the genuine point of no return in the whole migration — unlike
 * every migration before it, down() here cannot restore the original bigint
 * values, only the column shape (all NULL). Run only once the UUID cutover
 * has been verified against real data for a while.
 *
 * `id` stays a plain UNIQUE NOT NULL column rather than being promoted to the
 * literal SQL PRIMARY KEY once legacy_id is gone — deliberately, matching the
 * swap migration's own reasoning: Eloquent addresses rows through its own
 * $primaryKey regardless of the database's literal PRIMARY KEY flag, and a
 * UNIQUE index is a perfectly valid FOREIGN KEY target on both MySQL and
 * SQLite, so there is nothing to gain from reattempting that PK reassignment
 * here and a real portability risk (see the swap migration) in trying.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteAnchorTables();
        } else {
            Schema::table('workspaces', fn (Blueprint $t) => $t->dropColumn('legacy_id'));
            Schema::table('collections', fn (Blueprint $t) => $t->dropColumn(['legacy_id', 'legacy_workspace_id', 'legacy_parent_id']));
            Schema::table('requests', fn (Blueprint $t) => $t->dropColumn(['legacy_id', 'legacy_collection_id']));
        }

        Schema::table('workspace_members', fn (Blueprint $t) => $t->dropColumn('legacy_workspace_id'));
        Schema::table('environments', fn (Blueprint $t) => $t->dropColumn('legacy_workspace_id'));
        Schema::table('request_history', fn (Blueprint $t) => $t->dropColumn(['legacy_workspace_id', 'legacy_request_id']));
        Schema::table('workspace_invitations', fn (Blueprint $t) => $t->dropColumn('legacy_workspace_id'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('legacy_last_workspace_id'));
        Schema::table('workspace_variables', fn (Blueprint $t) => $t->dropColumn('legacy_workspace_id'));
        Schema::table('request_cookies', fn (Blueprint $t) => $t->dropColumn('legacy_workspace_id'));
        Schema::table('request_files', fn (Blueprint $t) => $t->dropColumn('legacy_request_id'));
    }

    /**
     * SQLite cannot drop its rowid-alias primary key. Recreate only the three
     * UUID anchors without their legacy bigint keys, preserving their data and
     * the indexes/FKs the UUID swap made authoritative.
     */
    private function rebuildSqliteAnchorTables(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        try {
            $this->replaceSqliteTable('workspaces', ['id', 'name', 'owner_id', 'created_at', 'updated_at'], function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });

            $this->replaceSqliteTable('collections', ['id', 'workspace_id', 'parent_id', 'name', 'order', 'variables', 'created_at', 'updated_at', 'headers', 'auth_type', 'auth'], function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('workspace_id');
                $table->uuid('parent_id')->nullable();
                $table->string('name');
                $table->unsignedInteger('order')->default(0);
                $table->json('variables')->nullable();
                $table->timestamps();
                $table->json('headers')->nullable();
                $table->string('auth_type')->nullable();
                $table->json('auth')->nullable();
                $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
                $table->foreign('parent_id')->references('id')->on('collections')->cascadeOnDelete();
                $table->index(['workspace_id', 'parent_id']);
            });

            $this->replaceSqliteTable('requests', ['id', 'collection_id', 'name', 'method', 'url', 'order', 'headers', 'query_params', 'body', 'body_type', 'pre_request_script', 'test_script', 'created_at', 'updated_at', 'auth_type', 'auth'], function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('collection_id');
                $table->string('name');
                $table->string('method', 10)->default('GET');
                $table->text('url');
                $table->unsignedInteger('order')->default(0);
                $table->json('headers')->nullable();
                $table->json('query_params')->nullable();
                $table->json('body')->nullable();
                $table->string('body_type')->default('none');
                $table->text('pre_request_script')->nullable();
                $table->text('test_script')->nullable();
                $table->timestamps();
                $table->string('auth_type')->nullable();
                $table->json('auth')->nullable();
                $table->foreign('collection_id')->references('id')->on('collections')->cascadeOnDelete();
                $table->index(['collection_id', 'order']);
            });
        } finally {
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }

    private function replaceSqliteTable(string $table, array $columns, Closure $define): void
    {
        $replacement = "{$table}_uuid_cutover";

        Schema::create($replacement, $define);
        DB::table($replacement)->insertUsing($columns, DB::table($table)->select($columns));
        Schema::drop($table);
        Schema::rename($replacement, $table);
    }

    /**
     * Restores the column shape only, not the data: every legacy_* column
     * comes back nullable and empty. The original bigint id values this
     * migration drops are gone for good — there is no way to recover them,
     * on purpose (this is documented as the point of no return in every
     * migration leading up to this one).
     */
    public function down(): void
    {
        Schema::table('workspaces', fn (Blueprint $t) => $t->unsignedBigInteger('legacy_id')->nullable()->after('id'));
        Schema::table('collections', function (Blueprint $t) {
            $t->unsignedBigInteger('legacy_id')->nullable()->after('id');
            $t->unsignedBigInteger('legacy_workspace_id')->nullable()->after('workspace_id');
            $t->unsignedBigInteger('legacy_parent_id')->nullable()->after('parent_id');
        });
        Schema::table('requests', function (Blueprint $t) {
            $t->unsignedBigInteger('legacy_id')->nullable()->after('id');
            $t->unsignedBigInteger('legacy_collection_id')->nullable()->after('collection_id');
        });
        Schema::table('workspace_members', fn (Blueprint $t) => $t->unsignedBigInteger('legacy_workspace_id')->nullable()->after('workspace_id'));
        Schema::table('environments', fn (Blueprint $t) => $t->unsignedBigInteger('legacy_workspace_id')->nullable()->after('workspace_id'));
        Schema::table('request_history', function (Blueprint $t) {
            $t->unsignedBigInteger('legacy_workspace_id')->nullable()->after('workspace_id');
            $t->unsignedBigInteger('legacy_request_id')->nullable()->after('request_id');
        });
        Schema::table('workspace_invitations', fn (Blueprint $t) => $t->unsignedBigInteger('legacy_workspace_id')->nullable()->after('workspace_id'));
        Schema::table('users', fn (Blueprint $t) => $t->unsignedBigInteger('legacy_last_workspace_id')->nullable()->after('last_workspace_id'));
        Schema::table('workspace_variables', fn (Blueprint $t) => $t->unsignedBigInteger('legacy_workspace_id')->nullable()->after('workspace_id'));
        Schema::table('request_cookies', fn (Blueprint $t) => $t->unsignedBigInteger('legacy_workspace_id')->nullable()->after('workspace_id'));
        Schema::table('request_files', fn (Blueprint $t) => $t->unsignedBigInteger('legacy_request_id')->nullable()->after('request_id'));
    }
};
