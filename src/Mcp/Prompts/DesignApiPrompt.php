<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;
use nameless\CodeGenerator\Support\SchemaParser;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;

#[Name('design-api')]
#[Title('Design an API')]
#[Description('Turns a description in plain words into an api-schema document, previews it with plan-api, then generates it with generate-api once the user agrees.')]
final class DesignApiPrompt extends Prompt
{
    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'description',
                description: 'What the API is about, in plain words. Example: a library that lends books to members.',
                required: true,
            ),
        ];
    }

    public function handle(Request $request): Response
    {
        $request->validate(['description' => 'required|string']);

        $types = implode(', ', FieldDefinition::CANONICAL_TYPES);
        $relations = implode(', ', SchemaParser::RELATION_KEYWORDS);
        $description = trim((string) $request->get('description'));

        return Response::text(<<<PROMPT
            Design a Laravel REST API for this description: {$description}

            1. Call list-entities to see what the project already has. Reuse an existing entity instead of creating a second one.
            2. Write an api-schema document. Entity names are PascalCase and singular, field names snake_case. Field types: {$types}, or enum(a,b) for a fixed list of values, followed by nullable, unique or default=<value> when needed. Relation types: {$relations}; declare each relation on one side only. Leave out id, timestamps and foreign keys, which the generator adds.
            3. Call plan-api with that document. Show me each entity with its fields and relations, the files it would create or update, and every warning.
            4. Wait until I agree or ask for changes. Then call generate-api with the same document and tell me to run php artisan migrate.
            PROMPT);
    }
}
