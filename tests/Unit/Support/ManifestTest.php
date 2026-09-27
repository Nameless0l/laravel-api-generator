<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Unit\Support;

use Illuminate\Support\Facades\File;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\Support\Manifest;
use nameless\CodeGenerator\Tests\TestCase;
use nameless\CodeGenerator\ValueObjects\FileChange;
use PHPUnit\Framework\Attributes\Test;

class ManifestTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'manifest-'.uniqid();
        File::ensureDirectoryExists($this->root);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    private function model(string $content, string $entity = 'Post'): FileChange
    {
        return new FileChange("app/Models/{$entity}.php", 'Model', $entity, FileChange::CREATE, $content);
    }

    #[Test]
    public function a_project_without_manifest_knows_no_file(): void
    {
        $manifest = Manifest::load($this->root);

        $this->assertFalse($manifest->exists());
        $this->assertNull($manifest->isPristine('app/Models/Post.php', '<?php'));
    }

    #[Test]
    public function recorded_files_are_pristine_until_edited(): void
    {
        $manifest = Manifest::load($this->root);
        $manifest->record($this->model("<?php\nclass Post {}\n"));
        $manifest->save();

        $reloaded = Manifest::load($this->root);

        $this->assertTrue($reloaded->exists());
        $this->assertTrue($reloaded->isPristine('app/Models/Post.php', "<?php\r\nclass Post {}\r\n"));
        $this->assertFalse($reloaded->isPristine('app/Models/Post.php', "<?php\nclass Post { use HasUuids; }\n"));
        $this->assertFileExists($this->root.'/.api-generator/manifest.json');
    }

    #[Test]
    public function files_are_listed_per_entity_and_can_be_forgotten(): void
    {
        $manifest = Manifest::load($this->root);
        $manifest->record($this->model('a', 'Post'));
        $manifest->record(new FileChange('app/Http/Controllers/PostController.php', 'Controller', 'Post', FileChange::CREATE, 'b'));
        $manifest->record($this->model('c', 'Tag'));

        $manifest->forget('app/Models/Post.php');

        $this->assertSame(['app/Http/Controllers/PostController.php'], $manifest->filesOf('Post'));
        $this->assertSame(['app/Models/Tag.php'], $manifest->filesOf('Tag'));
    }

    #[Test]
    public function shared_files_are_not_tracked(): void
    {
        foreach (['Routes', 'DatabaseSeeder', 'Bootstrap', 'Postman'] as $kind) {
            $this->assertFalse(Manifest::tracks($kind), $kind);
        }

        $this->assertTrue(Manifest::tracks('Model'));
        $this->assertTrue(Manifest::tracks('PivotMigration'));
    }

    #[Test]
    public function a_corrupted_manifest_stops_the_run(): void
    {
        File::ensureDirectoryExists($this->root.'/.api-generator');
        file_put_contents($this->root.'/.api-generator/manifest.json', '{not json');

        try {
            Manifest::load($this->root);
            $this->fail('A corrupted manifest was accepted.');
        } catch (CodeGeneratorException $e) {
            $this->assertSame('invalid_manifest', $e->errorCode);
        }
    }
}
