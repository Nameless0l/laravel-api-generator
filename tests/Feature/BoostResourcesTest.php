<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use nameless\CodeGenerator\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;

class BoostResourcesTest extends TestCase
{
    private const SKILL = __DIR__.'/../../resources/boost/skills/laravel-api-generator/SKILL.md';

    private const GUIDELINES = __DIR__.'/../../resources/boost/guidelines/core.blade.php';

    private const FRAMEWORK_COMMANDS = ['migrate', 'test', 'install:api'];

    private function text(): string
    {
        return file_get_contents(self::SKILL).file_get_contents(self::GUIDELINES);
    }

    #[Test]
    public function the_skill_follows_the_agent_skills_format(): void
    {
        if (preg_match('/\A---\R(.+?)\R---\R/s', (string) file_get_contents(self::SKILL), $matches) !== 1) {
            $this->fail('SKILL.md has no YAML frontmatter.');
        }
        $frontmatter = Yaml::parse($matches[1]);

        $this->assertIsArray($frontmatter);
        $this->assertSame(basename(dirname(self::SKILL)), $frontmatter['name']);
        $this->assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', $frontmatter['name']);
        $this->assertIsString($frontmatter['description']);
        $this->assertNotSame('', trim($frontmatter['description']));
        $this->assertLessThanOrEqual(1024, strlen($frontmatter['description']));
    }

    #[Test]
    public function the_guidelines_render_with_blade(): void
    {
        $rendered = Blade::render((string) file_get_contents(self::GUIDELINES));

        $this->assertStringContainsString('php artisan make:fullapi', $rendered);
        $this->assertStringNotContainsString('@verbatim', $rendered);
    }

    #[Test]
    public function every_command_they_mention_exists(): void
    {
        preg_match_all('/php artisan ([a-z][a-z0-9:-]*)/', $this->text(), $matches);
        $registered = array_keys(Artisan::all());

        foreach (array_unique($matches[1]) as $command) {
            $this->assertTrue(
                in_array($command, self::FRAMEWORK_COMMANDS, true) || in_array($command, $registered, true),
                "{$command} is mentioned but not registered."
            );
        }
    }

    #[Test]
    public function every_option_they_mention_exists(): void
    {
        $known = [];
        foreach (Artisan::all() as $command) {
            if (str_starts_with($command::class, 'nameless\\CodeGenerator\\')) {
                $known = array_merge($known, array_keys($command->getDefinition()->getOptions()));
            }
        }

        preg_match_all('/(?<![\w-])--([a-z][a-z-]*)/', $this->text(), $matches);

        foreach (array_unique($matches[1]) as $option) {
            $this->assertContains($option, $known, "--{$option} is mentioned but no generator command defines it.");
        }
    }
}
