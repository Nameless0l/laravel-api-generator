<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use nameless\CodeGenerator\EntitiesGenerator\PolicyGenerator;
use nameless\CodeGenerator\Support\StubLoader;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;

class EnforcedPoliciesTest extends GeneratorTestCase
{
    protected array $generatedEntities = ['Media', 'Voucher'];

    protected array $generatedTables = ['media'];

    private string $routes = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->routes = (string) file_get_contents(base_path('routes/api.php'));
    }

    protected function tearDown(): void
    {
        file_put_contents(base_path('routes/api.php'), $this->routes);
        @unlink(app_path('Policies/UserPolicy.php'));

        parent::tearDown();
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function engines(): array
    {
        return ['Eloquent' => [false], 'Spatie QueryBuilder' => [true]];
    }

    #[Test]
    #[DataProvider('engines')]
    public function every_controller_method_asks_the_policy_first(bool $queryBuilder): void
    {
        Artisan::call('make:fullapi', ['name' => 'Media', '--fields' => 'title:string', '--soft-deletes' => true, '--query-builder' => $queryBuilder]);
        $controller = (string) file_get_contents(app_path('Http/Controllers/MediaController.php'));

        $this->assertStringContainsString('use Illuminate\Support\Facades\Gate;', $controller);

        $checks = [
            'index' => "Gate::authorize('viewAny', Media::class);",
            'store' => "Gate::authorize('create', Media::class);",
            'show' => "Gate::authorize('view', \$medium);",
            'update' => "Gate::authorize('update', \$medium);",
            'destroy' => "Gate::authorize('delete', \$medium);",
            'restore' => "Gate::authorize('restore', \$medium);",
            'forceDelete' => "Gate::authorize('forceDelete', \$medium);",
        ];

        foreach ($checks as $method => $check) {
            $this->assertMatchesRegularExpression('/public function '.$method.'\([^)]*\)\s*\{\s*'.preg_quote($check, '/').'/', $controller, $method);
        }
    }

    #[Test]
    public function the_generated_policy_lets_guests_through(): void
    {
        (new PolicyGenerator(app(StubLoader::class)))->generate($this->entity('Voucher'));
        $policy = (string) file_get_contents(app_path('Policies/VoucherPolicy.php'));
        require_once app_path('Policies/VoucherPolicy.php');
        Gate::policy('App\Models\Voucher', 'App\Policies\VoucherPolicy');

        $this->assertTrue(Gate::forUser(null)->allows('viewAny', 'App\Models\Voucher'));
        $this->assertTrue(Gate::forUser(null)->allows('create', 'App\Models\Voucher'));
        $this->assertSame(7, substr_count($policy, '(?User $user'));
        $this->assertSame(7, substr_count($policy, '): bool'));
        $this->assertStringNotContainsString('HandlesAuthorization', $policy);
    }

    #[Test]
    public function the_policy_of_a_user_entity_imports_the_user_model_once(): void
    {
        (new PolicyGenerator(app(StubLoader::class)))->generate($this->entity('User'));
        $path = app_path('Policies/UserPolicy.php');

        $lint = new Process([PHP_BINARY, '-l', $path]);
        $lint->run();

        $this->assertSame(1, substr_count((string) file_get_contents($path), 'use App\Models\User;'));
        $this->assertSame(0, $lint->getExitCode(), $lint->getOutput());
    }

    private function entity(string $name): EntityDefinition
    {
        return new EntityDefinition(
            name: $name,
            fields: new Collection([new FieldDefinition(name: 'code', type: 'string')]),
            relationships: new Collection,
        );
    }
}
