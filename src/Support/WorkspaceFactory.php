<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Support;

use Closure;

final class WorkspaceFactory
{
    /**
     * @param  (Closure(): int)|null  $clock
     */
    public function __construct(
        private readonly ?Closure $clock = null,
    ) {}

    public function make(): Workspace
    {
        return new Workspace(base_path(), $this->clock ?? static fn (): int => time());
    }
}
