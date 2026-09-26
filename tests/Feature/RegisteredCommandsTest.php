<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use nameless\CodeGenerator\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RegisteredCommandsTest extends TestCase
{
    #[Test]
    public function the_package_registers_only_its_documented_commands(): void
    {
        $registered = collect(Artisan::all())
            ->filter(fn (object $command) => str_starts_with($command::class, 'nameless\\CodeGenerator\\'))
            ->keys()
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'api-generator:clean-routes',
            'api-generator:install',
            'api-generator:introspect',
            'api-generator:validate-stubs',
            'delete:fullapi',
            'make:fullapi',
        ], $registered);
    }
}
