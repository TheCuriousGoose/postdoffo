<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithWorkspaces;
use Illuminate\Support\Str;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

abstract class BaseTool extends Tool
{
    use InteractsWithWorkspaces;

    /**
     * Tool names are snake_case rather than the package's default kebab-case:
     * that is the convention across the MCP ecosystem, and it is the form the
     * server instructions and every tool description refer to each other by, so
     * a model reading "call get_workspace first" finds a tool by that name.
     */
    public function name(): string
    {
        return Str::snake(class_basename($this));
    }

    /**
     * Every tool answers with JSON, both as text and as structured content, so a
     * client that understands structured tool output can consume it directly and
     * one that doesn't still gets something the model can read.
     *
     * @param  array<string, mixed>  $data
     */
    protected function json(array $data): ResponseFactory
    {
        return Response::structured($data);
    }
}
