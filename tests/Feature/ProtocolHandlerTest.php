<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use nameless\CodeGenerator\Support\ProtocolHandler;
use nameless\CodeGenerator\Support\WorkspaceFactory;
use nameless\CodeGenerator\Tests\AssertsProtocol;
use PHPUnit\Framework\Attributes\Test;

class ProtocolHandlerTest extends GeneratorTestCase
{
    use AssertsProtocol;

    private const BOOK = ['entities' => ['Book' => ['fields' => ['title' => 'string']]]];

    protected function setUp(): void
    {
        parent::setUp();

        $this->instance(WorkspaceFactory::class, new WorkspaceFactory(fn (): int => 1767225600));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function rpc(ProtocolHandler $handler, string $method, array $params = [], int $id = 1): array
    {
        $response = $handler->handle((string) json_encode(['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params]));

        $this->assertNotNull($response);
        $this->assertStringNotContainsString("\n", $response);

        return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function the_handshake_describes_the_package(): void
    {
        $response = $this->rpc(app(ProtocolHandler::class), 'handshake', ['client' => 'test', 'clientVersion' => '0']);

        $this->assertSame(1, $response['id']);
        $this->assertMatchesProtocol($response['result'], 'handshakeResult');
    }

    #[Test]
    public function a_plan_returns_every_file_and_writes_nothing(): void
    {
        $response = $this->rpc(app(ProtocolHandler::class), 'plan', ['schema' => self::BOOK]);

        $this->assertMatchesProtocol($response['result'], 'planResult');
        $files = $response['result']['files'] ?? null;
        $this->assertIsArray($files);
        $model = collect($files)->firstWhere('path', 'app/Models/Book.php');
        $this->assertIsArray($model);
        $this->assertStringContainsString('class Book', $model['content']);
        $this->assertFileDoesNotExist(app_path('Models/Book.php'));
    }

    #[Test]
    public function a_plan_warns_about_unknown_field_types(): void
    {
        $response = $this->rpc(app(ProtocolHandler::class), 'plan', ['schema' => ['entities' => ['Book' => ['fields' => ['title' => 'strng']]]]]);

        $this->assertMatchesProtocol($response['result'], 'planResult');
        $this->assertContains('unknown_field_type', array_column($response['result']['warnings'], 'code'));
    }

    #[Test]
    public function two_identical_plans_give_the_same_answer(): void
    {
        $handler = app(ProtocolHandler::class);

        $first = $this->rpc($handler, 'plan', ['schema' => self::BOOK, 'flags' => ['auth' => true]], 1);
        $second = $this->rpc($handler, 'plan', ['schema' => self::BOOK, 'flags' => ['auth' => true]], 2);

        $this->assertSame($first['result'], $second['result']);
    }

    #[Test]
    public function an_invalid_schema_comes_back_as_a_coded_error(): void
    {
        $response = $this->rpc(app(ProtocolHandler::class), 'plan', ['schema' => ['entities' => []]]);

        $this->assertSame(-32000, $response['error']['code']);
        $this->assertSame('invalid_schema', $response['error']['data']['code']);
        $this->assertMatchesProtocol($response['error']['data'], 'error');
    }

    #[Test]
    public function unknown_methods_and_broken_lines_are_rejected(): void
    {
        $handler = app(ProtocolHandler::class);

        $unknown = $this->rpc($handler, 'nope');
        $broken = json_decode((string) $handler->handle('not json'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(-32601, $unknown['error']['code']);
        $this->assertSame('unsupported_method', $unknown['error']['data']['code']);
        $this->assertSame(-32700, $broken['error']['code']);
        $this->assertNull($broken['id']);
    }

    #[Test]
    public function notifications_get_no_answer_and_shutdown_stops_the_handler(): void
    {
        $handler = app(ProtocolHandler::class);

        $this->assertNull($handler->handle('{"jsonrpc":"2.0","method":"handshake"}'));
        $this->assertFalse($handler->stopped());

        $response = $this->rpc($handler, 'shutdown');

        $this->assertArrayHasKey('result', $response);
        $this->assertNull($response['result']);
        $this->assertTrue($handler->stopped());
    }
}
