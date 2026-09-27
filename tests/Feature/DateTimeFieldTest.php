<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\Test;

class DateTimeFieldTest extends GeneratorTestCase
{
    protected array $generatedEntities = ['Meeting'];

    protected array $generatedTables = ['meetings'];

    #[Test]
    public function date_and_time_fields_flow_through_the_generated_files(): void
    {
        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', [
            'name' => 'Meeting',
            '--fields' => 'day:date,starts_at:time,held_at:datetime',
        ]);
        $result->assertSuccessful();
        $result->run();

        $migration = (string) file_get_contents($this->firstMigrationFor('meetings'));
        $this->assertStringContainsString("\$table->date('day');", $migration);
        $this->assertStringContainsString("\$table->time('starts_at');", $migration);
        $this->assertStringContainsString("\$table->dateTime('held_at');", $migration);

        $request = (string) file_get_contents(app_path('Http/Requests/StoreMeetingRequest.php'));
        $this->assertStringContainsString("'starts_at' => 'required|date_format:H:i,H:i:s',", $request);

        $factory = (string) file_get_contents(database_path('factories/MeetingFactory.php'));
        $this->assertStringContainsString("'starts_at' => fake()->time()", $factory);

        $test = (string) file_get_contents(base_path('tests/Feature/MeetingControllerTest.php'));
        $this->assertStringContainsString("'starts_at' => '10:30:00',", $test);
    }
}
