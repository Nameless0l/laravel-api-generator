<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Unit;

use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\Support\Protocol;
use nameless\CodeGenerator\Tests\AssertsProtocol;
use nameless\CodeGenerator\Tests\TestCase;
use nameless\CodeGenerator\ValueObjects\FileChange;
use PHPUnit\Framework\Attributes\Test;

class ProtocolTest extends TestCase
{
    use AssertsProtocol;

    #[Test]
    public function errors_carry_a_stable_code(): void
    {
        $schema = CodeGeneratorException::invalidSchema('api-schema.yaml', 'missing entities');
        $wrapped = CodeGeneratorException::generationFailed('API', 'boom', CodeGeneratorException::fileNotFound('stubs/model.stub'));

        $this->assertSame('invalid_schema', Protocol::error($schema)['code']);
        $this->assertArrayHasKey('hint', Protocol::error($schema));
        $this->assertSame('file_not_found', Protocol::error($wrapped)['code']);
        $this->assertSame('unexpected_error', Protocol::error(new \RuntimeException('disk full'))['code']);
        $this->assertMatchesProtocol(Protocol::error($schema), 'error');
    }

    #[Test]
    public function a_dry_run_document_carries_the_file_contents(): void
    {
        $changes = [new FileChange('app/Models/Post.php', 'Model', 'Post', FileChange::CREATE, "<?php\n\nclass Post {}\n")];

        $dryRun = Protocol::planDocument($changes, [], true);
        $realRun = Protocol::planDocument($changes, [['code' => 'x', 'message' => 'y']], false);

        $this->assertMatchesProtocol($dryRun, 'planDocument');
        $this->assertMatchesProtocol($realRun, 'planDocument');
        $this->assertSame("<?php\n\nclass Post {}\n", $dryRun['files'][0]['content']);
        $this->assertArrayNotHasKey('content', $realRun['files'][0]);
    }

    #[Test]
    public function the_handshake_describes_the_package(): void
    {
        $handshake = Protocol::handshake();

        $this->assertMatchesProtocol($handshake, 'handshakeResult');
        $this->assertSame(1, $handshake['protocol']);
        $this->assertContains('string', $handshake['capabilities']['fieldTypes']);
        $this->assertContains('belongsToMany', $handshake['capabilities']['relationTypes']);
    }

    #[Test]
    public function encoded_documents_fit_on_one_line(): void
    {
        $changes = [new FileChange('app/Models/Post.php', 'Model', 'Post', FileChange::CREATE, "line one\nline two\n")];

        $this->assertStringNotContainsString("\n", Protocol::encode(Protocol::planDocument($changes, [], true)));
    }

    #[Test]
    public function an_error_document_validates(): void
    {
        $this->assertMatchesProtocol(Protocol::errorDocument(CodeGeneratorException::invalidRequest('nope'), true), 'planDocument');
    }
}
