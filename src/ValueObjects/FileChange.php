<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\ValueObjects;

final readonly class FileChange
{
    public const CREATE = 'create';

    public const UPDATE = 'update';

    public const UNCHANGED = 'unchanged';

    public function __construct(
        public string $path,
        public string $kind,
        public ?string $entity,
        public string $action,
        public string $content,
    ) {}

    public function writesToDisk(): bool
    {
        return $this->action !== self::UNCHANGED;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(bool $withContent): array
    {
        $data = ['path' => $this->path, 'kind' => $this->kind];

        if ($this->entity !== null) {
            $data['entity'] = $this->entity;
        }

        $data['action'] = $this->action;

        if ($withContent) {
            $data['content'] = $this->content;
        }

        return $data;
    }
}
