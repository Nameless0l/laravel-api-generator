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
        $this->assertStringContainsString('QueryBuilder::for(Product::class, new Request($query))', $service);
        $this->assertStringContainsString("allowedFilters([AllowedFilter::exact('id'), AllowedFilter::exact('name'), AllowedFilter::exact('price')])", $service);
        $this->assertStringContainsString("allowedSorts(['id', 'name', 'price', 'created_at', 'updated_at'])", $service);

        $controller = (string) file_get_contents(app_path('Http/Controllers/ProductController.php'));
        $this->assertStringContainsString('$this->service->paginate($request->query())', $controller);
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
        $this->assertStringContainsString('public function paginate(array $query = []): LengthAwarePaginator', $service);
    }

    #[Test]
    public function it_sorts_on_a_custom_primary_key(): void
    {
        /** @var PendingCommand $command */
        $command = $this->artisan('make:fullapi', [
            'name' => 'Product',
            '--fields' => 'sku:string:primary,name:string',
            '--query-builder' => true,
        ]);
        $command->run();

        $service = (string) file_get_contents(app_path('Services/ProductService.php'));
        $this->assertStringContainsString("allowedSorts(['sku', 'name', 'created_at', 'updated_at'])", $service);
        $this->assertStringContainsString("->defaultSort('-sku')", $service);
        $this->assertStringNotContainsString("'-id'", $service);
    }
}
