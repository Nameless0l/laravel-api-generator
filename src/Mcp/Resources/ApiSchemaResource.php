<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Mcp\Resources;

use Illuminate\Support\Facades\File;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Name('api-schema')]
#[Title('api-schema JSON Schema')]
#[Description('JSON Schema of the api-schema document taken by plan-api and generate-api, which is also the format of api-schema.yaml.')]
#[Uri('api-generator://schema/api-schema.json')]
#[MimeType('application/schema+json')]
final class ApiSchemaResource extends Resource
{
    public function handle(): Response
    {
        return Response::text(File::get(__DIR__.'/../../../resources/schema/api-schema.json'));
    }
}
