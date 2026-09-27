<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use nameless\CodeGenerator\EntitiesGenerator\FeatureTestGenerator;
use nameless\CodeGenerator\EntitiesGenerator\ModelGeneratorRefactored;
use nameless\CodeGenerator\EntitiesGenerator\ServiceGenerator;
use nameless\CodeGenerator\EntitiesGenerator\UnitTestGenerator;
use nameless\CodeGenerator\Support\StubLoader;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use PHPUnit\Framework\Attributes\Test;

class PaginatedIndexTest extends GeneratorTestCase
{
    protected array $generatedEntities = ['Track'];

    /**
     * @param  array<string, bool>  $options
     */
    private function track(array $options = []): EntityDefinition
    {
        return new EntityDefinition(
            name: 'Track',
            fields: new Collection([
                new FieldDefinition(name: 'title', type: 'string'),
                new FieldDefinition(name: 'plays', type: 'integer'),
                new FieldDefinition(name: 'meta', type: 'json', nullable: true),
            ]),
            relationships: new Collection,
            options: $options,
        );
    }

    private function service(): string
    {
        return (string) file_get_contents(app_path('Services/TrackService.php'));
    }

    #[Test]
    public function the_generated_service_filters_sorts_and_paginates(): void
    {
        config(['api-generator.pagination' => ['per_page' => 2, 'max_per_page' => 3]]);
        (new ModelGeneratorRefactored(app(StubLoader::class)))->generate($this->track());
        (new ServiceGenerator(app(StubLoader::class)))->generate($this->track());
        require_once app_path('Models/Track.php');
        require_once app_path('Services/TrackService.php');

        Schema::create('tracks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->integer('plays');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
        foreach ([['A', 5], ['B', 9], ['C', 5], ['D', 1]] as [$title, $plays]) {
            DB::table('tracks')->insert(['title' => $title, 'plays' => $plays]);
        }

        $this->assertSame(['D', 'C'], $this->titles([]));
        $this->assertSame(['B', 'A'], $this->titles(['page' => '2']));
        $this->assertSame(['A', 'C'], $this->titles(['filter' => ['plays' => '5'], 'sort' => 'title']));
        $this->assertSame(['B', 'A', 'C'], $this->titles(['sort' => '-plays,title', 'per_page' => '50']));
        $this->assertSame(['D', 'C'], $this->titles(['filter' => ['meta' => 'x', 'unknown' => '1'], 'sort' => 'meta', 'per_page' => 'all']));
    }

    #[Test]
    public function json_columns_are_neither_filtered_nor_sorted(): void
    {
        (new ServiceGenerator(app(StubLoader::class)))->generate($this->track());

        $this->assertStringContainsString("private const FILTERS = ['id', 'title', 'plays'];", $this->service());
        $this->assertStringContainsString("private const SORTS = ['id', 'title', 'plays', 'created_at', 'updated_at'];", $this->service());
    }

    #[Test]
    public function the_query_builder_service_takes_the_same_parameters_with_exact_filters(): void
    {
        (new ServiceGenerator(app(StubLoader::class)))->generate($this->track(['query_builder' => true]));

        $this->assertStringContainsString('QueryBuilder::for(Track::class, new Request($query))', $this->service());
        $this->assertStringContainsString("->allowedFilters([AllowedFilter::exact('id'), AllowedFilter::exact('title'), AllowedFilter::exact('plays')])", $this->service());
        $this->assertStringContainsString("->allowedSorts(['id', 'title', 'plays', 'created_at', 'updated_at'])", $this->service());
        $this->assertStringContainsString("->paginate(\$perPage, ['*'], 'page', max((int) (\$query['page'] ?? 1), 1))", $this->service());
    }

    #[Test]
    public function the_generated_tests_check_the_total_a_filter_and_a_sort(): void
    {
        foreach ([[], ['json_api' => true]] as $options) {
            (new FeatureTestGenerator(app(StubLoader::class)))->generate($this->track($options));
            (new UnitTestGenerator(app(StubLoader::class)))->generate($this->track($options));
            $feature = (string) file_get_contents(base_path('tests/Feature/TrackControllerTest.php'));
            $unit = (string) file_get_contents(base_path('tests/Unit/TrackServiceTest.php'));

            $this->assertStringContainsString("->assertJsonPath('meta.total', 3);", $feature);
            $this->assertStringContainsString("http_build_query(['filter' => ['title' => \$value]])", $feature);
            $this->assertStringContainsString("\$this->service->paginate(['filter' => ['title' => \$value]]);", $unit);
            $this->assertStringNotContainsString('getAll', $unit);
        }

        $this->assertStringContainsString("->assertJsonPath('data.0.id', (string) Track::query()->orderBy('id')->value('id'))", $feature);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, mixed>
     */
    private function titles(array $query): array
    {
        $class = 'App\\Services\\'.$this->track()->name.'Service';
        $service = new $class;

        return array_map(fn (mixed $track): mixed => $track->title, $this->items($service, $query));
    }

    /**
     * The service class only exists once generated, so its type stays unknown here.
     *
     * @param  array<string, mixed>  $query
     * @return array<int, mixed>
     */
    private function items(mixed $service, array $query): array
    {
        return $service->paginate($query)->items();
    }
}
