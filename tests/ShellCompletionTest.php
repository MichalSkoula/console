<?php

declare(strict_types=1);

namespace MichalSkoula\Console\Tests;

use MichalSkoula\Console\ShellCompletion;
use PHPUnit\Framework\TestCase;

class ShellCompletionTest extends TestCase
{
    public function test_completes_namespaced_command_without_unrelated_matches(): void
    {
        $completion = new ShellCompletion('./cli');

        self::assertSame(
            ['maintenance:on', 'maintenance:off'],
            $completion->getSuggestions($this->_commands(), ['maintenance:o'])
        );
    }

    public function test_completes_declared_options_for_selected_command(): void
    {
        $completion = new ShellCompletion('./cli');

        self::assertSame(
            ['--force'],
            $completion->getSuggestions($this->_commands(), ['deploy', '--f'])
        );
    }

    public function test_completes_configured_argument_values(): void
    {
        $completion = new ShellCompletion('./cli');
        $completion->setValues('deploy', 'environment', ['staging', 'production']);

        self::assertSame(
            ['production'],
            $completion->getSuggestions($this->_commands(), ['deploy', 'p'])
        );
    }

    public function test_bash_script_preserves_namespaced_command_prefix(): void
    {
        $completion = new ShellCompletion('./cli');
        $script = $completion->getScript();

        self::assertNotNull($script);
        self::assertStringContainsString('prefix="${current%:*}:"', $script);
        self::assertStringContainsString('suggestion="${suggestion#"$prefix"}"', $script);
        self::assertStringNotContainsString('complete -o default', $script);
    }

    private function _commands(): array
    {
        return [
            'maintenance:on' => $this->_command(),
            'maintenance:off' => $this->_command(),
            'opcache' => $this->_command(),
            'deploy' => $this->_command(
                ['environment' => []],
                ['force' => ['alias' => 'f']]
            ),
            '__complete' => $this->_command(hidden: true),
        ];
    }

    private function _command(array $arguments = [], array $options = [], bool $hidden = false): array
    {
        return [
            'args' => $arguments,
            'options' => $options,
            'hidden' => $hidden,
        ];
    }
}
