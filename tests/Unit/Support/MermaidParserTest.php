<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Unit\Support;

use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\Support\MermaidParser;
use nameless\CodeGenerator\Tests\TestCase;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use PHPUnit\Framework\Attributes\Test;

class MermaidParserTest extends TestCase
{
    private MermaidParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new MermaidParser;
    }

    #[Test]
    public function it_parses_an_er_diagram(): void
    {
        $diagram = <<<'MERMAID'
        erDiagram
            USER ||--o{ POST : writes
            POST }o--o{ TAG : tagged

            USER {
                string name
                string email UK
            }
            POST {
                string title
                text content
                datetime deleted_at
            }
            TAG {
                string name UK
            }
        MERMAID;

        $entities = $this->parser->parse($diagram);

        $this->assertCount(3, $entities);

        /** @var EntityDefinition $user */
        $user = $entities->firstWhere('name', 'User');
        /** @var EntityDefinition $post */
        $post = $entities->firstWhere('name', 'Post');
        /** @var EntityDefinition $tag */
        $tag = $entities->firstWhere('name', 'Tag');

        // USER ||--o{ POST → User hasMany Post, Post belongsTo User
        $userHasMany = $user->getRelationshipsByType('oneToMany')->first();
        $this->assertNotNull($userHasMany);
        $this->assertSame('Post', $userHasMany->relatedModel);

        $postBelongsTo = $post->getRelationshipsByType('manyToOne')->first();
        $this->assertNotNull($postBelongsTo);
        $this->assertSame('User', $postBelongsTo->relatedModel);

        // POST }o--o{ TAG → belongsToMany on both sides
        $postManyToMany = $post->getRelationshipsByType('manyToMany')->first();
        $this->assertNotNull($postManyToMany);
        $this->assertSame('Tag', $postManyToMany->relatedModel);

        $tagManyToMany = $tag->getRelationshipsByType('manyToMany')->first();
        $this->assertNotNull($tagManyToMany);
        $this->assertSame('Post', $tagManyToMany->relatedModel);

        // deleted_at column enables soft deletes and is not a field
        $this->assertTrue($post->hasSoftDeletes());
        $this->assertFalse($post->fields->contains(fn (FieldDefinition $f) => $f->name === 'deleted_at'));

        // UK marker becomes unique
        $email = $user->fields->firstWhere('name', 'email');
        $this->assertNotNull($email);
        $this->assertTrue($email->unique);
    }

    #[Test]
    public function it_ignores_pk_and_fk_columns_in_er_blocks(): void
    {
        $diagram = <<<'MERMAID'
        erDiagram
            USER ||--o{ POST : writes
            USER {
                string name
            }
            POST {
                int id PK
                int user_id FK
                string title
            }
        MERMAID;

        $entities = $this->parser->parse($diagram);

        /** @var EntityDefinition $post */
        $post = $entities->firstWhere('name', 'Post');

        $fieldNames = $post->fields->map(fn (FieldDefinition $f) => $f->name)->all();
        $this->assertSame(['title'], $fieldNames);

        // The FK still exists, but through the relationship
        $this->assertContains('user_id', $post->getFillableFields());
    }

    #[Test]
    public function it_parses_a_class_diagram_with_cardinalities(): void
    {
        $diagram = <<<'MERMAID'
        classDiagram
            class User {
                +String name
                +String email
            }
            class Post {
                +String title
                +text content
            }
            User "1" --> "*" Post : posts
        MERMAID;

        $entities = $this->parser->parse($diagram);

        /** @var EntityDefinition $user */
        $user = $entities->firstWhere('name', 'User');
        /** @var EntityDefinition $post */
        $post = $entities->firstWhere('name', 'Post');

        $hasMany = $user->getRelationshipsByType('oneToMany')->first();
        $this->assertNotNull($hasMany);
        $this->assertSame('Post', $hasMany->relatedModel);
        $this->assertSame('posts', $hasMany->role);

        $belongsTo = $post->getRelationshipsByType('manyToOne')->first();
        $this->assertNotNull($belongsTo);
        $this->assertSame('User', $belongsTo->relatedModel);

        // Parents are sorted before children for FK-safe migrations
        $names = $entities->map(fn (EntityDefinition $e) => $e->name)->all();
        $this->assertLessThan(
            array_search('Post', $names, true),
            array_search('User', $names, true)
        );
    }

    #[Test]
    public function it_treats_composition_as_one_to_many(): void
    {
        $diagram = <<<'MERMAID'
        classDiagram
            class Order {
                +String reference
            }
            class OrderLine {
                +int quantity
            }
            Order *-- OrderLine : lines
        MERMAID;

        $entities = $this->parser->parse($diagram);

        /** @var EntityDefinition $order */
        $order = $entities->firstWhere('name', 'Order');
        $rel = $order->getRelationshipsByType('oneToMany')->first();
        $this->assertNotNull($rel);
        $this->assertSame('OrderLine', $rel->relatedModel);
        $this->assertSame('lines', $rel->role);
    }

    #[Test]
    public function it_skips_methods_and_entities_without_attributes(): void
    {
        $diagram = <<<'MERMAID'
        classDiagram
            class User {
                +String name
                +login() bool
            }
            User "1" --> "*" Ghost : ghosts
        MERMAID;

        $entities = $this->parser->parse($diagram);

        $this->assertCount(1, $entities);
        /** @var EntityDefinition $user */
        $user = $entities->first();
        $this->assertSame('User', $user->name);
        $this->assertCount(1, $user->fields);
        // Relation to the never-declared Ghost entity is dropped with a warning
        $this->assertCount(0, $user->relationships);
        $this->assertNotEmpty($this->parser->getWarnings());
    }

    #[Test]
    public function it_rejects_non_mermaid_content(): void
    {
        $this->expectException(CodeGeneratorException::class);
        $this->parser->parse('flowchart TD');
    }

    #[Test]
    public function it_strips_markdown_fences_and_comments(): void
    {
        $diagram = <<<'MERMAID'
        ```mermaid
        erDiagram
            %% this is a comment
            USER {
                string name
            }
        ```
        MERMAID;

        $entities = $this->parser->parse($diagram);
        $this->assertCount(1, $entities);
        $first = $entities->first();
        $this->assertNotNull($first);
        $this->assertSame('User', $first->name);
    }
}
