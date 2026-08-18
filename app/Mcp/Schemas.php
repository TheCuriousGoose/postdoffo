<?php

namespace App\Mcp;

use App\Enums\AuthType;
use App\Enums\BodyType;
use App\Enums\HttpMethod;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

final class Schemas
{
    public static function method(JsonSchema $schema): Type
    {
        return $schema->string()
            ->enum(array_column(HttpMethod::cases(), 'value'))
            ->description('HTTP method.');
    }

    public static function bodyType(JsonSchema $schema): Type
    {
        return $schema->string()
            ->enum(array_column(BodyType::cases(), 'value'))
            ->description(
                'Which shape the body is in. Must match the "body" argument: '
                    .'"json" -> {"json": {...}}, "raw" -> {"raw": "..."}, '
                    .'"form_data"/"urlencoded" -> {"fields": [{"key": ..., "value": ...}]}, '
                    .'"none" -> no body.'
            );
    }

    public static function authType(JsonSchema $schema): Type
    {
        return $schema->string()
            ->enum(array_column(AuthType::cases(), 'value'))
            ->description(
                'Authentication scheme. Leave unset to inherit from the parent collection, '
                    .'or use "none" to explicitly send nothing.'
            );
    }

    public static function keyValueList(JsonSchema $schema, string $description): Type
    {
        return $schema->array()
            ->items($schema->object([
                'key' => $schema->string()->required(),
                'value' => $schema->string(),
                'enabled' => $schema->boolean()->description('Defaults to true. A disabled row is kept but not sent.'),
            ]))
            ->description($description);
    }

    public static function body(JsonSchema $schema): Type
    {
        return $schema->object([
            'json' => $schema->object()->description('For body_type "json": the payload, sent as application/json.'),
            'raw' => $schema->string()->description('For body_type "raw": the literal body string.'),
            'fields' => $schema->array()
                ->items($schema->object([
                    'key' => $schema->string()->required(),
                    'value' => $schema->string(),
                    'enabled' => $schema->boolean(),
                ]))
                ->description('For body_type "form_data" or "urlencoded": the fields to send.'),
        ])->description(
            'The request body, keyed by body_type. Values may contain {{variable}} references. '
                .'File uploads cannot be attached over MCP — add those in the app.'
        );
    }

    public static function auth(JsonSchema $schema): Type
    {
        return $schema->object([
            'token' => $schema->string()->description('For auth_type "bearer". Prefer a {{variable}} over a literal token.'),
            'username' => $schema->string()->description('For auth_type "basic".'),
            'password' => $schema->string()->description('For auth_type "basic".'),
            'key' => $schema->string()->description('For auth_type "apikey": the header or query parameter name.'),
            'value' => $schema->string()->description('For auth_type "apikey": the value.'),
            'in' => $schema->string()->enum(['header', 'query'])->description('For auth_type "apikey". Defaults to "header".'),
        ])->description('Credentials for the chosen auth_type.');
    }

    public static function testScript(JsonSchema $schema): Type
    {
        return $schema->string()->description(
            'Assertions run against the response, one statement per line. This is NOT JavaScript — '
                .'it is a small fixed grammar (pm.test, pm.response.status, pm.response.json.*, '
                .'pm.environment.set, ...). Read the "scripting-reference" resource before writing one; '
                .'a statement outside the grammar is reported as a failed test.'
        );
    }

    public static function preRequestScript(JsonSchema $schema): Type
    {
        return $schema->string()->description(
            'Statements run before the request is sent, in the same grammar as test_script. '
                .'Useful for pm.request.headers.set() and pm.variables.set().'
        );
    }
}
