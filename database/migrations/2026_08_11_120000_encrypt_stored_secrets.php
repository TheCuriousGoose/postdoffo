<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Variable values and recorded response bodies are the two places long-lived
 * credentials come to rest: an API token typed into an environment, and the same
 * token echoed back by a login endpoint and snapshotted into history. Both were
 * stored in the clear, so anyone holding a database dump or a backup held the
 * workspace's secrets too.
 *
 * The models now cast these columns as encrypted, which leaves this migration to
 * bring existing rows over. Encryption is keyed on APP_KEY — losing or rotating
 * that key without re-encrypting makes these values unrecoverable, same as any
 * other encrypted Laravel column.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The ciphertext isn't valid JSON, so a MySQL json column would reject it
        // outright. longText because a snapshot holds a whole response body.
        Schema::table('request_history', function (Blueprint $table) {
            $table->longText('response_snapshot')->nullable()->change();
        });

        $this->mapColumn('environment_variables', 'value', fn (?string $v) => $this->encrypt($v));
        $this->mapColumn('workspace_variables', 'value', fn (?string $v) => $this->encrypt($v));
        $this->mapColumn('request_history', 'response_snapshot', fn (?string $v) => $this->encrypt($v));
    }

    public function down(): void
    {
        $this->mapColumn('environment_variables', 'value', fn (?string $v) => $this->decrypt($v));
        $this->mapColumn('workspace_variables', 'value', fn (?string $v) => $this->decrypt($v));
        $this->mapColumn('request_history', 'response_snapshot', fn (?string $v) => $this->decrypt($v));

        Schema::table('request_history', function (Blueprint $table) {
            $table->json('response_snapshot')->nullable()->change();
        });
    }

    /**
     * Rewrite one column row by row, in chunks — history in particular can be
     * long, and holding every response body in memory to convert it would not go
     * well on a busy workspace.
     */
    private function mapColumn(string $table, string $column, callable $transform): void
    {
        DB::table($table)
            ->select('id', $column)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table, $column, $transform) {
                foreach ($rows as $row) {
                    $next = $transform($row->{$column});

                    if ($next !== $row->{$column}) {
                        DB::table($table)->where('id', $row->id)->update([$column => $next]);
                    }
                }
            });
    }

    /**
     * encryptString, not the encrypt() helper: the helper serializes what it is
     * given, while the `encrypted` cast on the models reads with unserialize
     * turned off. Mixing the two hands the application back the serialized
     * payload — `s:32:"https://…";` — instead of the value.
     */
    private function encrypt(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Decryptable already means a previous run got here first — leave it be
        // rather than wrapping the ciphertext in a second layer.
        try {
            Crypt::decryptString($value);

            return $value;
        } catch (Throwable) {
            return Crypt::encryptString($value);
        }
    }

    private function decrypt(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return $value;
        }
    }
};
