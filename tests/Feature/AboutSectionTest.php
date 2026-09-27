<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use nameless\CodeGenerator\Support\Protocol;
use nameless\CodeGenerator\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AboutSectionTest extends TestCase
{
    #[Test]
    public function php_artisan_about_describes_the_generator(): void
    {
        Artisan::call('about', ['--only' => 'laravel_api_generator', '--json' => true]);

        $about = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($about);
        $this->assertArrayHasKey('laravel_api_generator', $about);
        $section = $about['laravel_api_generator'];
        $this->assertSame(Protocol::VERSION, $section['protocol']);
        $this->assertSame(Protocol::packageVersion(), $section['version']);
        $this->assertSame('none', $section['schema_file']);
        $this->assertSame('package defaults', $section['stubs']);
    }
}
