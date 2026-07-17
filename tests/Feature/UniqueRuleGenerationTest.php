<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Collection;
use nameless\CodeGenerator\EntitiesGenerator\FactoryGenerator;
use nameless\CodeGenerator\EntitiesGenerator\RequestGenerator;
use nameless\CodeGenerator\Support\StubLoader;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use PHPUnit\Framework\Attributes\Test;

/**
 * Regression: fields marked unique (typically discovered by --from-database)
 * produced a bare "unique" validation rule that made every generated
 * store/update endpoint fail with a 500, and factories without unique fakes
 * that collided as soon as tests created a few rows.
 */
class UniqueRuleGenerationTest extends GeneratorTestCase
{
    protected array $generatedEntities = ['Product'];

    protected array $generatedTables = ['products'];

    private function definition(): EntityDefinition
    {
        return new EntityDefinition(
            name: 'Product',
            fields: new Collection([
                new FieldDefinition(name: 'slug', type: 'string', unique: true),
                new FieldDefinition(name: 'title', type: 'string'),
            ]),
            relationships: new Collection,
        );
    }

    #[Test]
    public function unique_fields_generate_a_parameterized_rule_that_ignores_the_current_model(): void
    {
        (new RequestGenerator(app(StubLoader::class)))->generate($this->definition());

        $request = (string) file_get_contents(app_path('Http/Requests/ProductRequest.php'));

        $this->assertStringContainsString(
            "'slug' => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('products')->ignore(\$this->route('product'))],",
            $request
        );
        $this->assertStringContainsString("'title' => 'required|string|max:255',", $request);
        $this->assertStringNotContainsString('|unique', $request);
    }

    #[Test]
    public function unique_fields_generate_unique_factory_fakes(): void
    {
        (new FactoryGenerator(app(StubLoader::class)))->generate($this->definition());

        $factory = (string) file_get_contents(database_path('factories/ProductFactory.php'));

        $this->assertStringContainsString("'slug' => fake()->unique()->slug(),", $factory);
        $this->assertStringContainsString("'title' => fake()->word(),", $factory);
    }
}
