<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Exceptions;

use Exception;
use Throwable;

class CodeGeneratorException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'generation_failed',
        public readonly ?string $hint = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function invalidEntityName(string $name): self
    {
        return new self("Invalid entity name: {$name}", 'invalid_entity_name');
    }

    public static function fileNotFound(string $path): self
    {
        return new self("File not found: {$path}", 'file_not_found', 'Paths are resolved from the project root.');
    }

    public static function fileCreationFailed(string $path): self
    {
        return new self("Failed to create file: {$path}", 'write_failed');
    }

    public static function invalidJsonData(string $error): self
    {
        return new self("Invalid JSON data: {$error}", 'invalid_json');
    }

    public static function generationFailed(string $type, string $reason, ?Throwable $previous = null): self
    {
        return new self(
            "Failed to generate {$type}: {$reason}",
            $previous instanceof self ? $previous->errorCode : 'generation_failed',
            $previous instanceof self ? $previous->hint : null,
            $previous
        );
    }

    public static function invalidSchema(string $source, string $reason): self
    {
        return new self(
            "Invalid schema in {$source}: {$reason}",
            'invalid_schema',
            'Check a schema without writing anything: php artisan make:fullapi --schema=<file> --dry-run'
        );
    }

    public static function invalidDiagram(string $source, string $reason): self
    {
        return new self("Invalid Mermaid diagram in {$source}: {$reason}", 'invalid_diagram');
    }

    public static function invalidRequest(string $reason): self
    {
        return new self($reason, 'invalid_request');
    }
}
