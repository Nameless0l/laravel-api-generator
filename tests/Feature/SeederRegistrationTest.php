<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;

class SeederRegistrationTest extends GeneratorTestCase
{
    protected array $generatedEntities = ['Widget'];

    protected array $generatedTables = ['widgets'];

    private string $seederPath;

    private string $originalSeeder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seederPath = database_path('seeders/DatabaseSeeder.php');
        $this->originalSeeder = (string) file_get_contents($this->seederPath);
    }

    protected function tearDown(): void
    {
        file_put_contents($this->seederPath, $this->originalSeeder);

        parent::tearDown();
    }

    #[Test]
    public function the_seeder_is_registered_in_a_crlf_database_seeder(): void
    {
        file_put_contents($this->seederPath, str_replace("\n", "\r\n", <<<'PHP'
            <?php

            namespace Database\Seeders;

            use App\Models\User;
            use Illuminate\Database\Seeder;

            class DatabaseSeeder extends Seeder
            {
                public function run(): void
                {
                    User::factory()->create([
                        'name' => 'Test User',
                    ]);
                }
            }

            PHP));

        $this->pendingArtisan('make:fullapi', ['name' => 'Widget', '--fields' => 'name:string'])->assertSuccessful();

        $seeder = (string) file_get_contents($this->seederPath);
        $this->assertStringContainsString("    {\r\n        \$this->call(WidgetSeeder::class);\r\n", $seeder);
        $this->assertSame(0, preg_match("/(?<!\r)\n/", $seeder), 'The seeder must keep its CRLF line endings.');
    }
}
