<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Collection;
use nameless\CodeGenerator\EntitiesGenerator\FeatureTestGenerator;
use nameless\CodeGenerator\Support\StubLoader;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use PHPUnit\Framework\Attributes\Test;

class GeneratedFeatureTestTest extends GeneratorTestCase
{
    protected array $generatedEntities = ['Ticket'];

    /**
     * @param  array<int, FieldDefinition>  $fields
     * @param  array<string, bool>  $options
     */
    private function generate(array $fields, array $options = []): string
    {
        $ticket = new EntityDefinition(name: 'Ticket', fields: new Collection($fields), relationships: new Collection, options: $options);
        (new FeatureTestGenerator(app(StubLoader::class)))->generate($ticket);

        return (string) file_get_contents(base_path('tests/Feature/TicketControllerTest.php'));
    }

    #[Test]
    public function a_patch_sends_one_field_and_checks_that_the_others_kept_their_value(): void
    {
        $test = $this->generate([
            new FieldDefinition(name: 'code', type: 'string', attributes: ['primary' => true]),
            new FieldDefinition(name: 'title', type: 'string'),
            new FieldDefinition(name: 'body', type: 'text'),
        ]);

        $this->assertStringContainsString('use Illuminate\Support\Arr;', $test);
        $this->assertStringContainsString("\$response = \$this->patchJson(\"/api/tickets/{\$ticket->getKey()}\", ['title' => 'test_title']);", $test);
        $this->assertStringContainsString("\$this->assertDatabaseHas('tickets', ['code' => \$ticket->getKey(), 'title' => 'test_title']);", $test);
        $this->assertStringContainsString("Arr::except(\$ticket->fresh()->getAttributes(), ['title', 'updated_at'])", $test);
    }

    #[Test]
    public function a_patched_json_field_is_not_looked_up_in_the_database(): void
    {
        $test = $this->generate([new FieldDefinition(name: 'meta', type: 'json')]);
        $patch = substr($test, (int) strpos($test, 'function test_can_patch_ticket'), (int) strpos($test, 'function test_can_delete_ticket') - (int) strpos($test, 'function test_can_patch_ticket'));

        $this->assertStringContainsString("['meta' => '{\"key\":\"value\"}']", $patch);
        $this->assertStringNotContainsString('assertDatabaseHas', $patch);
    }

    #[Test]
    public function soft_deletes_add_a_restore_and_a_force_delete_test(): void
    {
        $test = $this->generate([new FieldDefinition(name: 'title', type: 'string')], ['soft_deletes' => true]);

        $this->assertStringContainsString('$this->postJson("/api/tickets/{$ticket->getKey()}/restore");', $test);
        $this->assertStringContainsString("\$this->assertNotSoftDeleted('tickets', ['id' => \$ticket->getKey()]);", $test);
        $this->assertStringContainsString('$this->deleteJson("/api/tickets/{$ticket->getKey()}/force-delete");', $test);
        $this->assertStringNotContainsString('restore', $this->generate([new FieldDefinition(name: 'title', type: 'string')]));
    }

    #[Test]
    public function pest_tests_patch_restore_and_force_delete_too(): void
    {
        $test = $this->generate([new FieldDefinition(name: 'title', type: 'string')], ['soft_deletes' => true, 'pest' => true]);

        foreach (["it('patches a ticket'", "it('restores a ticket'", "it('force deletes a ticket'"] as $case) {
            $this->assertStringContainsString($case, $test);
        }
        $this->assertStringContainsString("    \$this->assertDatabaseHas('tickets', ['id' => \$ticket->getKey(), 'title' => 'test_title']);", $test);
    }
}
