<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Support;

final class LaravelVersion
{
    public function __construct(
        private readonly string $version
    ) {}

    /**
     * Laravel 13 reads the table, key and fillable columns from class attributes.
     */
    public function hasModelAttributes(): bool
    {
        return version_compare($this->version, '13.0.0', '>=');
    }
}
