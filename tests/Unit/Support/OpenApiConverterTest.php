<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Unit\Support;

use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\Support\OpenApiConverter;
use nameless\CodeGenerator\Support\SchemaParser;
use nameless\CodeGenerator\Tests\TestCase;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\Test;

class OpenApiConverterTest extends TestCase
{
    private OpenApiConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new OpenApiConverter;
    }

    /**
     * @param  array<string, mixed>  $schemas
     * @return array<string, mixed>
     */
    private function openApi(array $schemas): array
    {
        return ['openapi' => '3.0.3', 'info' => ['title' => 'Shop', 'version' => '1'], 'paths' => [], 'components' => ['schemas' => $schemas]];
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function entities(array $document): array
    {
        $converted = $this->converter->convert($document);

        $this->assertTrue((new Validator)->validate(
            json_decode((string) json_encode($converted)),
            json_decode((string) file_get_contents(__DIR__.'/../../../resources/schema/api-schema.json'))
        )->isValid(), 'the converted document does not follow the api-schema JSON Schema');
        (new SchemaParser)->parseArray($converted);

        return $converted['entities'];
    }

    /**
     * @return array<int, string>
     */
    private function warningCodes(): array
    {
        return array_column($this->converter->getWarnings(), 'code');
    }

    #[Test]
    public function types_and_formats_become_columns(): void
    {
        $pet = $this->entities($this->openApi(['Pet' => [
            'type' => 'object',
            'required' => ['name', 'price'],
            'properties' => [
                'id' => ['type' => 'integer', 'format' => 'int64'],
                'name' => ['type' => 'string'],
                'tag' => ['type' => 'string'],
                'born_at' => ['type' => 'string', 'format' => 'date-time'],
                'birthday' => ['type' => 'string', 'format' => 'date'],
                'chip' => ['type' => 'string', 'format' => 'uuid'],
                'price' => ['type' => 'number'],
                'weight' => ['type' => 'number', 'format' => 'float'],
                'visits' => ['type' => 'integer', 'format' => 'int64'],
                'legs' => ['type' => 'integer', 'default' => 4],
                'vaccinated' => ['type' => 'boolean'],
                'notes' => ['type' => 'string', 'maxLength' => 2000],
                'photoUrls' => ['type' => 'array', 'items' => ['type' => 'string']],
                'metadata' => ['type' => 'object', 'properties' => ['color' => ['type' => 'string']]],
                'created_at' => ['type' => 'string', 'format' => 'date-time'],
                'updatedAt' => ['type' => 'string', 'format' => 'date-time'],
            ],
        ]]))['Pet'];

        $this->assertSame([
            'name' => ['type' => 'string'],
            'tag' => ['type' => 'string', 'nullable' => true],
            'born_at' => ['type' => 'datetime', 'nullable' => true],
            'birthday' => ['type' => 'date', 'nullable' => true],
            'chip' => ['type' => 'uuid', 'nullable' => true],
            'price' => ['type' => 'decimal'],
            'weight' => ['type' => 'float', 'nullable' => true],
            'visits' => ['type' => 'bigint', 'nullable' => true],
            'legs' => ['type' => 'integer', 'nullable' => true, 'default' => 4],
            'vaccinated' => ['type' => 'boolean', 'nullable' => true],
            'notes' => ['type' => 'text', 'nullable' => true],
            'photoUrls' => ['type' => 'json', 'nullable' => true],
            'metadata' => ['type' => 'json', 'nullable' => true],
        ], $pet['fields']);
    }

    #[Test]
    public function string_enums_become_enum_fields_and_a_deleted_at_turns_on_soft_deletes(): void
    {
        $order = $this->entities($this->openApi([
            'Order' => ['type' => 'object', 'required' => ['status'], 'properties' => [
                'status' => ['type' => 'string', 'enum' => ['placed', 'approved', 'delivered']],
                'channel' => ['$ref' => '#/components/schemas/Channel'],
                'grade' => ['type' => 'string', 'enum' => ['1st', '2nd']],
                'deleted_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
            ]],
            'Channel' => ['type' => 'string', 'enum' => ['web', 'phone']],
        ]))['Order'];

        $this->assertSame(['type' => 'string', 'enum' => ['placed', 'approved', 'delivered']], $order['fields']['status']);
        $this->assertSame(['type' => 'string', 'nullable' => true, 'enum' => ['web', 'phone']], $order['fields']['channel']);
        $this->assertSame(['type' => 'string', 'nullable' => true], $order['fields']['grade']);
        $this->assertTrue($order['soft_deletes']);
        $this->assertArrayNotHasKey('deleted_at', $order['fields']);
    }

    #[Test]
    public function references_become_relations(): void
    {
        $entities = $this->entities($this->openApi([
            'User' => ['type' => 'object', 'properties' => [
                'name' => ['type' => 'string'],
                'posts' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Post']],
            ]],
            'Post' => ['type' => 'object', 'properties' => [
                'title' => ['type' => 'string'],
                'author' => ['$ref' => '#/components/schemas/User'],
                'author_id' => ['type' => 'integer'],
                'tags' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Tag']],
            ]],
            'Tag' => ['type' => 'object', 'properties' => [
                'label' => ['type' => 'string'],
                'posts' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Post']],
            ]],
            'Comment' => ['type' => 'object', 'properties' => [
                'body' => ['type' => 'string'],
                'post_id' => ['type' => 'integer'],
                'userId' => ['type' => 'integer'],
            ]],
        ]));

        $this->assertSame(['posts' => 'hasMany Post'], $entities['User']['relations']);
        $this->assertSame(['author' => 'belongsTo User', 'tags' => 'belongsToMany Tag'], $entities['Post']['relations']);
        $this->assertArrayNotHasKey('author_id', $entities['Post']['fields']);
        $this->assertArrayNotHasKey('relations', $entities['Tag']);
        $this->assertSame(['post' => 'belongsTo Post', 'user' => 'belongsTo User'], $entities['Comment']['relations']);
        $this->assertSame(['body'], array_keys($entities['Comment']['fields']));
    }

    #[Test]
    public function compositions_and_nullable_types_are_followed(): void
    {
        $entities = $this->entities(['openapi' => '3.1.0', 'components' => ['schemas' => [
            'Animal' => ['type' => 'object', 'required' => ['name'], 'properties' => ['name' => ['type' => 'string']]],
            'Dog' => ['allOf' => [
                ['$ref' => '#/components/schemas/Animal'],
                ['type' => 'object', 'required' => ['breed'], 'properties' => [
                    'breed' => ['type' => ['string', 'null']],
                    'owner' => ['allOf' => [['$ref' => '#/components/schemas/Person']], 'nullable' => true],
                ]],
            ]],
            'Person' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
        ]]]);

        $this->assertSame(['name' => ['type' => 'string'], 'breed' => ['type' => 'string', 'nullable' => true]], $entities['Dog']['fields']);
        $this->assertSame(['owner' => 'belongsTo Person'], $entities['Dog']['relations']);
    }

    #[Test]
    public function swagger_2_definitions_are_read(): void
    {
        $entities = $this->entities(['swagger' => '2.0', 'definitions' => [
            'pet_owner' => ['type' => 'object', 'required' => ['email'], 'properties' => [
                'email' => ['type' => 'string', 'x-nullable' => true],
                'first-name' => ['type' => 'string'],
            ]],
        ]]);

        $this->assertSame(['email' => ['type' => 'string', 'nullable' => true], 'first_name' => ['type' => 'string', 'nullable' => true]], $entities['PetOwner']['fields']);
    }

    #[Test]
    public function payload_and_error_schemas_are_skipped_with_a_warning(): void
    {
        $entities = $this->entities($this->openApi([
            'Pet' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
            'NewPet' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
            'CreatePetRequest' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
            'PetCollection' => ['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Pet']]]],
            'Error' => ['type' => 'object', 'properties' => ['code' => ['type' => 'integer'], 'message' => ['type' => 'string']]],
            'Empty' => ['type' => 'object'],
            'Status' => ['type' => 'string', 'enum' => ['on', 'off']],
        ]));

        $this->assertSame(['Pet'], array_keys($entities));
        $this->assertSame(array_fill(0, 5, 'openapi_schema_skipped'), $this->warningCodes());
        $this->assertStringContainsString('NewPet', $this->converter->getWarnings()[0]['message']);
    }

    #[Test]
    public function json_and_yaml_texts_are_accepted(): void
    {
        $json = (string) json_encode($this->openApi(['Pet' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]]]));
        $yaml = "openapi: 3.0.0\ncomponents:\n  schemas:\n    Pet:\n      type: object\n      properties:\n        name:\n          type: string\n";

        $this->assertSame(['Pet'], array_keys($this->converter->convertString($json, 'spec.json')['entities']));
        $this->assertSame(['Pet'], array_keys($this->converter->convertString($yaml, 'spec.yaml')['entities']));
    }

    #[Test]
    public function a_document_without_resources_is_refused(): void
    {
        foreach ([['openapi' => '3.0.0', 'paths' => []], $this->openApi(['Error' => ['type' => 'object', 'properties' => ['code' => ['type' => 'integer']]]])] as $document) {
            try {
                $this->converter->convert($document, 'spec.yaml');
                $this->fail('A document without resources was accepted.');
            } catch (CodeGeneratorException $e) {
                $this->assertSame('invalid_schema', $e->errorCode);
                $this->assertStringContainsString('spec.yaml', $e->getMessage());
            }
        }
    }
}
