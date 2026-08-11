<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * The migration that first encrypted these columns reached for the `encrypt()`
 * helper, which serializes its argument. The `encrypted` cast on the models
 * reads with unserialize turned off, so every row it converted came back one
 * layer short: an environment variable rendered as `s:32:"https://…";`, and a
 * recorded response snapshot (cast `encrypted:array`) json_decoded that same
 * payload to null and lost the body entirely.
 *
 * That migration now writes with `encryptString`, which fixes installs that
 * have yet to run it. This one repairs the rows that already went through the
 * old version: decrypt, peel off the serialization, put it back the way the
 * cast expects to find it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->repairColumn('environment_variables', 'value');
        $this->repairColumn('workspace_variables', 'value');
        $this->repairColumn('request_history', 'response_snapshot');
    }

    public function down(): void
    {
        // Deliberately empty. Re-serializing would restore a bug, not a state.
    }

    private function repairColumn(string $table, string $column): void
    {
        DB::table($table)
            ->select('id', $column)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table, $column) {
                foreach ($rows as $row) {
                    $next = $this->repair($row->{$column});

                    if ($next !== null && $next !== $row->{$column}) {
                        DB::table($table)->where('id', $row->id)->update([$column => $next]);
                    }
                }
            });
    }

    /**
     * The repaired ciphertext, or null when the row needs no repair.
     *
     * Anything that doesn't decrypt, or that decrypts to something which isn't
     * a serialized string, is left exactly as it is — rows written through the
     * model since the bad migration ran are already correct, and this has to
     * run past them without touching them.
     */
    private function repair(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $plain = Crypt::decryptString($value);
        } catch (Throwable) {
            return null;
        }

        if (preg_match('/^s:\d+:"/', $plain) !== 1) {
            return null;
        }

        $unserialized = @unserialize($plain, ['allowed_classes' => false]);

        if (! is_string($unserialized)) {
            return null;
        }

        return Crypt::encryptString($unserialized);
    }
};
