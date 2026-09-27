<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use nameless\CodeGenerator\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class InstallCommandTest extends TestCase
{
    /**
     * @var array<string, string|null>
     */
    private array $originals = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([base_path('routes/api.php'), config_path('scramble.php')] as $path) {
            $this->originals[$path] = file_exists($path) ? (string) file_get_contents($path) : null;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originals as $path => $content) {
            if ($content === null) {
                @unlink($path);
            } else {
                file_put_contents($path, $content);
            }
        }

        parent::tearDown();
    }

    #[Test]
    public function it_points_to_the_generation_command_that_exists(): void
    {
        file_put_contents(base_path('routes/api.php'), "<?php\n");

        $this->pendingArtisan('api-generator:install')
            ->expectsOutputToContain('php artisan make:fullapi')
            ->doesntExpectOutputToContain('api:generate')
            ->doesntExpectOutputToContain('config/api-generator.php')
            ->assertSuccessful();
    }

    #[Test]
    public function it_never_overwrites_an_existing_scramble_config(): void
    {
        file_put_contents(base_path('routes/api.php'), "<?php\n");
        file_put_contents(config_path('scramble.php'), "<?php\n\nreturn ['api_path' => 'v1'];\n");

        $this->pendingArtisan('api-generator:install')->assertSuccessful();

        $this->assertSame("<?php\n\nreturn ['api_path' => 'v1'];\n", file_get_contents(config_path('scramble.php')));
    }

    #[Test]
    public function it_offers_install_api_when_the_api_routes_file_is_missing(): void
    {
        if (! array_key_exists('install:api', Artisan::all())) {
            $this->markTestSkipped('install:api ships with Laravel 11+.');
        }

        @unlink(base_path('routes/api.php'));

        $this->pendingArtisan('api-generator:install')
            ->expectsConfirmation('Set up API routes and Sanctum now with php artisan install:api?', 'no')
            ->expectsOutputToContain('php artisan install:api')
            ->assertSuccessful();
    }
}
