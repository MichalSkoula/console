<?php

declare(strict_types=1);

namespace MichalSkoula\Console\Tests;

use MichalSkoula\Console\ShellCompletion;
use PHPUnit\Framework\TestCase;

final class ShellCompletionTest extends TestCase
{
    public function testCompletesNamespacedCommandWithoutUnrelatedMatches(): void
    {
        $shellCompletion = new ShellCompletion('./cli');

        $this->assertSame(['maintenance:on', 'maintenance:off'], $shellCompletion->getSuggestions($this->commands(), ['maintenance:o']));
    }

    public function testCompletesDeclaredOptionsForSelectedCommand(): void
    {
        $shellCompletion = new ShellCompletion('./cli');

        $this->assertSame(['--force'], $shellCompletion->getSuggestions($this->commands(), ['deploy', '--f']));
    }

    public function testCompletesConfiguredArgumentValues(): void
    {
        $shellCompletion = new ShellCompletion('./cli');
        $shellCompletion->setValues('deploy', 'environment', ['staging', 'production']);

        $this->assertSame(['production'], $shellCompletion->getSuggestions($this->commands(), ['deploy', 'p']));
    }

    public function testBashScriptPreservesNamespacedCommandPrefix(): void
    {
        $shellCompletion = new ShellCompletion('./cli');
        $script = $shellCompletion->getScript();

        $this->assertNotNull($script);
        $this->assertStringContainsString('prefix="${current%:*}:"', $script);
        $this->assertStringContainsString('suggestion="${suggestion#"$prefix"}"', $script);
        $this->assertStringNotContainsString('complete -o default', $script);
    }

    /**
     * @return array<string, mixed[]>
     */
    private function commands(): array
    {
        return [
            'maintenance:on' => $this->command(),
            'maintenance:off' => $this->command(),
            'opcache' => $this->command(),
            'deploy' => $this->command(
                [
                    'environment' => [],
                ],
                [
                    'force' => [
                        'alias' => 'f',
                    ],
                ]
            ),
            '__complete' => $this->command(hidden: true),
        ];
    }

    /**
     * @return array<string, bool|mixed[]>
     */
    private function command(array $arguments = [], array $options = [], bool $hidden = false): array
    {
        return [
            'args' => $arguments,
            'options' => $options,
            'hidden' => $hidden,
        ];
    }
}
