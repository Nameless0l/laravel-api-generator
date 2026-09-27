<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
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
    protected array $generatedEntities = ['Product', 'BlogPost', 'Country'];

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
    public function unique_fields_generate_a_parameterized_rule_that_ignores_the_current_model_on_update(): void
    {
        (new RequestGenerator(app(StubLoader::class)))->generate($this->definition());

        $store = (string) file_get_contents(app_path('Http/Requests/StoreProductRequest.php'));
        $update = (string) file_get_contents(app_path('Http/Requests/UpdateProductRequest.php'));

        $this->assertStringContainsString(
            "'slug' => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('products')],",
            $store
        );
        $this->assertStringContainsString(
            "'slug' => ['sometimes', 'required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('products')->ignore(\$this->route('product'))],",
            $update
        );
        $this->assertStringContainsString("'title' => 'required|string|max:255',", $store);
        $this->assertStringContainsString("'title' => 'sometimes|required|string|max:255',", $update);
        $this->assertStringNotContainsString('|unique', $store.$update);
    }

    #[Test]
    public function unique_fields_generate_unique_factory_fakes(): void
    {
        (new FactoryGenerator(app(StubLoader::class)))->generate($this->definition());

        $factory = (string) file_get_contents(database_path('factories/ProductFactory.php'));

        $this->assertStringContainsString("'slug' => fake()->unique()->slug(),", $factory);
        $this->assertStringContainsString("'title' => fake()->word(),", $factory);
    }

    #[Test]
    public function multi_word_entities_accept_an_update_that_keeps_their_unique_value(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->timestamps();
        });
        DB::table('blog_posts')->insert(['title' => 'Hello', 'slug' => 'hello-world']);

        $definition = new EntityDefinition(
            name: 'BlogPost',
            fields: new Collection([
                new FieldDefinition(name: 'title', type: 'string'),
                new FieldDefinition(name: 'slug', type: 'string', unique: true),
            ]),
            relationships: new Collection,
        );

        $validated = $this->validateUpdate($definition, '/blogposts/1', ['title' => 'Renamed', 'slug' => 'hello-world']);

        $this->assertSame(['title' => 'Renamed', 'slug' => 'hello-world'], $validated);
    }

    #[Test]
    public function custom_primary_keys_accept_an_update_that_keeps_the_unique_value(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->string('name')->unique();
            $table->timestamps();
        });
        DB::table('countries')->insert(['code' => 'FR', 'name' => 'France']);

        $definition = new EntityDefinition(
            name: 'Country',
            fields: new Collection([
                new FieldDefinition(name: 'code', type: 'string', attributes: ['primary' => true]),
                new FieldDefinition(name: 'name', type: 'string', unique: true),
            ]),
            relationships: new Collection,
        );

        $validated = $this->validateUpdate($definition, '/countries/FR', ['code' => 'FR', 'name' => 'France']);

        $this->assertSame(['code' => 'FR', 'name' => 'France'], $validated);
    }

    /**
     * Goes through the route apiResource registers: its parameter name is what the rule must read.
     *
     * @param  array<string, string>  $payload
     * @return array<string, mixed>
     */
    private function validateUpdate(EntityDefinition $definition, string $uri, array $payload): array
    {
        (new RequestGenerator(app(StubLoader::class)))->generate($definition);
        require_once app_path("Http/Requests/Update{$definition->name}Request.php");

        Route::apiResource($definition->getPluralName(), "App\\Http\\Controllers\\{$definition->name}Controller");

        /** @var class-string<FormRequest> $class */
        $class = "App\\Http\\Requests\\Update{$definition->name}Request";
        $request = $class::create($uri, 'PUT', $payload);
        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);
        $request->setContainer(app())->setRedirector(app('redirect'));

        try {
            $request->validateResolved();
        } catch (ValidationException $e) {
            $this->fail('The update was rejected: '.json_encode($e->errors()));
        }

        return $request->validated();
    }
}
