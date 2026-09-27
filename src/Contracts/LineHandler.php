<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Contracts;

interface LineHandler
{
    public function handle(string $line): ?string;

    public function stopped(): bool;
}
