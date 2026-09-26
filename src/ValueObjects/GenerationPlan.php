<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\ValueObjects;

use nameless\CodeGenerator\Support\Workspace;

final class GenerationPlan
{
    /**
     * @param  array<int, array{code: string, message: string}>  $warnings
     */
    public function __construct(
        private readonly Workspace $workspace,
        public readonly array $warnings,
    ) {}

    /**
     * @return array<int, FileChange>
     */
    public function changes(): array
    {
        return $this->workspace->changes();
    }

    public function apply(): void
    {
        $this->workspace->commit();
    }
}
