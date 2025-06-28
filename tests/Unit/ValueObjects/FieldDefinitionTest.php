<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Unit\ValueObjects;

use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class FieldDefinitionTest extends TestCase
{
    public function test_creates_field_definition_with_valid_data(): void
    {
        $field = new FieldDefinition(
            name: 'email',
            type: 'string'
        );

        $this->assertEquals('email', $field->name);
        $this->assertEquals('string', $field->type);
        $this->assertTrue($field->nullable);
    }

    public function test_throws_exception_for_empty_field_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field name cannot be empty');

        new FieldDefinition(name: '', type: 'string');
    }

    public function test_throws_exception_for_invalid_field_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid field name: 123invalid');

        new FieldDefinition(name: '123invalid', type: 'string');
    }

    public function test_throws_exception_for_unsupported_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported field type: unsupported');

        new FieldDefinition(name: 'field', type: 'unsupported');
    }

    public function test_gets_correct_database_type(): void
    {
        $stringField = new FieldDefinition('name', 'string');
        $this->assertEquals('string', $stringField->getDatabaseType());

        $intField = new FieldDefinition('age', 'integer');
        $this->assertEquals('integer', $intField->getDatabaseType());

        $boolField = new FieldDefinition('active', 'boolean');
        $this->assertEquals('boolean', $boolField->getDatabaseType());
    }

    public function test_gets_correct_php_type(): void
    {
        $stringField = new FieldDefinition('name', 'string');
        $this->assertEquals('string', $stringField->getPhpType());

        $intField = new FieldDefinition('age', 'integer');
        $this->assertEquals('int', $intField->getPhpType());

        $boolField = new FieldDefinition('active', 'boolean');
        $this->assertEquals('bool', $boolField->getPhpType());
    }

    public function test_gets_correct_validation_rule(): void
    {
        $stringField = new FieldDefinition('name', 'string');
        $this->assertEquals('sometimes|string|max:255', $stringField->getValidationRule());

        $requiredField = new FieldDefinition('email', 'string', false);
        $this->assertEquals('required|string|max:255', $requiredField->getValidationRule());
    }

    public function test_gets_correct_fake_value(): void
    {
        $stringField = new FieldDefinition('name', 'string');
        $this->assertEquals('fake()->word()', $stringField->getFakeValue());

        $intField = new FieldDefinition('age', 'integer');
        $this->assertEquals('fake()->randomNumber()', $intField->getFakeValue());

        $uuidField = new FieldDefinition('id', 'uuid');
        $this->assertEquals('fake()->uuid()', $uuidField->getFakeValue());
    }
}
