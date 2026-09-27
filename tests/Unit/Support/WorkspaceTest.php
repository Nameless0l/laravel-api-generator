<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Unit\Support;

use Illuminate\Support\Facades\File;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\Support\Workspace;
use nameless\CodeGenerator\Tests\TestCase;
use nameless\CodeGenerator\ValueObjects\FileChange;
use PHPUnit\Framework\Attributes\Test;

class WorkspaceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'workspace-'.uniqid();
        File::ensureDirectoryExists($this->root.'/app');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    private function workspace(): Workspace
    {
        return new Workspace($this->root, fn (): int => 1767225600);
    }

    #[Test]
    public function writes_stay_in_memory_until_commit(): void
    {
        $workspace = $this->workspace();
        $workspace->put($this->root.'/app/Post.php', '<?php // post', 'Model', 'Post');

        $this->assertTrue($workspace->exists($this->root.'/app/Post.php'));
        $this->assertSame('<?php // post', $workspace->get($this->root.'/app/Post.php'));
        $this->assertFileDoesNotExist($this->root.'/app/Post.php');

        $workspace->commit();

        $this->assertSame('<?php // post', file_get_contents($this->root.'/app/Post.php'));
        $this->assertSame([], $workspace->changes());
    }

    #[Test]
    public function changes_compare_pending_content_with_the_disk(): void
    {
        file_put_contents($this->root.'/app/Same.php', 'same');
        file_put_contents($this->root.'/app/Old.php', 'old');
        $workspace = $this->workspace();
        $workspace->put($this->root.'/app/New.php', 'new', 'Model', 'New');
        $workspace->put($this->root.'\\app\\Old.php', 'changed', 'Model', 'Old');
        $workspace->put($this->root.'/app/Same.php', 'same', 'Model', 'Same');

        $actions = [];
        foreach ($workspace->changes() as $change) {
            $actions[$change->path] = $change->action;
        }

        $this->assertSame([
            'app/New.php' => FileChange::CREATE,
            'app/Old.php' => FileChange::UPDATE,
            'app/Same.php' => FileChange::UNCHANGED,
        ], $actions);
    }

    #[Test]
    public function append_extends_the_current_content(): void
    {
        file_put_contents($this->root.'/routes.php', "<?php\n");
        $workspace = $this->workspace();

        $workspace->append($this->root.'/routes.php', "Route::a();\n", 'Routes');
        $workspace->append($this->root.'/routes.php', "Route::b();\n", 'Routes');

        $this->assertSame("<?php\nRoute::a();\nRoute::b();\n", $workspace->get($this->root.'/routes.php'));
        $this->assertSame("<?php\n", file_get_contents($this->root.'/routes.php'));
    }

    #[Test]
    public function glob_sees_files_on_disk_and_pending_files(): void
    {
        File::ensureDirectoryExists($this->root.'/migrations');
        file_put_contents($this->root.'/migrations/2020_01_01_000000_create_posts_table.php', '');
        $workspace = $this->workspace();
        $workspace->put($this->root.'/migrations/2026_01_01_000000_create_tags_table.php', '', 'Migration');

        $found = array_map('basename', $workspace->glob($this->root.'/migrations/*_create_*_table.php'));

        $this->assertSame([
            '2020_01_01_000000_create_posts_table.php',
            '2026_01_01_000000_create_tags_table.php',
        ], $found);
    }

    #[Test]
    public function migration_timestamps_are_consecutive_within_a_run(): void
    {
        $workspace = $this->workspace();

        $this->assertSame('2026_01_01_000000', $workspace->migrationTimestamp());
        $this->assertSame('2026_01_01_000001', $workspace->migrationTimestamp());
    }

    #[Test]
    public function the_first_writer_names_the_file(): void
    {
        $workspace = $this->workspace();
        $workspace->put($this->root.'/routes/api.php', 'a', 'Routes');
        $workspace->put($this->root.'/routes/api.php', 'b', 'Auth', 'Post');

        $change = $workspace->changes()[0];

        $this->assertSame('Routes', $change->kind);
        $this->assertNull($change->entity);
        $this->assertSame('b', $change->content);
    }

    #[Test]
    public function reading_a_missing_file_fails(): void
    {
        $this->expectException(CodeGeneratorException::class);

        $this->workspace()->get($this->root.'/missing.php');
    }

    #[Test]
    public function to_array_omits_content_unless_asked(): void
    {
        $change = new FileChange('app/Models/Post.php', 'Model', 'Post', FileChange::CREATE, '<?php');

        $this->assertSame(
            ['path' => 'app/Models/Post.php', 'kind' => 'Model', 'entity' => 'Post', 'action' => 'create'],
            $change->toArray(false)
        );
        $this->assertSame('<?php', $change->toArray(true)['content']);
        $this->assertArrayNotHasKey('entity', (new FileChange('routes/api.php', 'Routes', null, FileChange::UPDATE, ''))->toArray(false));
    }
}
