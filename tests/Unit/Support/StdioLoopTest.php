<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Unit\Support;

use nameless\CodeGenerator\Contracts\LineHandler;
use nameless\CodeGenerator\Support\StdioLoop;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StdioLoopTest extends TestCase
{
    /**
     * @return resource
     */
    private function stream(string $content = '')
    {
        $stream = fopen('php://memory', 'r+');
        $this->assertNotFalse($stream);
        fwrite($stream, $content);
        rewind($stream);

        return $stream;
    }

    /**
     * @param  resource  $stream
     */
    private function contents($stream): string
    {
        rewind($stream);

        return (string) stream_get_contents($stream);
    }

    #[Test]
    public function stray_output_goes_to_stderr_and_answers_to_stdout(): void
    {
        $handler = new class implements LineHandler
        {
            private bool $stopped = false;

            public function handle(string $line): string
            {
                echo 'noise;';
                $this->stopped = $line === 'stop';

                return '{"echo":"'.$line.'"}';
            }

            public function stopped(): bool
            {
                return $this->stopped;
            }
        };
        $input = $this->stream("first\n\nstop\nnever read\n");
        $output = $this->stream();
        $errors = $this->stream();

        (new StdioLoop($handler))->run($input, $output, $errors);

        $this->assertSame("{\"echo\":\"first\"}\n{\"echo\":\"stop\"}\n", $this->contents($output));
        $this->assertSame('noise;noise;', $this->contents($errors));
    }
}
