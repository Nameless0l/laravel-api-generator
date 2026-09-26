<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Support;

use nameless\CodeGenerator\Contracts\LineHandler;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\Services\GenerationPlanner;
use nameless\CodeGenerator\ValueObjects\GenerationRequest;

/**
 * JSON-RPC 2.0 over single lines. Read-only: plans are never applied.
 */
final class ProtocolHandler implements LineHandler
{
    private const METHODS = ['handshake', 'plan', 'shutdown'];

    private bool $stopped = false;

    public function __construct(
        private readonly GenerationPlanner $planner,
        private readonly SchemaParser $schemaParser,
    ) {}

    public function stopped(): bool
    {
        return $this->stopped;
    }

    public function handle(string $line): ?string
    {
        try {
            $message = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->error(null, -32700, ['code' => 'invalid_request', 'message' => 'The request is not valid JSON.']);
        }

        if (! is_array($message) || ($message['jsonrpc'] ?? null) !== '2.0' || ! is_string($message['method'] ?? null)) {
            return $this->error(null, -32600, ['code' => 'invalid_request', 'message' => 'Expected a JSON-RPC 2.0 request.']);
        }

        $id = $message['id'] ?? null;
        $method = $message['method'];
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        if (! in_array($method, self::METHODS, true)) {
            return $this->error($id, -32601, ['code' => 'unsupported_method', 'message' => "Unknown method {$method}."]);
        }

        try {
            $result = match ($method) {
                'handshake' => Protocol::handshake(),
                'plan' => $this->plan($params),
                default => $this->shutdown(),
            };
        } catch (\Throwable $e) {
            return $this->error($id, -32000, Protocol::error($e));
        }

        return $id === null ? null : Protocol::encode(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
    }

    /**
     * @param  array<mixed>  $params
     * @return array<string, mixed>
     */
    private function plan(array $params): array
    {
        $schema = $params['schema'] ?? null;
        if (! is_array($schema)) {
            throw CodeGeneratorException::invalidRequest('plan expects a "schema" object in the api-schema format.');
        }

        $flags = is_array($params['flags'] ?? null) ? $params['flags'] : [];
        $only = $flags['only'] ?? null;

        $plan = $this->planner->plan(new GenerationRequest(
            entities: $this->schemaParser->parseArray($schema, [], 'request'),
            auth: (bool) ($flags['auth'] ?? false),
            postman: (bool) ($flags['postman'] ?? false),
            only: is_array($only) && $only !== [] ? array_values(array_map('strval', $only)) : null,
        ));

        return ['files' => Protocol::files($plan->changes(), true), 'warnings' => array_values($plan->warnings)];
    }

    private function shutdown(): null
    {
        $this->stopped = true;

        return null;
    }

    /**
     * @param  array{code: string, message: string, hint?: string}  $data
     */
    private function error(mixed $id, int $code, array $data): string
    {
        return Protocol::encode([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => ['code' => $code, 'message' => $data['message'], 'data' => $data],
        ]);
    }
}
