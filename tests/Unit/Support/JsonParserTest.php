<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Unit\Support;

use nameless\CodeGenerator\Support\JsonParser;
use PHPUnit\Framework\TestCase;

class JsonParserTest extends TestCase
{
    public function test_parses_enum_field_types_into_enum_attributes(): void
    {
        $json = json_encode([
            [
                'name' => 'Article',
                'attributes' => [
                    ['name' => 'title', '_type' => 'string'],
                    ['name' => 'status', '_type' => 'enum(draft,published)'],
                ],
            ],
        ]);

        $entities = (new JsonParser)->parseJsonToEntities((string) $json);
        $entity = $entities->first();
        $this->assertNotNull($entity);

        $status = $entity->fields->firstWhere('name', 'status');
        $this->assertNotNull($status);
        $this->assertSame('string', $status->type);
        $this->assertTrue($status->isEnum());
        $this->assertSame(['draft', 'published'], $status->getEnumValues());
        $this->assertSame('Status', $status->getEnumClass());
    }

    public function test_plain_enum_type_without_values_stays_string(): void
    {
        $json = json_encode([
            [
                'name' => 'Article',
                'attributes' => [
                    ['name' => 'status', '_type' => 'enum'],
                ],
            ],
        ]);

        $entities = (new JsonParser)->parseJsonToEntities((string) $json);
        $status = $entities->first()?->fields->firstWhere('name', 'status');
        $this->assertNotNull($status);
        $this->assertSame('string', $status->type);
        $this->assertFalse($status->isEnum());
    }
}
