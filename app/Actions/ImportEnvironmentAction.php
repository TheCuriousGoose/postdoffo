<?php

namespace App\Actions;

use App\Models\Environment;
use App\Models\Workspace;

/**
 * Imports a Postman environment export (`_postman_variable_scope: "environment"`,
 * with a flat `values` array) into a workspace Environment. Postman marks a value
 * as `type: "secret"`; anything else that reads like a credential is stored as
 * secret too, so it's masked in the UI the same way {@see ImportCollectionAction}
 * seeds its base environment.
 */
class ImportEnvironmentAction
{
    /**
     * @param  array<string, mixed>  $export
     */
    public function handle(Workspace $workspace, array $export): Environment
    {
        $name = is_string($export['name'] ?? null) ? $export['name'] : 'Imported Environment';

        $environment = $workspace->environments()->create([
            'name' => $name,
            // Activate it only if nothing else is active yet, mirroring how a
            // collection import seeds its base environment.
            'is_active' => ! $workspace->environments()->where('is_active', true)->exists(),
        ]);

        foreach ($export['values'] ?? [] as $value) {
            if (! is_array($value)) {
                continue;
            }

            $key = $value['key'] ?? null;

            if (! is_string($key) || $key === '') {
                continue;
            }

            $environment->variables()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => (string) ($value['value'] ?? ''),
                    'is_secret' => ($value['type'] ?? null) === 'secret' || $this->looksSecret($key),
                ],
            );
        }

        return $environment;
    }

    /**
     * Heuristic: a variable named like a credential (token, secret, password,
     * api key, ...) is stored as secret so its value is masked by default.
     */
    private function looksSecret(string $key): bool
    {
        return (bool) preg_match('/(secret|password|passwd|token|api[_-]?key|apikey|auth|bearer|credential|private[_-]?key)/i', $key);
    }
}
