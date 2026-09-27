<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use nameless\CodeGenerator\EntitiesGenerator\DTOGenerator;
use nameless\CodeGenerator\EntitiesGenerator\RequestGenerator;
use nameless\CodeGenerator\EntitiesGenerator\ServiceGenerator;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\Support\StubLoader;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use PHPUnit\Framework\Attributes\Test;

class PartialUpdateDtoTest extends GeneratorTestCase
{
    protected array $generatedEntities = ['Leaflet'];

    protected function setUp(): void
    {
        parent::setUp();

        (new RequestGenerator(app(StubLoader::class)))->generate($this->leaflet());
        (new DTOGenerator(app(StubLoader::class)))->generate($this->leaflet());
        require_once app_path('Http/Requests/StoreLeafletRequest.php');
        require_once app_path('Http/Requests/UpdateLeafletRequest.php');
        require_once app_path('DTO/LeafletDTO.php');

        Route::apiResource('leaflets', 'App\Http\Controllers\LeafletController');
    }

    private function leaflet(string $extraField = ''): EntityDefinition
    {
        $fields = [
            new FieldDefinition(name: 'title', type: 'string'),
            new FieldDefinition(name: 'subtitle', type: 'string', nullable: true),
            new FieldDefinition(name: 'pages', type: 'integer', nullable: true),
            new FieldDefinition(name: 'meta', type: 'json', nullable: true),
        ];

        if ($extraField !== '') {
            $fields[] = new FieldDefinition(name: $extraField, type: 'string');
        }

        return new EntityDefinition(name: 'Leaflet', fields: new Collection($fields), relationships: new Collection);
    }

    #[Test]
    public function a_dto_built_by_hand_saves_every_property(): void
    {
        $dto = $this->newDto(title: 'Guide', pages: 3);

        $this->assertSame(['title' => 'Guide', 'subtitle' => null, 'pages' => 3, 'meta' => null], $dto->toArray());
    }

    #[Test]
    public function a_dto_from_an_update_only_carries_the_fields_sent(): void
    {
        $this->assertSame(['pages' => 12], $this->dto('Update', 'PATCH', '/leaflets/1', ['pages' => '12'])->toArray());
    }

    #[Test]
    public function an_explicit_null_stays_null_instead_of_becoming_zero(): void
    {
        $this->assertSame(['pages' => null], $this->dto('Update', 'PATCH', '/leaflets/1', ['pages' => null])->toArray());
    }

    #[Test]
    public function a_dto_from_a_store_leaves_out_the_optional_fields_not_sent(): void
    {
        $dto = $this->dto('Store', 'POST', '/leaflets', ['title' => 'Guide', 'meta' => '{"lang":"fr"}']);

        $this->assertSame(['title' => 'Guide', 'meta' => ['lang' => 'fr']], $dto->toArray());
    }

    #[Test]
    public function the_services_save_what_the_dto_carries(): void
    {
        foreach ([false, true] as $queryBuilder) {
            (new ServiceGenerator(app(StubLoader::class)))->generate($this->leaflet()->withOptions(['query_builder' => $queryBuilder]));
            $service = (string) file_get_contents(app_path('Services/LeafletService.php'));

            $this->assertStringContainsString('return Leaflet::create($dto->toArray());', $service);
            $this->assertStringContainsString('$leaflet->update($dto->toArray());', $service);
            $this->assertStringNotContainsString('get_object_vars', $service);
        }
    }

    #[Test]
    public function a_field_named_like_the_dto_bookkeeping_is_refused(): void
    {
        $this->expectException(CodeGeneratorException::class);
        $this->expectExceptionMessage('Leaflet.provided: the generated DTO keeps the list of sent fields in $provided, rename the field.');

        (new DTOGenerator(app(StubLoader::class)))->generate($this->leaflet('provided'));
    }

    /**
     * The DTO class only exists once generated, so its type stays unknown here.
     */
    private function newDto(mixed ...$arguments): mixed
    {
        $class = $this->dtoClass();

        return new $class(...$arguments);
    }

    private function dtoClass(): string
    {
        return "App\\DTO\\{$this->leaflet()->name}DTO";
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dto(string $kind, string $method, string $uri, array $payload): mixed
    {
        /** @var class-string<FormRequest> $class */
        $class = "App\\Http\\Requests\\{$kind}LeafletRequest";
        $request = $class::create($uri, $method, $payload);
        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();

        return $this->dtoClass()::fromRequest($request);
    }
}
