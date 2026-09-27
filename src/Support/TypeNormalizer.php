<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Support;

/**
 * Central type normalization used by every input source (JSON diagrams,
 * YAML schema files, Mermaid diagrams, database introspection).
 */
class TypeNormalizer
{
    private const SCHEMA_TYPES = [
        'int' => ['integer', 'int', 'long', 'tinyint', 'smallint', 'mediumint', 'short', 'byte'],
        'bigint' => ['bigint', 'biginteger'],
        'string' => ['str', 'string', 'varchar', 'char', 'enum', 'set', 'java.time.offsetdatetime', 'java.time.localdate'],
        'text' => ['text', 'longtext', 'mediumtext', 'tinytext', 'clob'],
        'bool' => ['boolean', 'bool'],
        'float' => ['float', 'double', 'real', 'number'],
        'decimal' => ['decimal', 'java.math.bigdecimal', 'money'],
        'date' => ['date', 'localdate'],
        'datetime' => ['datetime', 'timestamp', 'localdatetime'],
        'time' => ['time', 'localtime'],
        'json' => ['json', 'jsonb', 'array', 'list', 'map', 'object', 'java.util.map', 'java.util.list'],
        'uuid' => ['uuid'],
    ];

    /**
     * Normalize type names from schema-like sources (UML, Java, YAML, Mermaid)
     * to the field type vocabulary accepted by FieldDefinition. Unknown types
     * become strings, and list_* types (list_uuid, list_string) become json.
     */
    public static function fromSchemaType(string $type): string
    {
        $lower = strtolower($type);

        foreach (self::SCHEMA_TYPES as $normalized => $names) {
            if (in_array($lower, $names, true)) {
                return $normalized;
            }
        }

        return str_starts_with($lower, 'list_') ? 'json' : 'string';
    }

    public static function isKnownSchemaType(string $type): bool
    {
        $lower = strtolower($type);

        return str_starts_with($lower, 'list_') || in_array($lower, array_merge(...array_values(self::SCHEMA_TYPES)), true);
    }

    /**
     * Map driver-specific database column types (varchar(255), tinyint(1), ...)
     * to the field type vocabulary used by make:fullapi.
     */
    public static function fromDatabaseType(string $rawType): string
    {
        $t = strtolower($rawType);

        return match (true) {
            str_contains($t, 'char'), str_contains($t, 'varchar'), $t === 'string' => 'string',
            str_contains($t, 'text') => 'text',
            str_contains($t, 'bigint') => 'bigint',
            str_contains($t, 'int') => 'integer',
            str_contains($t, 'bool'), $t === 'tinyint(1)' => 'boolean',
            str_contains($t, 'decimal'), str_contains($t, 'numeric') => 'decimal',
            str_contains($t, 'float'), str_contains($t, 'double'), str_contains($t, 'real') => 'float',
            str_contains($t, 'json') => 'json',
            str_contains($t, 'datetime'), str_contains($t, 'timestamp') => 'datetime',
            str_contains($t, 'date') => 'date',
            str_contains($t, 'time') => 'time',
            str_contains($t, 'uuid') => 'uuid',
            default => 'string',
        };
    }
}
