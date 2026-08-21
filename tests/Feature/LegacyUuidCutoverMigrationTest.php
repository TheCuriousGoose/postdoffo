<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyUuidCutoverMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_legacy_bigint_columns_are_removed(): void
    {
        $columns = [
            'workspaces' => ['legacy_id'],
            'collections' => ['legacy_id', 'legacy_workspace_id', 'legacy_parent_id'],
            'requests' => ['legacy_id', 'legacy_collection_id'],
            'workspace_members' => ['legacy_workspace_id'],
            'environments' => ['legacy_workspace_id'],
            'request_history' => ['legacy_workspace_id', 'legacy_request_id'],
            'workspace_invitations' => ['legacy_workspace_id'],
            'users' => ['legacy_last_workspace_id'],
            'workspace_variables' => ['legacy_workspace_id'],
            'request_cookies' => ['legacy_workspace_id'],
            'request_files' => ['legacy_request_id'],
        ];

        foreach ($columns as $table => $legacyColumns) {
            foreach ($legacyColumns as $column) {
                $this->assertFalse(Schema::hasColumn($table, $column));
            }
        }
    }
}
