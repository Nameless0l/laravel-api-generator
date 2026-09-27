<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Mcp\Tools;

use Illuminate\Support\Facades\File;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use nameless\CodeGenerator\Support\Manifest;
use nameless\CodeGenerator\Support\SchemaParser;

#[Name('list-entities')]
#[Title('List generated entities')]
#[Description('Entities generated before (from .api-generator/manifest.json), each file with its status: intact, edited by hand, or missing. Also names the api-schema file at the project root, if any.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
final class ListEntitiesTool extends Tool
{
    public function handle(): ResponseFactory
    {
        $manifest = Manifest::load(base_path());
        $entities = [];

        foreach ($manifest->entries() as $path => $entry) {
            $entities[$entry['entity'] ?? ''][] = [
                'path' => $path,
                'kind' => $entry['kind'],
                'status' => $this->status($manifest, $path),
            ];
        }

        unset($entities['']);
        ksort($entities);

        return Response::structured([
            'manifest' => $manifest->exists(),
            'schemaFile' => collect(SchemaParser::DEFAULT_FILES)->first(fn (string $file) => File::exists(base_path($file))),
            'entities' => array_map(
                fn (string $name, array $files) => ['name' => $name, 'files' => $files],
                array_keys($entities),
                array_values($entities)
            ),
        ]);
    }

    private function status(Manifest $manifest, string $path): string
    {
        if (! File::exists(base_path($path))) {
            return 'missing';
        }

        return $manifest->isPristine($path, File::get(base_path($path))) === false ? 'edited' : 'intact';
    }
}
