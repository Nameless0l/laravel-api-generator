<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use nameless\CodeGenerator\Services\GenerationPlanner;
use nameless\CodeGenerator\Support\SchemaParser;
use nameless\CodeGenerator\Tests\TestCase;
use nameless\CodeGenerator\ValueObjects\GenerationRequest;
use PHPUnit\Framework\Attributes\Test;

class RelationForeignKeyTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $entities
     */
    private function model(array $entities, string $name): string
    {
        $plan = app(GenerationPlanner::class)->plan(new GenerationRequest(
            (new SchemaParser)->parseArray(['entities' => $entities]),
            only: ['Model'],
        ));

        foreach ($plan->changes() as $change) {
            if ($change->path === "app/Models/{$name}.php") {
                return $change->content;
            }
        }

        $this->fail("No model planned for {$name}");
    }

    #[Test]
    public function a_has_many_follows_a_belongs_to_named_after_its_role(): void
    {
        $entities = [
            'Writer' => ['fields' => ['name' => 'string']],
            'Story' => ['fields' => ['title' => 'string'], 'relations' => ['author' => 'belongsTo Writer']],
        ];

        $this->assertStringContainsString("hasMany(Story::class, 'author_id')", $this->model($entities, 'Writer'));
        $this->assertStringContainsString('belongsTo(Writer::class)', $this->model($entities, 'Story'));
    }

    #[Test]
    public function a_declared_has_many_follows_the_belongs_to_declared_on_the_other_side(): void
    {
        $entities = [
            'Writer' => ['fields' => ['name' => 'string'], 'relations' => ['stories' => 'hasMany Story']],
            'Story' => ['fields' => ['title' => 'string'], 'relations' => ['author' => 'belongsTo Writer']],
        ];

        $this->assertStringContainsString("hasMany(Story::class, 'author_id')", $this->model($entities, 'Writer'));
    }

    #[Test]
    public function the_foreign_key_follows_a_custom_primary_key(): void
    {
        $entities = [
            'Shelf' => ['fields' => ['code' => 'string primary']],
            'Story' => ['fields' => ['title' => 'string'], 'relations' => ['location' => 'belongsTo Shelf']],
        ];

        $this->assertStringContainsString("hasMany(Story::class, 'location_code')", $this->model($entities, 'Shelf'));
    }

    #[Test]
    public function the_default_key_stays_implicit(): void
    {
        $entities = [
            'Shelf' => ['fields' => ['code' => 'string primary']],
            'Writer' => ['fields' => ['name' => 'string']],
            'Story' => ['fields' => ['title' => 'string'], 'relations' => ['writer' => 'belongsTo Writer', 'shelf' => 'belongsTo Shelf']],
        ];

        $this->assertStringContainsString('hasMany(Story::class);', $this->model($entities, 'Writer'));
        $this->assertStringContainsString('hasMany(Story::class);', $this->model($entities, 'Shelf'));
    }
}
