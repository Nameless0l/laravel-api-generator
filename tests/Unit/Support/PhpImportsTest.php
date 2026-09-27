<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Unit\Support;

use nameless\CodeGenerator\Support\PhpImports;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PhpImportsTest extends TestCase
{
    #[Test]
    public function imports_are_sorted_the_way_pint_sorts_them(): void
    {
        $php = "<?php\n\nnamespace App\\Http\\Controllers;\n\nuse Illuminate\\Http\\Request;\nuse App\\Models2\\Legacy;\nuse App\\DTO\\PostDTO;\nuse App\\Models\\Post;\nuse App\\Domain\\Thing;\n\nclass PostController\n{\n}\n";

        $this->assertSame(
            "<?php\n\nnamespace App\\Http\\Controllers;\n\nuse App\\Domain\\Thing;\nuse App\\DTO\\PostDTO;\nuse App\\Models\\Post;\nuse App\\Models2\\Legacy;\nuse Illuminate\\Http\\Request;\n\nclass PostController\n{\n}\n",
            PhpImports::normalize($php)
        );
    }

    #[Test]
    public function duplicates_and_classes_of_the_same_namespace_are_dropped(): void
    {
        $php = "<?php\n\nnamespace App\\Models;\n\nuse App\\Models\\Tag;\nuse Illuminate\\Support\\Carbon;\nuse App\\Models\\Tag as Label;\nuse Illuminate\\Support\\Carbon;\nuse App\\Models\\Sub\\Thing;\n\nclass Post\n{\n}\n";

        $this->assertSame(
            "<?php\n\nnamespace App\\Models;\n\nuse App\\Models\\Sub\\Thing;\nuse App\\Models\\Tag as Label;\nuse Illuminate\\Support\\Carbon;\n\nclass Post\n{\n}\n",
            PhpImports::normalize($php)
        );
    }

    #[Test]
    public function a_block_left_empty_leaves_a_single_blank_line(): void
    {
        $php = "<?php\n\nnamespace App\\Models;\n\nuse App\\Models\\Tag;\n\nclass Post\n{\n}\n";

        $this->assertSame("<?php\n\nnamespace App\\Models;\n\nclass Post\n{\n}\n", PhpImports::normalize($php));
    }

    #[Test]
    public function a_file_without_imports_is_left_alone(): void
    {
        $php = "<?php\n\nnamespace App\\Enums;\n\nenum PostStatus: string\n{\n    case Draft = 'draft';\n}\n";

        $this->assertSame($php, PhpImports::normalize($php));
    }

    #[Test]
    public function an_import_is_added_among_the_others(): void
    {
        $php = "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::apiResource('posts', PostController::class);\n";

        $this->assertSame(
            "<?php\n\nuse App\\Http\\Controllers\\PostController;\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::apiResource('posts', PostController::class);\n",
            PhpImports::add($php, ['App\\Http\\Controllers\\PostController'])
        );
    }

    #[Test]
    public function an_import_lands_after_the_namespace_or_the_declare_when_there_is_none(): void
    {
        $this->assertSame(
            "<?php\n\nnamespace App\\Models;\n\nuse App\\Enums\\PostStatus;\n\nclass Post\n{\n}\n",
            PhpImports::add("<?php\n\nnamespace App\\Models;\n\nclass Post\n{\n}\n", ['App\\Enums\\PostStatus'])
        );
        $this->assertSame(
            "<?php\n\ndeclare(strict_types=1);\n\nuse App\\Enums\\PostStatus;\n\nreturn PostStatus::cases();\n",
            PhpImports::add("<?php\n\ndeclare(strict_types=1);\n\nreturn PostStatus::cases();\n", ['App\\Enums\\PostStatus'])
        );
    }

    #[Test]
    public function windows_line_endings_are_kept(): void
    {
        $php = "<?php\r\n\r\nuse Illuminate\\Support\\Facades\\Route;\r\n\r\nRoute::get('/', fn () => null);\r\n";

        $this->assertSame(
            "<?php\r\n\r\nuse App\\Http\\Controllers\\PostController;\r\nuse Illuminate\\Support\\Facades\\Route;\r\n\r\nRoute::get('/', fn () => null);\r\n",
            PhpImports::add($php, ['App\\Http\\Controllers\\PostController'])
        );
    }
}
