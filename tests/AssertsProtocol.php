<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

trait AssertsProtocol
{
    private const PROTOCOL_SCHEMA_ID = 'https://raw.githubusercontent.com/Nameless0l/laravel-api-generator/main/resources/protocol/v1.schema.json';

    protected function assertMatchesProtocol(mixed $document, string $definition): void
    {
        $validator = new Validator;
        $validator->resolver()?->registerFile(self::PROTOCOL_SCHEMA_ID, dirname(__DIR__).'/resources/protocol/v1.schema.json');

        $result = $validator->validate(
            json_decode((string) json_encode($document)),
            (object) ['$ref' => self::PROTOCOL_SCHEMA_ID.'#/definitions/'.$definition]
        );

        $error = $result->error();
        $this->assertTrue(
            $result->isValid(),
            $error === null ? '' : (string) json_encode((new ErrorFormatter)->format($error))
        );
    }
}
