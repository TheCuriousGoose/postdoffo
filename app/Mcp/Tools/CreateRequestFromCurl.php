<?php

namespace App\Mcp\Tools;

use App\Exceptions\InvalidCurlCommandException;
use App\Mcp\Presenter;
use App\Services\CurlCommandParser;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Create request from curl')]
#[Description(
    'Turn a curl command into a saved request — method, url, headers and body are all read off '
    .'the command. This is the shortest path from an API doc, a browser "copy as cURL", or a '
    .'shell snippet into a collection, and it beats transcribing the pieces by hand. Afterwards, '
    .'consider swapping literal hosts and tokens for {{variables}} with update_request.'
)]
class CreateRequestFromCurl extends BaseTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'collection_id' => $schema->string()->required(),
            'command' => $schema->string()->max(100_000)->description('The full curl command, line continuations and all.')->required(),
            'name' => $schema->string()->max(255)->description('Overrides the name derived from the url.'),
        ];
    }

    public function handle(Request $request, CurlCommandParser $parser): ResponseFactory
    {
        $validated = $request->validate([
            'collection_id' => ['required', 'string', 'uuid'],
            'command' => ['required', 'string', 'max:100000'],
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        $collection = $this->collection($validated['collection_id'], 'edit');

        try {
            $attributes = $parser->parse($validated['command']);
        } catch (InvalidCurlCommandException $e) {
            throw ValidationException::withMessages(['command' => $e->getMessage()]);
        }

        if (filled($validated['name'] ?? null)) {
            $attributes['name'] = $validated['name'];
        }

        $apiRequest = $collection->requests()->create([
            ...$attributes,
            'order' => $collection->requests()->count(),
        ]);

        return $this->json([
            'request' => Presenter::request($apiRequest),
        ]);
    }
}
