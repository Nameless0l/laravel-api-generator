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
