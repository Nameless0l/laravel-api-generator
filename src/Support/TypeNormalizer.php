<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Support;

/**
 * Central type normalization used by every input source (JSON diagrams,
 * YAML schema files, Mermaid diagrams, database introspection).
 */
class TypeNormalizer
{
    /**
     * Normalize type names from schema-like sources (UML, Java, YAML, Mermaid)
     * to the field type vocabulary accepted by FieldDefinition.
     */
    public static function fromSchemaType(string $type): string
    {
        return match (strtolower($type)) {
            // Integer variants
            'integer', 'int', 'long', 'tinyint', 'smallint', 'mediumint', 'short', 'byte' => 'int',
            'bigint', 'biginteger' => 'bigint',

            // String variants
            'str', 'string', 'varchar', 'char', 'enum', 'set',
            'java.time.offsetdatetime', 'java.time.localdate' => 'string',

            // Text
            'text', 'longtext', 'mediumtext', 'tinytext', 'clob' => 'text',

            // Boolean
            'boolean', 'bool' => 'bool',

            // Float/Decimal variants
            'float', 'double', 'real', 'number' => 'float',
            'decimal', 'java.math.bigdecimal', 'money' => 'decimal',

            // Date/Time
            'date', 'localdate' => 'date',
            'datetime', 'timestamp', 'localdatetime' => 'datetime',
            'time', 'localtime' => 'time',

            // JSON / Array
            'json', 'jsonb', 'array', 'list', 'map', 'object',
            'java.util.map', 'java.util.list' => 'json',

            // UUID
            'uuid' => 'uuid',

            // Catch-all: types like list_uuid, list_string, etc.
            default => str_starts_with(strtolower($type), 'list_') ? 'json' : 'string',
        };
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
