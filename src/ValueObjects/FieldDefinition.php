<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\ValueObjects;

use InvalidArgumentException;

final readonly class FieldDefinition
{
    /**
     * @param  array<int, string>  $validationRules
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $name,
        public string $type,
        public bool $nullable = false,
        public bool $unique = false,
        public ?string $default = null,
        public array $validationRules = [],
        public array $attributes = []
    ) {
        $this->validateName($name);
        $this->validateType($type);
    }

    private function validateName(string $name): void
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Field name cannot be empty');
        }

        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("Invalid field name: {$name}");
        }
    }

    private function validateType(string $type): void
    {
        $allowedTypes = [
            'string', 'integer', 'int', 'boolean', 'bool', 'text', 'float',
            'decimal', 'json', 'date', 'datetime', 'timestamp', 'time',
            'uuid', 'UUID', 'bigint',
        ];

        if (! in_array($type, $allowedTypes, true)) {
            throw new InvalidArgumentException("Unsupported field type: {$type}");
        }
    }

    public function getDatabaseType(): string
    {
        return match ($this->type) {
            'string' => 'string',
            'integer', 'int' => 'integer',
            'boolean', 'bool' => 'boolean',
            'text' => 'text',
            'float' => 'decimal',
            'decimal' => 'decimal',
            'json' => 'json',
            'date', 'datetime', 'timestamp', 'time' => 'timestamp',
            'uuid', 'UUID' => 'uuid',
            'bigint' => 'bigInteger',
            default => 'string'
        };
    }

    public function getPhpType(): string
    {
        return match ($this->type) {
            'string', 'text', 'uuid', 'UUID' => 'string',
            'integer', 'int', 'bigint' => 'int',
            'boolean', 'bool' => 'bool',
            'float', 'decimal' => 'float',
            'json' => 'array',
            'date', 'datetime', 'timestamp', 'time' => 'string',
            default => 'string'
        };
    }

    public function getValidationRule(): string
    {
        if (! empty($this->validationRules)) {
            return implode('|', $this->validationRules);
        }

        $rule = match ($this->type) {
            'string' => 'string|max:255',
            'integer', 'int', 'bigint' => 'integer',
            'boolean', 'bool' => 'boolean',
            'text' => 'string',
            'uuid', 'UUID' => 'uuid',
            'float', 'decimal' => 'numeric',
            'json' => 'json',
            'date', 'datetime', 'timestamp' => 'date',
            default => 'string'
        };

        $prefix = $this->nullable ? 'sometimes' : 'required';

        // Note: uniqueness is NOT appended here — a bare "unique" rule is
        // invalid in Laravel (it needs the table). RequestGenerator emits a
        // proper Rule::unique() using the entity's table name.
        return "{$prefix}|{$rule}";
    }

    public function getFakeValue(): string
    {
        $fake = match ($this->type) {
            'string' => $this->name === 'slug' ? 'fake()->slug()' : 'fake()->word()',
            'integer', 'int', 'bigint' => 'fake()->randomNumber()',
            'boolean', 'bool' => 'fake()->boolean()',
            'text' => 'fake()->sentence()',
            'uuid', 'UUID' => 'fake()->uuid()',
            'float', 'decimal' => 'fake()->randomFloat(2, 1, 1000)',
            'json' => "json_encode(['key' => 'value'])",
            'date', 'datetime', 'timestamp', 'time' => "fake()->dateTime()->format('Y-m-d H:i:s')",
            default => 'fake()->word()'
        };

        // Unique columns need unique fakes, otherwise factories collide as
        // soon as a test creates a few rows (e.g. posts.slug).
        if ($this->unique && str_starts_with($fake, 'fake()->')) {
            return 'fake()->unique()->'.substr($fake, strlen('fake()->'));
        }

        return $fake;
    }
}
