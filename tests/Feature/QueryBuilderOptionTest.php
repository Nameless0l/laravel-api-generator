<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\Test;

class QueryBuilderOptionTest extends GeneratorTestCase
{
    protected array $generatedEntities = ['Product'];

    protected array $generatedTables = ['products'];

    #[Test]
    public function it_generates_a_spatie_query_builder_service_and_controller(): void
    {
        /** @var PendingCommand $command */
        $command = $this->artisan('make:fullapi', [
            'name' => 'Product',
            '--fields' => 'name:string,price:float',
            '--query-builder' => true,
        ]);
        $command->run();

        $service = (string) file_get_contents(app_path('Services/ProductService.php'));
        $this->assertStringContainsString('use Spatie\QueryBuilder\QueryBuilder;', $service);
        $this->assertStringContainsString('QueryBuilder::for(Product::class)', $service);
        $this->assertStringContainsString("allowedFilters(['name', 'price'])", $service);
        $this->assertStringContainsString("allowedSorts(['id', 'name', 'price', 'created_at'])", $service);

        $controller = (string) file_get_contents(app_path('Http/Controllers/ProductController.php'));
        $this->assertStringContainsString('$this->service->getAll()', $controller);
        $this->assertStringNotContainsString('use Illuminate\Http\Request;', $controller);
    }

    #[Test]
    public function it_keeps_the_default_service_without_the_flag(): void
    {
        /** @var PendingCommand $command */
        $command = $this->artisan('make:fullapi', [
            'name' => 'Product',
            '--fields' => 'name:string',
        ]);
        $command->run();

        $service = (string) file_get_contents(app_path('Services/ProductService.php'));
        $this->assertStringNotContainsString('QueryBuilder', $service);
        $this->assertStringContainsString('getAll(array $filters = [])', $service);
    }
}
