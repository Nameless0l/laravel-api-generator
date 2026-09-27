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
use nameless\CodeGenerator\EntitiesGenerator\RequestGenerator;
use nameless\CodeGenerator\Support\StubLoader;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use PHPUnit\Framework\Attributes\Test;

class StoreAndUpdateRequestTest extends GeneratorTestCase
{
    protected array $generatedEntities = ['Memo'];

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('memos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('code')->unique();
            $table->timestamps();
        });
        DB::table('memos')->insert(['title' => 'First', 'code' => 'M-1']);

        (new RequestGenerator(app(StubLoader::class)))->generate(new EntityDefinition(
            name: 'Memo',
            fields: new Collection([
                new FieldDefinition(name: 'title', type: 'string'),
                new FieldDefinition(name: 'subtitle', type: 'string', nullable: true),
                new FieldDefinition(name: 'code', type: 'string', unique: true),
            ]),
            relationships: new Collection,
        ));

        Route::apiResource('memos', 'App\Http\Controllers\MemoController');
    }

    #[Test]
    public function it_writes_a_store_and_an_update_request_instead_of_a_single_one(): void
    {
        $this->assertFileExists(app_path('Http/Requests/StoreMemoRequest.php'));
        $this->assertFileExists(app_path('Http/Requests/UpdateMemoRequest.php'));
        $this->assertFileDoesNotExist(app_path('Http/Requests/MemoRequest.php'));
    }

    #[Test]
    public function a_nullable_field_accepts_an_explicit_null_on_store(): void
    {
        $validated = $this->validated('Store', 'POST', '/memos', ['title' => 'Hello', 'subtitle' => null, 'code' => 'M-2']);

        $this->assertSame(['title' => 'Hello', 'subtitle' => null, 'code' => 'M-2'], $validated);
    }

    #[Test]
    public function a_store_rejects_a_unique_value_already_taken(): void
    {
        $this->assertSame(['code'], array_keys($this->errors('Store', 'POST', '/memos', ['title' => 'Hello', 'code' => 'M-1'])));
    }

    #[Test]
    public function an_update_accepts_a_partial_payload(): void
    {
        $this->assertSame(['subtitle' => 'Short'], $this->validated('Update', 'PATCH', '/memos/1', ['subtitle' => 'Short']));
    }

    #[Test]
    public function an_update_still_validates_the_fields_it_receives(): void
    {
        $this->assertSame(['title'], array_keys($this->errors('Update', 'PATCH', '/memos/1', ['title' => ''])));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validated(string $kind, string $method, string $uri, array $payload): array
    {
        $request = $this->request($kind, $method, $uri, $payload);

        try {
            $request->validateResolved();
        } catch (ValidationException $e) {
            $this->fail('The request was rejected: '.json_encode($e->errors()));
        }

        return $request->validated();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array<int, string>>
     */
    private function errors(string $kind, string $method, string $uri, array $payload): array
    {
        try {
            $this->request($kind, $method, $uri, $payload)->validateResolved();
        } catch (ValidationException $e) {
            return $e->errors();
        }

        $this->fail('The request was accepted.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function request(string $kind, string $method, string $uri, array $payload): FormRequest
    {
        require_once app_path("Http/Requests/{$kind}MemoRequest.php");

        /** @var class-string<FormRequest> $class */
        $class = "App\\Http\\Requests\\{$kind}MemoRequest";
        $request = $class::create($uri, $method, $payload);
        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);
        $request->setContainer(app())->setRedirector(app('redirect'));

        return $request;
    }
}
