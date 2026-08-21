<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 of the workspaces/collections/requests UUID migration. Cuts the
 * whole graph over: drops every old bigint foreign key, retires each old
 * bigint id/FK column by renaming it to `legacy_*` (never dropping it here),
 * and promotes the uuid columns from the previous migration into their
 * place, with fresh foreign keys/indexes/unique constraints matching the
 * originals exactly (including preserving explicitly-named constraints like
 * request_cookies_identity_unique).
 *
 * The legacy_* columns are intentionally left in place — dropping them is a
 * separate, later migration to run only once the cutover has been verified
 * against real data. That means this migration's down() is a real, complete
 * reversal: nothing is destroyed here, only renamed and re-keyed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dropOldForeignKeysAndCoveringIndexes();

        $this->swapOwnPrimaryKey('workspaces');
        $this->swapCollections();
        $this->swapRequests();
        $this->swapWorkspaceMembers();
        $this->swapEnvironments();
        $this->swapRequestHistory();
        $this->swapWorkspaceInvitations();
        $this->swapUsers();
        $this->swapWorkspaceVariables();
        $this->swapRequestCookies();
        $this->swapRequestFiles();
    }

    /**
     * Drops every old bigint FK across the graph, plus any index that
     * references the retiring column — found by inspecting the live schema
     * rather than assuming a naming convention, since MySQL/MariaDB names
     * the index it auto-creates to support a FK after the FK constraint
     * itself (not Laravel's own `{table}_{column}_index` convention), and
     * that index is NOT dropped automatically by `dropForeign()`. Left in
     * place, it would collide with the new FK's auto-named index once the
     * uuid column is renamed into the old column's spot.
     */
    private function dropOldForeignKeysAndCoveringIndexes(): void
    {
        $targets = [
            'collections' => ['workspace_id', 'parent_id'],
            'requests' => ['collection_id'],
            'workspace_members' => ['workspace_id'],
            'environments' => ['workspace_id'],
            'request_history' => ['workspace_id', 'request_id'],
            'workspace_invitations' => ['workspace_id'],
            'users' => ['last_workspace_id'],
            'workspace_variables' => ['workspace_id'],
            'request_cookies' => ['workspace_id'],
            'request_files' => ['request_id'],
        ];

        foreach ($targets as $table => $columns) {
            foreach ($columns as $column) {
                Schema::table($table, fn (Blueprint $t) => $t->dropForeign([$column]));
                $this->dropIndexesReferencingColumn($table, $column);
            }
        }
    }

    private function dropIndexesReferencingColumn(string $table, string $column): void
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['primary'] || ! in_array($column, $index['columns'], true)) {
                continue;
            }

            Schema::table($table, fn (Blueprint $t) => $t->dropIndex($index['name']));
        }
    }

    /**
     * Drops a column's FK plus any index referencing it (see
     * dropIndexesReferencingColumn — MySQL/MariaDB don't drop the
     * FK-support index automatically). Used by down() to retire the
     * uuid-sourced FK columns before renaming the legacy ones back into
     * place, mirroring what dropOldForeignKeysAndCoveringIndexes does in up().
     */
    private function retireColumn(string $table, string $column): void
    {
        Schema::table($table, fn (Blueprint $t) => $t->dropForeign([$column]));
        $this->dropIndexesReferencingColumn($table, $column);
    }

    /**
     * Retires the old auto-increment `id` on an anchor table (workspaces,
     * collections, requests) and promotes `uuid` in its place.
     *
     * Deliberately does NOT try to move the literal SQL PRIMARY KEY from
     * legacy_id onto id: confirmed empirically that SQLite's dropPrimary()/
     * primary() are no-ops against an existing table — legacy_id silently
     * stays SQLite's rowid-aliasing primary key no matter what, which is
     * harmless (Eloquent addresses rows through the model's own $primaryKey
     * = 'id', never the database's literal PRIMARY KEY flag, and a plain
     * UNIQUE index — which `id` already carries from the additive migration
     * — is a perfectly valid target for another table's FOREIGN KEY on both
     * MySQL and SQLite). legacy_id also keeps whatever AUTO_INCREMENT status
     * it already had, so it goes on quietly self-populating on every future
     * insert instead of needing to be stripped or relaxed to nullable.
     */
    private function swapOwnPrimaryKey(string $table): void
    {
        Schema::table($table, function (Blueprint $t) {
            $t->renameColumn('id', 'legacy_id');
            $t->renameColumn('uuid', 'id');
        });

        // Unlike MySQL/MariaDB (which always enforces NOT NULL on a UNIQUE/
        // PRIMARY column regardless of its own declared nullability), SQLite
        // does not imply NOT NULL here — make it explicit so the constraint
        // actually holds on the driver the test suite runs on.
        Schema::table($table, function (Blueprint $t) {
            $t->uuid('id')->nullable(false)->change();
        });
    }

    private function swapCollections(): void
    {
        $this->swapOwnPrimaryKey('collections');

        Schema::table('collections', function (Blueprint $table) {
            $table->renameColumn('workspace_id', 'legacy_workspace_id');
            $table->renameColumn('workspace_uuid', 'workspace_id');
            $table->renameColumn('parent_id', 'legacy_parent_id');
            $table->renameColumn('parent_uuid', 'parent_id');
        });

        // workspace_id was required before the migration; the staging uuid column it
        // was renamed from was nullable (had to be, before backfill), so restore NOT
        // NULL now that every row has a value. parent_id stays nullable, as before.
        // legacy_workspace_id goes the other way: it's retired and nothing populates
        // it going forward, so its old NOT NULL (inherited from the pre-rename column)
        // would block every future insert unless relaxed.
        Schema::table('collections', function (Blueprint $table) {
            $table->uuid('workspace_id')->nullable(false)->change();
            $table->unsignedBigInteger('legacy_workspace_id')->nullable()->change();
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('collections')->cascadeOnDelete();
            $table->index(['workspace_id', 'parent_id']);
        });
    }

    private function swapRequests(): void
    {
        $this->swapOwnPrimaryKey('requests');

        Schema::table('requests', function (Blueprint $table) {
            $table->renameColumn('collection_id', 'legacy_collection_id');
            $table->renameColumn('collection_uuid', 'collection_id');
        });

        Schema::table('requests', function (Blueprint $table) {
            $table->uuid('collection_id')->nullable(false)->change();
            $table->unsignedBigInteger('legacy_collection_id')->nullable()->change();
        });

        Schema::table('requests', function (Blueprint $table) {
            $table->foreign('collection_id')->references('id')->on('collections')->cascadeOnDelete();
            $table->index(['collection_id', 'order']);
        });
    }

    private function swapWorkspaceMembers(): void
    {
        Schema::table('workspace_members', function (Blueprint $table) {
            $table->renameColumn('workspace_id', 'legacy_workspace_id');
            $table->renameColumn('workspace_uuid', 'workspace_id');
        });

        Schema::table('workspace_members', function (Blueprint $table) {
            $table->uuid('workspace_id')->nullable(false)->change();
            $table->unsignedBigInteger('legacy_workspace_id')->nullable()->change();
        });

        Schema::table('workspace_members', function (Blueprint $table) {
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->unique(['workspace_id', 'user_id']);
        });
    }

    private function swapEnvironments(): void
    {
        Schema::table('environments', function (Blueprint $table) {
            $table->renameColumn('workspace_id', 'legacy_workspace_id');
            $table->renameColumn('workspace_uuid', 'workspace_id');
        });

        Schema::table('environments', function (Blueprint $table) {
            $table->uuid('workspace_id')->nullable(false)->change();
            $table->unsignedBigInteger('legacy_workspace_id')->nullable()->change();
        });

        Schema::table('environments', function (Blueprint $table) {
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->index(['workspace_id', 'is_active']);
        });
    }

    private function swapRequestHistory(): void
    {
        Schema::table('request_history', function (Blueprint $table) {
            $table->renameColumn('workspace_id', 'legacy_workspace_id');
            $table->renameColumn('workspace_uuid', 'workspace_id');
            $table->renameColumn('request_id', 'legacy_request_id');
            $table->renameColumn('request_uuid', 'request_id');
        });

        // Only workspace_id was required originally; request_id was already nullable
        // (and so is legacy_request_id, unchanged). legacy_workspace_id still needs
        // relaxing since it inherited the original column's NOT NULL.
        Schema::table('request_history', function (Blueprint $table) {
            $table->uuid('workspace_id')->nullable(false)->change();
            $table->unsignedBigInteger('legacy_workspace_id')->nullable()->change();
        });

        Schema::table('request_history', function (Blueprint $table) {
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('request_id')->references('id')->on('requests')->nullOnDelete();
            $table->index(['workspace_id', 'executed_at']);
        });
    }

    private function swapWorkspaceInvitations(): void
    {
        Schema::table('workspace_invitations', function (Blueprint $table) {
            $table->renameColumn('workspace_id', 'legacy_workspace_id');
            $table->renameColumn('workspace_uuid', 'workspace_id');
        });

        Schema::table('workspace_invitations', function (Blueprint $table) {
            $table->uuid('workspace_id')->nullable(false)->change();
            $table->unsignedBigInteger('legacy_workspace_id')->nullable()->change();
        });

        Schema::table('workspace_invitations', function (Blueprint $table) {
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->unique(['workspace_id', 'email']);
        });
    }

    private function swapUsers(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('last_workspace_id', 'legacy_last_workspace_id');
            $table->renameColumn('last_workspace_uuid', 'last_workspace_id');
        });

        // last_workspace_id was nullable originally — no NOT NULL change needed.
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('last_workspace_id')->references('id')->on('workspaces')->nullOnDelete();
        });
    }

    private function swapWorkspaceVariables(): void
    {
        Schema::table('workspace_variables', function (Blueprint $table) {
            $table->renameColumn('workspace_id', 'legacy_workspace_id');
            $table->renameColumn('workspace_uuid', 'workspace_id');
        });

        Schema::table('workspace_variables', function (Blueprint $table) {
            $table->uuid('workspace_id')->nullable(false)->change();
            $table->unsignedBigInteger('legacy_workspace_id')->nullable()->change();
        });

        Schema::table('workspace_variables', function (Blueprint $table) {
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->unique(['workspace_id', 'key']);
        });
    }

    private function swapRequestCookies(): void
    {
        Schema::table('request_cookies', function (Blueprint $table) {
            $table->renameColumn('workspace_id', 'legacy_workspace_id');
            $table->renameColumn('workspace_uuid', 'workspace_id');
        });

        Schema::table('request_cookies', function (Blueprint $table) {
            $table->uuid('workspace_id')->nullable(false)->change();
            $table->unsignedBigInteger('legacy_workspace_id')->nullable()->change();
        });

        Schema::table('request_cookies', function (Blueprint $table) {
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->unique(['workspace_id', 'user_id', 'domain', 'path', 'name'], 'request_cookies_identity_unique');
            $table->index(['workspace_id', 'user_id', 'domain']);
        });
    }

    private function swapRequestFiles(): void
    {
        Schema::table('request_files', function (Blueprint $table) {
            $table->renameColumn('request_id', 'legacy_request_id');
            $table->renameColumn('request_uuid', 'request_id');
        });

        Schema::table('request_files', function (Blueprint $table) {
            $table->uuid('request_id')->nullable(false)->change();
            $table->unsignedBigInteger('legacy_request_id')->nullable()->change();
        });

        Schema::table('request_files', function (Blueprint $table) {
            $table->foreign('request_id')->references('id')->on('requests')->cascadeOnDelete();
        });
    }

    /**
     * Reverts in the SAME parent-first order as up(), not the reverse: each
     * child's FK-add step below needs its parent's column already back to
     * bigint at that moment (MySQL/MariaDB require matching FK column types),
     * so workspaces/collections/requests must be reverted before anything
     * that references them re-adds its old FK.
     */
    public function down(): void
    {
        $this->unswapOwnPrimaryKey('workspaces');
        $this->unswapCollections();
        $this->unswapRequests();
        $this->unswapWorkspaceMembers();
        $this->unswapEnvironments();
        $this->unswapRequestHistory();
        $this->unswapWorkspaceInvitations();
        $this->unswapUsers();
        $this->unswapWorkspaceVariables();
        $this->unswapRequestCookies();
        $this->unswapRequestFiles();
    }

    /**
     * legacy_id never lost its PRIMARY KEY/AUTO_INCREMENT status going up
     * (see swapOwnPrimaryKey) — renaming it back to `id` is a full reversal
     * on its own, no key surgery needed here either.
     */
    private function unswapOwnPrimaryKey(string $table): void
    {
        Schema::table($table, function (Blueprint $t) {
            $t->renameColumn('id', 'uuid');
            $t->renameColumn('legacy_id', 'id');
        });
    }

    private function unswapCollections(): void
    {
        $this->retireColumn('collections', 'workspace_id');
        $this->retireColumn('collections', 'parent_id');

        Schema::table('collections', function (Blueprint $table) {
            $table->renameColumn('workspace_id', 'workspace_uuid');
            $table->renameColumn('legacy_workspace_id', 'workspace_id');
            $table->renameColumn('parent_id', 'parent_uuid');
            $table->renameColumn('legacy_parent_id', 'parent_id');
        });

        // workspace_id (restored from legacy_workspace_id) was NOT NULL originally —
        // relaxed going up only because it was retired at that point, so restore it.
        Schema::table('collections', function (Blueprint $table) {
            $table->unsignedBigInteger('workspace_id')->nullable(false)->change();
        });

        $this->unswapOwnPrimaryKey('collections');

        Schema::table('collections', function (Blueprint $table) {
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('collections')->cascadeOnDelete();
            $table->index(['workspace_id', 'parent_id']);
        });
    }

    private function unswapRequests(): void
    {
        $this->retireColumn('requests', 'collection_id');

        Schema::table('requests', function (Blueprint $table) {
            $table->renameColumn('collection_id', 'collection_uuid');
            $table->renameColumn('legacy_collection_id', 'collection_id');
        });

        Schema::table('requests', function (Blueprint $table) {
            $table->unsignedBigInteger('collection_id')->nullable(false)->change();
        });

        $this->unswapOwnPrimaryKey('requests');

        Schema::table('requests', function (Blueprint $table) {
            $table->foreign('collection_id')->references('id')->on('collections')->cascadeOnDelete();
            $table->index(['collection_id', 'order']);
        });
    }

    private function unswapWorkspaceMembers(): void
    {
        $this->retireColumn('workspace_members', 'workspace_id');

        Schema::table('workspace_members', function (Blueprint $table) {
            $table->renameColumn('workspace_id', 'workspace_uuid');
            $table->renameColumn('legacy_workspace_id', 'workspace_id');
        });

        Schema::table('workspace_members', function (Blueprint $table) {
            $table->unsignedBigInteger('workspace_id')->nullable(false)->change();
        });

        Schema::table('workspace_members', function (Blueprint $table) {
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->unique(['workspace_id', 'user_id']);
        });
    }

    private function unswapEnvironments(): void
    {
        $this->retireColumn('environments', 'workspace_id');

        Schema::table('environments', function (Blueprint $table) {
            $table->renameColumn('workspace_id', 'workspace_uuid');
            $table->renameColumn('legacy_workspace_id', 'workspace_id');
        });

        Schema::table('environments', function (Blueprint $table) {
            $table->unsignedBigInteger('workspace_id')->nullable(false)->change();
        });

        Schema::table('environments', function (Blueprint $table) {
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->index(['workspace_id', 'is_active']);
        });
    }

    private function unswapRequestHistory(): void
    {
        $this->retireColumn('request_history', 'workspace_id');
        $this->retireColumn('request_history', 'request_id');

        Schema::table('request_history', function (Blueprint $table) {
            $table->renameColumn('workspace_id', 'workspace_uuid');
            $table->renameColumn('legacy_workspace_id', 'workspace_id');
            $table->renameColumn('request_id', 'request_uuid');
            $table->renameColumn('legacy_request_id', 'request_id');
        });

        // Only workspace_id was NOT NULL originally; request_id stays nullable.
        Schema::table('request_history', function (Blueprint $table) {
            $table->unsignedBigInteger('workspace_id')->nullable(false)->change();
        });

        Schema::table('request_history', function (Blueprint $table) {
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('request_id')->references('id')->on('requests')->nullOnDelete();
            $table->index(['workspace_id', 'executed_at']);
        });
    }

    private function unswapWorkspaceInvitations(): void
    {
        $this->retireColumn('workspace_invitations', 'workspace_id');

        Schema::table('workspace_invitations', function (Blueprint $table) {
            $table->renameColumn('workspace_id', 'workspace_uuid');
            $table->renameColumn('legacy_workspace_id', 'workspace_id');
        });

        Schema::table('workspace_invitations', function (Blueprint $table) {
            $table->unsignedBigInteger('workspace_id')->nullable(false)->change();
        });

        Schema::table('workspace_invitations', function (Blueprint $table) {
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->unique(['workspace_id', 'email']);
        });
    }

    private function unswapUsers(): void
    {
        $this->retireColumn('users', 'last_workspace_id');

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('last_workspace_id', 'last_workspace_uuid');
            $table->renameColumn('legacy_last_workspace_id', 'last_workspace_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('last_workspace_id')->references('id')->on('workspaces')->nullOnDelete();
        });
    }

    private function unswapWorkspaceVariables(): void
    {
        $this->retireColumn('workspace_variables', 'workspace_id');

        Schema::table('workspace_variables', function (Blueprint $table) {
            $table->renameColumn('workspace_id', 'workspace_uuid');
            $table->renameColumn('legacy_workspace_id', 'workspace_id');
        });

        Schema::table('workspace_variables', function (Blueprint $table) {
            $table->unsignedBigInteger('workspace_id')->nullable(false)->change();
        });

        Schema::table('workspace_variables', function (Blueprint $table) {
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->unique(['workspace_id', 'key']);
        });
    }

    private function unswapRequestCookies(): void
    {
        $this->retireColumn('request_cookies', 'workspace_id');

        Schema::table('request_cookies', function (Blueprint $table) {
            $table->renameColumn('workspace_id', 'workspace_uuid');
            $table->renameColumn('legacy_workspace_id', 'workspace_id');
        });

        Schema::table('request_cookies', function (Blueprint $table) {
            $table->unsignedBigInteger('workspace_id')->nullable(false)->change();
        });

        Schema::table('request_cookies', function (Blueprint $table) {
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->unique(['workspace_id', 'user_id', 'domain', 'path', 'name'], 'request_cookies_identity_unique');
            $table->index(['workspace_id', 'user_id', 'domain']);
        });
    }

    private function unswapRequestFiles(): void
    {
        $this->retireColumn('request_files', 'request_id');

        Schema::table('request_files', function (Blueprint $table) {
            $table->renameColumn('request_id', 'request_uuid');
            $table->renameColumn('legacy_request_id', 'request_id');
        });

        Schema::table('request_files', function (Blueprint $table) {
            $table->unsignedBigInteger('request_id')->nullable(false)->change();
        });

        Schema::table('request_files', function (Blueprint $table) {
            $table->foreign('request_id')->references('id')->on('requests')->cascadeOnDelete();
        });
    }
};
