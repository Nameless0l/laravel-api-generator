<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Unit\Support;

use nameless\CodeGenerator\Support\ApiRoutesRegistrar;
use nameless\CodeGenerator\Support\WorkspaceFactory;
use nameless\CodeGenerator\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ApiRoutesRegistrarTest extends TestCase
{
    private const BOOTSTRAP = <<<'PHP'
<?php

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->create();
PHP;

    #[Test]
    public function it_registers_the_api_routes_when_the_generator_wrote_them(): void
    {
        $workspace = (new WorkspaceFactory)->make();
        $workspace->put(base_path('bootstrap/app.php'), self::BOOTSTRAP, 'Bootstrap');
        $workspace->put(base_path('routes/api.php'), "<?php\n", 'Routes');

        $warnings = (new ApiRoutesRegistrar)->register($workspace);

        $this->assertSame([], $warnings);
        $this->assertStringContainsString(
            "web: __DIR__.'/../routes/web.php',\n        api: __DIR__.'/../routes/api.php',",
            $workspace->get(base_path('bootstrap/app.php'))
        );
    }

    #[Test]
    public function it_warns_when_bootstrap_app_cannot_be_patched(): void
    {
        $workspace = (new WorkspaceFactory)->make();
        $workspace->put(base_path('bootstrap/app.php'), "<?php\n\nreturn \$app;\n", 'Bootstrap');
        $workspace->put(base_path('routes/api.php'), "<?php\n", 'Routes');

        $warnings = (new ApiRoutesRegistrar)->register($workspace);

        $this->assertSame('api_routes_not_loaded', $warnings[0]['code']);
        $this->assertStringContainsString('php artisan install:api', $warnings[0]['message']);
    }

    #[Test]
    public function it_leaves_an_app_that_already_loads_api_routes_alone(): void
    {
        $workspace = (new WorkspaceFactory)->make();
        $content = str_replace("health: '/up',", "api: __DIR__.'/../routes/api.php',\n        health: '/up',", self::BOOTSTRAP);
        $workspace->put(base_path('bootstrap/app.php'), $content, 'Bootstrap');

        $this->assertSame([], (new ApiRoutesRegistrar)->register($workspace));
        $this->assertSame($content, $workspace->get(base_path('bootstrap/app.php')));
    }
}
