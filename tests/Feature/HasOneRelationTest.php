<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\Test;

class HasOneRelationTest extends GeneratorTestCase
{
    protected array $generatedEntities = ['Author', 'Profile'];

    protected array $generatedTables = ['authors', 'profiles'];

    private string $schemaPath = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->schemaPath = base_path('api-schema-has-one-test.yaml');
    }

    protected function tearDown(): void
    {
        File::delete([$this->schemaPath, base_path('class_data.json')]);
        parent::tearDown();
    }

    #[Test]
    public function the_foreign_key_of_a_has_one_lives_on_the_related_table(): void
    {
        $this->generate(<<<'YAML'
        entities:
          Author:
            fields:
              name: string
            relations:
              profile: hasOne Profile
          Profile:
            fields:
              bio: text
        YAML);

        $this->assertStringNotContainsString('profile_id', (string) file_get_contents($this->firstMigrationFor('authors')));
        $this->assertStringContainsString(
            "\$table->foreignId('author_id')->constrained('authors')->cascadeOnDelete();",
            (string) file_get_contents($this->firstMigrationFor('profiles'))
        );
        $this->assertStringContainsString('return $this->hasOne(Profile::class);', (string) file_get_contents(app_path('Models/Author.php')));
        $this->assertStringContainsString('return $this->belongsTo(Author::class);', (string) file_get_contents(app_path('Models/Profile.php')));
        $this->assertStringNotContainsString('profile_id', (string) file_get_contents(app_path('Http/Requests/StoreAuthorRequest.php')));
    }

    #[Test]
    public function a_has_one_uses_the_key_of_a_role_named_belongs_to(): void
    {
        $this->generate(<<<'YAML'
        entities:
          Author:
            fields:
              name: string
            relations:
              profile: hasOne Profile
          Profile:
            fields:
              bio: text
            relations:
              owner: belongsTo Author
        YAML);

        $this->assertStringContainsString("return \$this->hasOne(Profile::class, 'owner_id');", (string) file_get_contents(app_path('Models/Author.php')));
        $this->assertStringContainsString("foreignId('owner_id')", (string) file_get_contents($this->firstMigrationFor('profiles')));
    }

    #[Test]
    public function a_has_one_to_a_model_outside_the_batch_warns_about_the_missing_column(): void
    {
        File::put($this->schemaPath, <<<'YAML'
        entities:
          Author:
            fields:
              name: string
            relations:
              profile: hasOne Profile
        YAML);

        Artisan::call('make:fullapi', ['--schema' => $this->schemaPath, '--dry-run' => true, '--json' => true]);
        $document = json_decode(Artisan::output(), true);

        $this->assertIsArray($document);
        $this->assertIsArray($document['warnings']);
        $warning = collect($document['warnings'])->firstWhere('code', 'has_one_foreign_key');
        $this->assertIsArray($warning);
        $this->assertSame('Author hasOne Profile: add an author_id column to profiles, or generate Profile with belongsTo Author.', $warning['message']);
    }

    #[Test]
    public function class_data_json_one_to_one_relations_get_their_inverse(): void
    {
        File::put(base_path('class_data.json'), (string) json_encode([
            ['name' => 'Author', 'attributes' => [['name' => 'name', '_type' => 'string']], 'oneToOneRelationships' => [['comodel' => 'Profile', 'role' => 'profile']]],
            ['name' => 'Profile', 'attributes' => [['name' => 'bio', '_type' => 'text']]],
        ]));

        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi');
        $result->assertSuccessful();
        $result->run();

        $this->assertStringContainsString("foreignId('author_id')", (string) file_get_contents($this->firstMigrationFor('profiles')));
        $this->assertStringNotContainsString('profile_id', (string) file_get_contents($this->firstMigrationFor('authors')));
    }

    private function generate(string $schema): void
    {
        File::put($this->schemaPath, $schema);

        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', ['--schema' => $this->schemaPath]);
        $result->assertSuccessful();
        $result->run();
    }
}
