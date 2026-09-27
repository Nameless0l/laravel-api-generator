<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\ValueObjects;

use Illuminate\Support\Facades\File;
use nameless\CodeGenerator\Support\Manifest;
use nameless\CodeGenerator\Support\Workspace;

final class GenerationPlan
{
    /** @var array<int, FileChange>|null */
    private ?array $changes = null;

    /**
     * @param  array<int, array{code: string, message: string}>  $warnings
     * @param  array<int, string>  $kept  paths edited since they were generated, left as they are
     * @param  bool  $patchesInPlace  field additions patch files around manual edits
     */
    public function __construct(
        private readonly Workspace $workspace,
        public readonly array $warnings,
        private readonly ?Manifest $manifest = null,
        private readonly array $kept = [],
        private readonly bool $patchesInPlace = false,
    ) {}

    /**
     * @return array<int, FileChange>
     */
    public function changes(): array
    {
        return $this->changes ??= array_map(
            fn (FileChange $change) => in_array($change->path, $this->kept, true) ? $change->asKept() : $change,
            $this->workspace->changes()
        );
    }

    public function apply(): void
    {
        $changes = $this->changes();
        $pristineBefore = $this->patchesInPlace ? $this->pristineBefore($changes) : [];

        $this->workspace->commit($this->kept);

        if ($this->manifest === null) {
            return;
        }

        foreach ($changes as $change) {
            if (! Manifest::tracks($change->kind) || $change->kept) {
                continue;
            }

            // A patched file edited by hand stays marked as edited
            if ($this->patchesInPlace && $change->action !== FileChange::CREATE && ($pristineBefore[$change->path] ?? null) !== true) {
                continue;
            }

            $this->manifest->record($change);
        }

        $this->manifest->save();
    }

    /**
     * @param  array<int, FileChange>  $changes
     * @return array<string, bool|null>
     */
    private function pristineBefore(array $changes): array
    {
        $pristine = [];

        foreach ($changes as $change) {
            if ($this->manifest !== null && $change->action === FileChange::UPDATE) {
                $pristine[$change->path] = $this->manifest->isPristine($change->path, File::get(base_path($change->path)));
            }
        }

        return $pristine;
    }
}
