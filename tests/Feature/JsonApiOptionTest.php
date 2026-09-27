<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\Test;

class JsonApiOptionTest extends GeneratorTestCase
{
    protected array $generatedEntities = ['Gadget'];

    protected array $generatedTables = ['gadgets'];

    #[Test]
    public function it_generates_a_json_api_resource_when_supported(): void
    {
        if (! class_exists(JsonApiResource::class)) {
            $this->markTestSkipped('JsonApiResource requires Laravel 12.45+.');
        }

        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', [
            'name' => 'Gadget',
            '--fields' => 'name:string',
            '--json-api' => true,
        ]);
        $result->run();

        $resource = (string) file_get_contents(app_path('Http/Resources/GadgetResource.php'));
        $this->assertStringContainsString('use Illuminate\Http\Resources\JsonApi\JsonApiResource;', $resource);
        $this->assertStringContainsString('extends JsonApiResource', $resource);
        $this->assertStringContainsString('public $attributes = [', $resource);
        $this->assertStringContainsString("'name',", $resource);
        $this->assertStringNotContainsString("'id',", $resource);

        $featureTest = (string) file_get_contents(base_path('tests/Feature/GadgetControllerTest.php'));
        $this->assertStringContainsString("assertJsonPath('data.id', (string) \$gadget->getKey())", $featureTest);
    }

    #[Test]
    public function it_falls_back_to_a_standard_resource_on_older_laravel(): void
    {
        if (class_exists(JsonApiResource::class)) {
            $this->markTestSkipped('Runtime supports JSON:API; fallback path not exercised here.');
        }

        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', [
            'name' => 'Gadget',
            '--fields' => 'name:string',
            '--json-api' => true,
        ]);
        $result->run();

        $resource = (string) file_get_contents(app_path('Http/Resources/GadgetResource.php'));
        $this->assertStringContainsString('extends JsonResource', $resource);
        $this->assertStringNotContainsString('JsonApiResource', $resource);
    }

    #[Test]
    public function a_schema_file_can_ask_for_json_api_resources(): void
    {
        if (! class_exists(JsonApiResource::class)) {
            $this->markTestSkipped('JsonApiResource requires Laravel 12.45+.');
        }

        $this->pendingArtisan('make:fullapi', ['--schema' => $this->jsonApiSchema()])->assertSuccessful();

        $resource = (string) file_get_contents(app_path('Http/Resources/GadgetResource.php'));
        $this->assertStringContainsString('extends JsonApiResource', $resource);
    }

    #[Test]
    public function a_schema_file_asking_for_json_api_falls_back_on_older_laravel(): void
    {
        if (class_exists(JsonApiResource::class)) {
            $this->markTestSkipped('Runtime supports JSON:API; fallback path not exercised here.');
        }

        $this->pendingArtisan('make:fullapi', ['--schema' => $this->jsonApiSchema()])
            ->expectsOutputToContain('JSON:API resources need Laravel 12.45+')
            ->assertSuccessful();

        $resource = (string) file_get_contents(app_path('Http/Resources/GadgetResource.php'));
        $this->assertStringContainsString('extends JsonResource', $resource);
    }

    private function jsonApiSchema(): string
    {
        $path = base_path('json-api-schema.yaml');
        file_put_contents($path, "entities:\n  Gadget:\n    json_api: true\n    fields:\n      name: string\n");
        $this->beforeApplicationDestroyed(function () use ($path): void {
            @unlink($path);
        });

        return $path;
    }

    #[Test]
    public function it_generates_a_standard_resource_by_default(): void
    {
        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', [
            'name' => 'Gadget',
            '--fields' => 'name:string',
        ]);
        $result->run();

        $resource = (string) file_get_contents(app_path('Http/Resources/GadgetResource.php'));
        $this->assertStringContainsString('extends JsonResource', $resource);
        $this->assertStringContainsString("'id' => \$this->id,", $resource);
    }
}
