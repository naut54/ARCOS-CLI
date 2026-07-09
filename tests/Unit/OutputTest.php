<?php

declare(strict_types=1);

namespace Arcos\Cli\Tests\Unit;

use Arcos\Cli\IO\Output;
use PHPUnit\Framework\TestCase;

class OutputTest extends TestCase
{
    private function capture(callable $fn): string
    {
        ob_start();
        $fn();

        return ob_get_clean();
    }

    public function test_line_prints_the_text_verbatim(): void
    {
        $output = $this->capture(fn() => Output::line('hello'));

        $this->assertSame("hello" . PHP_EOL, $output);
    }

    public function test_success_contains_the_message(): void
    {
        $output = $this->capture(fn() => Output::success('Created: foo.php'));

        $this->assertStringContainsString('Created: foo.php', $output);
    }

    public function test_error_contains_the_message(): void
    {
        $output = $this->capture(fn() => Output::error('Something broke'));

        $this->assertStringContainsString('Something broke', $output);
    }

    public function test_warn_contains_the_message(): void
    {
        $output = $this->capture(fn() => Output::warn('Careful'));

        $this->assertStringContainsString('Careful', $output);
    }

    public function test_info_contains_the_message(): void
    {
        $output = $this->capture(fn() => Output::info('FYI'));

        $this->assertStringContainsString('FYI', $output);
    }

    public function test_header_contains_the_text(): void
    {
        $output = $this->capture(fn() => Output::header('Section'));

        $this->assertStringContainsString('Section', $output);
    }

    public function test_snippet_contains_the_label_and_indented_code(): void
    {
        $output = $this->capture(fn() => Output::snippet('Add this:', "line one\nline two"));

        $this->assertStringContainsString('Add this:', $output);
        $this->assertStringContainsString('    line one', $output);
        $this->assertStringContainsString('    line two', $output);
    }
}
