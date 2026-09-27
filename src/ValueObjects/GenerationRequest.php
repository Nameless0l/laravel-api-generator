<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\ValueObjects;

use Illuminate\Support\Collection;

final readonly class GenerationRequest
{
    /**
     * @param  Collection<int, EntityDefinition>  $entities
     * @param  array<int, string>|null  $only
     */
    public function __construct(
        public Collection $entities,
        public bool $auth = false,
        public bool $postman = false,
        public ?array $only = null,
    ) {}
}
