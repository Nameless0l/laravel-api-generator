<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use nameless\CodeGenerator\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ValidateStubsCommandTest extends TestCase
{
    private string $published = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->published = base_path('stubs/vendor/laravel-api-generator');
        File::ensureDirectoryExists($this->published);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path('stubs/vendor'));

        parent::tearDown();
    }

    #[Test]
    public function the_stubs_shipped_with_the_package_are_valid(): void
    {
        File::copyDirectory(dirname(__DIR__, 2).'/stubs', $this->published);

        [$exitCode, $report] = $this->validate();

        $this->assertSame(0, $exitCode, (string) json_encode($report));
        $this->assertSame('ok', $report['status']);
        $this->assertContains(['stub' => 'request.store', 'status' => 'ok', 'missing' => []], $report['results']);
        $this->assertContains(['stub' => 'request.update', 'status' => 'ok', 'missing' => []], $report['results']);
    }

    #[Test]
    public function a_request_stub_left_from_3x_is_reported_without_failing(): void
    {
        File::put("{$this->published}/request.stub", "class {{modelName}}Request\n{\n    {{rules}}\n}\n");

        [$exitCode, $report] = $this->validate();

        $this->assertSame(0, $exitCode);
        $this->assertSame('obsolete', $this->row($report, 'request')['status']);
    }

    #[Test]
    public function a_controller_stub_from_3x_misses_the_route_parameter(): void
    {
        File::put("{$this->published}/controller.stub", "class {{modelName}}Controller\n{\n    public function show(int|string \$id) { \${{modelNameLower}} = \$this->service->find(\$id); }\n    // {{pluralName}}\n}\n");

        [$exitCode, $report] = $this->validate();

        $this->assertSame(1, $exitCode);
        $this->assertSame(['routeParameter'], $this->row($report, 'controller')['missing']);
    }

    #[Test]
    public function a_dto_stub_from_3x_misses_the_validated_attributes(): void
    {
        File::put("{$this->published}/dto.stub", "class {{modelName}}DTO\n{\n    public function __construct({{attributes}}) {}\n    public static function fromRequest(\$request) { return new self({{attributesFromRequest}}); }\n}\n");

        [$exitCode, $report] = $this->validate();

        $this->assertSame(1, $exitCode);
        $this->assertSame(['attributesFromValidated'], $this->row($report, 'dto')['missing']);
    }

    #[Test]
    public function a_service_stub_that_saves_every_dto_property_is_outdated(): void
    {
        File::put("{$this->published}/service.stub", "class {{modelName}}Service\n{\n    public function update({{modelName}} \${{modelNameLower}}, \$dto) { \${{modelNameLower}}->update(get_object_vars(\$dto)); }\n}\n");

        [$exitCode, $report] = $this->validate();

        $this->assertSame(1, $exitCode);
        $this->assertSame('invalid', $this->row($report, 'service')['status']);
        $this->assertStringContainsString('$dto->toArray()', (string) $this->row($report, 'service')['reason']);
    }

    /**
     * @return array{int, array{status: string, results: array<int, array<string, mixed>>}}
     */
    private function validate(): array
    {
        $exitCode = Artisan::call('api-generator:validate-stubs', ['--json' => true]);

        return [$exitCode, json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR)];
    }

    /**
     * @param  array{results: array<int, array<string, mixed>>}  $report
     * @return array<string, mixed>
     */
    private function row(array $report, string $stub): array
    {
        foreach ($report['results'] as $result) {
            if ($result['stub'] === $stub) {
                return $result;
            }
        }

        $this->fail("No result for {$stub}.stub");
    }
}
