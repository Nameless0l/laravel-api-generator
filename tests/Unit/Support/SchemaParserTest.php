<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Unit\Support;

use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\Support\SchemaParser;
use nameless\CodeGenerator\Tests\TestCase;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;

class SchemaParserTest extends TestCase
{
    private SchemaParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SchemaParser;
    }

    /** @test */
    public function it_parses_shorthand_field_definitions(): void
    {
        $entities = $this->parser->parseArray([
            'entities' => [
                'Post' => [
                    'fields' => [
                        'title' => 'string',
                        'slug' => 'string unique',
                        'content' => 'text nullable',
                        'views' => 'integer default=0',
                    ],
                ],
            ],
        ]);

        $this->assertCount(1, $entities);
        /** @var EntityDefinition $post */
        $post = $entities->first();
        $this->assertSame('Post', $post->name);

        $fields = $post->fields->keyBy(fn (FieldDefinition $f) => $f->name);
        $this->assertSame('string', $fields['title']->type);
        $this->assertTrue($fields['slug']->unique);
        $this->assertTrue($fields['content']->nullable);
        $this->assertFalse($fields['title']->nullable);
        $this->assertSame('0', $fields['views']->default);
    }

    /** @test */
    public function it_parses_mapping_field_definitions(): void
    {
        $entities = $this->parser->parseArray([
            'entities' => [
                'Product' => [
                    'fields' => [
                        'price' => ['type' => 'decimal', 'nullable' => true],
                        'sku' => ['type' => 'string', 'unique' => true, 'rules' => ['min:3']],
                    ],
                ],
            ],
        ]);

        /** @var EntityDefinition $product */
        $product = $entities->first();
        $fields = $product->fields->keyBy(fn (FieldDefinition $f) => $f->name);

        $this->assertSame('decimal', $fields['price']->type);
        $this->assertTrue($fields['price']->nullable);
        $this->assertTrue($fields['sku']->unique);
        $this->assertSame(['min:3'], $fields['sku']->validationRules);
    }

    /** @test */
    public function it_parses_relations_and_sorts_parents_first(): void
    {
        $entities = $this->parser->parseArray([
            'entities' => [
                // Post declared before Category on purpose
                'Post' => [
                    'fields' => ['title' => 'string'],
                    'relations' => [
                        'category' => 'belongsTo Category',
                        'tags' => 'belongsToMany Tag',
                    ],
                ],
                'Category' => [
                    'fields' => ['name' => 'string'],
                ],
                'Tag' => [
                    'fields' => ['name' => 'string'],
                ],
            ],
        ]);

        $names = $entities->map(fn (EntityDefinition $e) => $e->name)->all();
        $this->assertLessThan(
            array_search('Post', $names, true),
            array_search('Category', $names, true),
            'Category (parent) must be generated before Post (child)'
        );

        /** @var EntityDefinition $post */
        $post = $entities->firstWhere('name', 'Post');
        $belongsTo = $post->getRelationshipsByType('manyToOne')->first();
        $this->assertNotNull($belongsTo);
        $this->assertSame('Category', $belongsTo->relatedModel);
        $this->assertSame('category', $belongsTo->role);

        $manyToMany = $post->getRelationshipsByType('manyToMany')->first();
        $this->assertNotNull($manyToMany);
        $this->assertSame('Tag', $manyToMany->relatedModel);
    }

    /** @test */
    public function it_applies_global_and_entity_options(): void
    {
        $entities = $this->parser->parseArray([
            'options' => ['query_builder' => true],
            'entities' => [
                'Post' => [
                    'soft_deletes' => true,
                    'fields' => ['title' => 'string'],
                ],
                'Tag' => [
                    'fields' => ['name' => 'string'],
                ],
            ],
        ]);

        /** @var EntityDefinition $post */
        $post = $entities->firstWhere('name', 'Post');
        /** @var EntityDefinition $tag */
        $tag = $entities->firstWhere('name', 'Tag');

        $this->assertTrue($post->usesQueryBuilder());
        $this->assertTrue($post->hasSoftDeletes());
        $this->assertTrue($tag->usesQueryBuilder());
        $this->assertFalse($tag->hasSoftDeletes());
    }

    /** @test */
    public function it_rejects_schema_without_entities(): void
    {
        $this->expectException(CodeGeneratorException::class);
        $this->parser->parseArray(['options' => []]);
    }

    /** @test */
    public function it_rejects_unknown_field_modifier(): void
    {
        $this->expectException(CodeGeneratorException::class);
        $this->parser->parseArray([
            'entities' => [
                'Post' => ['fields' => ['title' => 'string wat']],
            ],
        ]);
    }

    /** @test */
    public function it_rejects_unknown_relation_type(): void
    {
        $this->expectException(CodeGeneratorException::class);
        $this->parser->parseArray([
            'entities' => [
                'Post' => [
                    'fields' => ['title' => 'string'],
                    'relations' => ['category' => 'linkedTo Category'],
                ],
            ],
        ]);
    }
}
