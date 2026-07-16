<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Support;

use InvalidArgumentException;

class FieldParser
{
    /**
     * Parse fields string into array.
     *
     * @param  string  $fields  Format: "field1:type1,field2:type2"
     * @return array<string, string>
     */
    public static function parseFieldsString(string $fields): array
    {
        if (empty(trim($fields))) {
            throw new InvalidArgumentException('Fields string cannot be empty');
        }

        $fieldsArray = [];

        // Split on commas outside parentheses so enum(a,b) survives intact
        $fieldTokens = preg_split('/,(?![^()]*\))/', $fields) ?: [];

        foreach ($fieldTokens as $field) {
            $parts = explode(':', trim($field));

            if (count($parts) < 2) {
                throw new InvalidArgumentException("Invalid field format: {$field}. Expected format: field:type");
            }

            $fieldName = trim($parts[0]);
            // Keep any modifier segments (e.g. code:string:primary) in the type spec
            $fieldType = trim(strtolower(implode(':', array_slice($parts, 1))));

            if (empty($fieldName) || empty($fieldType)) {
                throw new InvalidArgumentException("Field name and type cannot be empty: {$field}");
            }

            $fieldsArray[$fieldName] = $fieldType;
        }

        return $fieldsArray;
    }

    /**
     * Validate field type.
     */
    public static function isValidType(string $type): bool
    {
        $allowedTypes = [
            'string', 'integer', 'int', 'boolean', 'bool', 'text', 'float',
            'decimal', 'json', 'date', 'datetime', 'timestamp', 'time',
            'uuid', 'UUID', 'bigint',
        ];

        return in_array($type, $allowedTypes, true) || self::parseEnumType($type) !== null;
    }

    /**
     * @return array<int, string>|null
     */
    public static function parseEnumType(string $type): ?array
    {
        if (! preg_match('/^enum\((.+)\)$/i', trim($type), $matches)) {
            return null;
        }

        $values = array_filter(array_map('trim', explode(',', $matches[1])));

        return $values === [] ? null : array_values($values);
    }
}
