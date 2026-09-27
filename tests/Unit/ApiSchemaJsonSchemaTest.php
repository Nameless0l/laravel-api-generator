<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Unit;

use nameless\CodeGenerator\Support\SchemaParser;
use nameless\CodeGenerator\Tests\TestCase;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;

class ApiSchemaJsonSchemaTest extends TestCase
{
    private const SCHEMA = __DIR__.'/../../resources/schema/api-schema.json';

    private function errors(mixed $document): ?string
    {
        $result = (new Validator)->validate(
            json_decode((string) json_encode($document)),
            json_decode((string) file_get_contents(self::SCHEMA))
        );
        $error = $result->error();

        return $error === null ? null : (string) json_encode((new ErrorFormatter)->format($error));
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function entity(array $fields, array $extra = []): array
    {
        return ['entities' => ['Post' => ['fields' => $fields] + $extra]];
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function validDocuments(): array
    {
        return [
            'shorthand modifiers' => [self::entity(['title' => 'string', 'slug' => 'string unique', 'body' => 'text nullable', 'views' => 'integer default=0', 'code' => 'string primary'])],
            'enum shorthand' => [self::entity(['status' => 'enum(draft,published) default=draft'])],
            'mapping form' => [self::entity(['price' => ['type' => 'decimal', 'nullable' => true, 'default' => 9.5, 'rules' => ['min:0']], 'status' => ['enum' => ['draft', 'paid']]])],
            'type aliases' => [self::entity(['a' => 'int', 'b' => 'bool', 'c' => 'varchar', 'd' => 'UUID'])],
            'relations' => [self::entity(['title' => 'string'], ['relations' => [
                'category' => 'belongsTo Category',
                'tags' => 'belongsToMany:Tag',
                'author' => ['type' => 'belongsTo', 'model' => 'User', 'foreignKey' => 'author_id'],
                'commentable' => 'morphTo',
                'comments' => 'morphMany Comment commentable',
            ]])],
            'options everywhere' => [['options' => ['query_builder' => true, 'pest' => true], 'entities' => ['Post' => ['softDeletes' => true, 'json_api' => false, 'fields' => ['title' => 'string']]]]],
            'relationships alias and empty sections' => [['options' => null, 'entities' => ['Post' => ['fields' => ['title' => 'string'], 'relationships' => null]]]],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function mistakes(): array
    {
        return [
            'unknown modifier' => [self::entity(['title' => 'string nulable'])],
            'unknown type' => [self::entity(['title' => 'strng'])],
            'enum values with spaces' => [self::entity(['status' => 'enum(draft, paid)'])],
            'unknown relation type' => [self::entity(['title' => 'string'], ['relations' => ['post' => 'belongsTwo Post']])],
            'relation without a model' => [self::entity(['title' => 'string'], ['relations' => ['post' => 'belongsTo']])],
            'misspelled option' => [self::entity(['title' => 'string'], ['soft_delete' => true])],
            'entity without fields' => [['entities' => ['Post' => ['fields' => []]]]],
            'no entities' => [['entities' => []]],
            'field name with a dash' => [self::entity(['first-name' => 'string'])],
        ];
    }

    /**
     * @param  array<string, mixed>  $document
     */
    #[Test]
    #[DataProvider('validDocuments')]
    public function documented_forms_are_valid_and_parse(array $document): void
    {
        $this->assertNull($this->errors($document));
        $this->assertCount(1, (new SchemaParser)->parseArray($document));
    }

    /**
     * @param  array<string, mixed>  $document
     */
    #[Test]
    #[DataProvider('mistakes')]
    public function common_mistakes_are_flagged(array $document): void
    {
        $this->assertNotNull($this->errors($document));
    }

    #[Test]
    public function the_example_schema_is_valid(): void
    {
        $this->assertNull($this->errors(Yaml::parseFile(dirname(__DIR__, 2).'/examples/api-schema.yaml')));
    }

    #[Test]
    public function every_field_type_and_relation_keyword_of_the_code_is_accepted(): void
    {
        foreach (FieldDefinition::CANONICAL_TYPES as $type) {
            $this->assertNull($this->errors(self::entity(['a' => $type, 'b' => ['type' => $type]])), $type);
        }

        foreach (SchemaParser::RELATION_KEYWORDS as $keyword) {
            $relation = $keyword === 'morphTo' ? 'morphTo' : "{$keyword} Tag";
            $this->assertNull($this->errors(self::entity(['a' => 'string'], ['relations' => ['r' => $relation]])), $keyword);
        }
    }

    #[Test]
    public function the_docs_site_serves_the_same_schema(): void
    {
        $published = dirname(__DIR__, 2).'/docs/public/schema/api-schema.json';

        $this->assertFileExists($published);
        $this->assertSame(
            str_replace("\r\n", "\n", (string) file_get_contents(self::SCHEMA)),
            str_replace("\r\n", "\n", (string) file_get_contents($published))
        );
    }
}
