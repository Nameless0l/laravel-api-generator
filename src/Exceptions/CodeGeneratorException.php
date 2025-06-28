<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Exceptions;

use Exception;

class CodeGeneratorException extends Exception
{
    public static function invalidEntityName(string $name): self
    {
        return new self("Invalid entity name: {$name}");
    }

    public static function fileNotFound(string $path): self
    {
        return new self("File not found: {$path}");
    }

    public static function fileCreationFailed(string $path): self
    {
        return new self("Failed to create file: {$path}");
    }

    public static function invalidJsonData(string $error): self
    {
        return new self("Invalid JSON data: {$error}");
    }

    public static function generationFailed(string $type, string $reason): self
    {
        return new self("Failed to generate {$type}: {$reason}");
    }
}
