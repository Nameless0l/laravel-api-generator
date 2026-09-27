<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Unit\Support;

use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\Support\SchemaParser;
use nameless\CodeGenerator\Tests\TestCase;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use PHPUnit\Framework\Attributes\Test;

class SchemaParserTest extends TestCase
{
    private SchemaParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SchemaParser;
    }

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
    public function it_reads_the_json_api_option_in_both_spellings(): void
    {
        $entities = $this->parser->parseArray([
            'entities' => [
                'Post' => ['json_api' => true, 'fields' => ['title' => 'string']],
                'Tag' => ['jsonApi' => true, 'fields' => ['name' => 'string']],
                'Page' => ['fields' => ['title' => 'string']],
            ],
        ]);

        /** @var EntityDefinition $post */
        $post = $entities->firstWhere('name', 'Post');
        /** @var EntityDefinition $tag */
        $tag = $entities->firstWhere('name', 'Tag');
        /** @var EntityDefinition $page */
        $page = $entities->firstWhere('name', 'Page');

        $this->assertTrue($post->usesJsonApi());
        $this->assertTrue($tag->usesJsonApi());
        $this->assertFalse($page->usesJsonApi());
    }

    #[Test]
    public function it_rejects_schema_without_entities(): void
    {
        $this->expectException(CodeGeneratorException::class);
        $this->parser->parseArray(['options' => []]);
    }

    #[Test]
    public function it_rejects_unknown_field_modifier(): void
    {
        $this->expectException(CodeGeneratorException::class);
        $this->parser->parseArray([
            'entities' => [
                'Post' => ['fields' => ['title' => 'string wat']],
            ],
        ]);
    }

    #[Test]
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

    #[Test]
    public function it_parses_a_json_string(): void
    {
        $entities = $this->parser->parseString('{"entities":{"Book":{"fields":{"title":"string"}}}}');

        $this->assertSame('Book', $entities->first()?->name);
    }

    #[Test]
    public function it_parses_a_yaml_string(): void
    {
        $entities = $this->parser->parseString("entities:\n  Book:\n    fields:\n      title: string\n");

        $this->assertSame('Book', $entities->first()?->name);
    }

    #[Test]
    public function it_rejects_an_empty_string(): void
    {
        try {
            $this->parser->parseString("  \n");
            $this->fail('An empty schema was accepted.');
        } catch (CodeGeneratorException $e) {
            $this->assertSame('invalid_schema', $e->errorCode);
        }
    }

    #[Test]
    public function it_parses_fields_to_add_to_an_existing_entity(): void
    {
        $fields = $this->parser->parseFields('Post', ['excerpt' => 'text nullable', 'status' => 'enum(draft,published)']);

        $this->assertSame(['excerpt', 'status'], $fields->map(fn (FieldDefinition $field) => $field->name)->all());
        $this->assertTrue($fields[0]->nullable);
        $this->assertSame(['draft', 'published'], $fields[1]->attributes['enum'] ?? null);
    }

    #[Test]
    public function an_unknown_type_becomes_a_string_with_a_warning(): void
    {
        $entities = $this->parser->parseArray(['entities' => ['Post' => ['fields' => [
            'title' => 'strng',
            'body' => ['type' => 'txet'],
            'views' => 'integer',
            'tags' => 'list_string',
        ]]]]);

        $this->assertSame('string', $entities[0]->fields[0]->type);
        $this->assertSame(['unknown_field_type', 'unknown_field_type'], array_column($this->parser->getWarnings(), 'code'));
        $this->assertStringContainsString("Post.title: unknown type 'strng'", $this->parser->getWarnings()[0]['message']);

        $this->parser->parseFields('Post', ['excerpt' => 'text']);
        $this->assertSame([], $this->parser->getWarnings());
    }

    #[Test]
    public function fields_to_add_must_be_a_mapping(): void
    {
        $this->expectException(CodeGeneratorException::class);

        $this->parser->parseFields('Post', ['excerpt', 'status']);
    }
}
