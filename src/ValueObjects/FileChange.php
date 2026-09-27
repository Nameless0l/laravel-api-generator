<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\ValueObjects;

final readonly class FileChange
{
    public const CREATE = 'create';

    public const UPDATE = 'update';

    public const UNCHANGED = 'unchanged';

    /**
     * @param  bool  $kept  the plan would update the file, but it was edited since it was generated, so it stays as is
     */
    public function __construct(
        public string $path,
        public string $kind,
        public ?string $entity,
        public string $action,
        public string $content,
        public bool $kept = false,
    ) {}

    public function asKept(): self
    {
        return new self($this->path, $this->kind, $this->entity, $this->action, $this->content, true);
    }

    public function writesToDisk(): bool
    {
        return $this->action !== self::UNCHANGED && ! $this->kept;
    }

    /**
     * @return array<string, string|bool>
     */
    public function toArray(bool $withContent): array
    {
        $data = ['path' => $this->path, 'kind' => $this->kind];

        if ($this->entity !== null) {
            $data['entity'] = $this->entity;
        }

        $data['action'] = $this->action;

        if ($this->kept) {
            $data['kept'] = true;
        }

        if ($withContent) {
            $data['content'] = $this->content;
        }

        return $data;
    }
}
