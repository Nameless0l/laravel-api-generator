<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;

class DeleteCommandTest extends GeneratorTestCase
{
    protected array $generatedEntities = ['Widget'];

    protected array $generatedTables = ['widgets'];

    protected function setUp(): void
    {
        parent::setUp();

        file_put_contents(app_path('Models/Widget.php'), "<?php\n");
    }

    #[Test]
    public function it_keeps_the_files_when_the_deletion_is_not_confirmed(): void
    {
        $this->pendingArtisan('delete:fullapi', ['name' => 'Widget'])
            ->expectsConfirmation('Delete every generated file for Widget?', 'no')
            ->assertSuccessful();

        $this->assertFileExists(app_path('Models/Widget.php'));
    }

    #[Test]
    public function it_deletes_the_files_once_confirmed(): void
    {
        $this->pendingArtisan('delete:fullapi', ['name' => 'Widget'])
            ->expectsConfirmation('Delete every generated file for Widget?', 'yes')
            ->assertSuccessful();

        $this->assertFileDoesNotExist(app_path('Models/Widget.php'));
    }

    #[Test]
    public function force_skips_the_confirmation(): void
    {
        $this->pendingArtisan('delete:fullapi', ['name' => 'Widget', '--force' => true])->assertSuccessful();

        $this->assertFileDoesNotExist(app_path('Models/Widget.php'));
    }
}
