<?php

declare(strict_types=1);

namespace MichalSkoula\Console\Tests;

use InvalidArgumentException;
use MichalSkoula\Console\App;
use MichalSkoula\Console\Color;
use PHPUnit\Framework\TestCase;

final class CommandListTest extends TestCase
{
    public function testListsGroupedCommandsInRegistrationOrderWithoutEmptyRows(): void
    {
        $app = new App(['console', 'list']);
        $app->group('Build', Color::BLUE, function (): void {
            $this->command('bravo', 'Bravo command', function (): void {});
            $this->command('alpha', 'Alpha command', function (): void {});
        });

        $output = $this->listCommands($app);

        $this->assertMatchesRegularExpression('/Build:\n\s*1\/ bravo\s+Bravo command\n\s*2\/ alpha\s+Alpha command/', $output);
        $this->assertStringNotContainsString('General:', $output);
        $this->assertStringNotContainsString('list                  Show available commands', $output);
        $this->assertStringContainsString("\nType '<command> --help' for usage information\nType 'list' to show all commands", $output);
    }

    public function testKeepsGroupRegistrationOrder(): void
    {
        $app = new App(['console', 'list']);
        $app->group('Second', Color::RED, function (): void {
            $this->command('second', 'Second command', function (): void {});
        });
        $app->group('First', Color::BLUE, function (): void {
            $this->command('first', 'First command', function (): void {});
        });

        $output = $this->listCommands($app);

        $this->assertGreaterThan(strpos($output, 'Second:'), strpos($output, 'First:'));
    }

    public function testAlignsMultilineDescriptions(): void
    {
        $app = new App(['console', 'list']);
        $app->command('short', "First line\nSecond line\n  Indented detail", function (): void {});
        $app->command('long-command', 'Long command', function (): void {});

        $output = $this->listCommands($app);
        $lines = explode(PHP_EOL, $output);
        $firstLine = $this->findLine($lines, 'First line');
        $secondLine = $this->findLine($lines, 'Second line');
        $detailLine = $this->findLine($lines, 'Indented detail');

        $this->assertSame(strpos($firstLine, 'First line'), strpos($secondLine, 'Second line'));
        $this->assertSame(strpos($firstLine, 'First line') + 2, strpos($detailLine, 'Indented detail'));
    }

    public function testAlignsMultilineDescriptionInCommandHelp(): void
    {
        $app = new App(['console', 'demo', '--help']);
        $app->command('demo', "First line\nSecond line", function (): void {});

        ob_start();
        $app->run();
        $output = $this->stripColors((string) ob_get_clean());

        $this->assertStringContainsString("\n First line\n Second line\n", $output);
    }

    public function testSupportsHyphenatedOptionNames(): void
    {
        $enabledOptions = [];
        $app = new App(['console', 'demo', '--auth', '--eshop-advanced']);
        $app->command('demo {--auth::Run auth tests} {--eshop-advanced::Run advanced tests}', 'Demo command', function () use (&$enabledOptions): void {
            $enabledOptions = [
                'auth' => $this->option('auth'),
                'eshop-advanced' => $this->option('eshop-advanced'),
            ];
        });

        $app->run();

        $this->assertSame([
            'auth' => true,
            'eshop-advanced' => true,
        ], $enabledOptions);
        $this->assertSame(['--eshop-advanced'], $app->getCompletionSuggestions(['demo', '--eshop']));
    }

    public function testDisplaysOptionDescriptionsInDarkGray(): void
    {
        $app = new App(['console', 'demo', '--help']);
        $app->command('demo {--dry-run::Preview without saving}', 'Demo command', function (): void {});

        ob_start();
        $app->run();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString("\033[1;30mPreview without saving\033[0m", $output);
    }

    public function testDisplaysUnicodeListHeaderAndCommandColor(): void
    {
        $app = new App(['console', 'list']);
        $app->setListHeader("My Console\n█", Color::CYAN);
        $app->command('danger', 'Danger command', function (): void {}, Color::RED);

        ob_start();
        $app->execute('list');
        $output = (string) ob_get_clean();

        $this->assertStringContainsString("My Console\n█", $this->stripColors($output));
        $this->assertStringContainsString("\033[0;31mdanger\033[0m", $output);
    }

    public function testRejectsNegativeHeaderTypingDelay(): void
    {
        $app = new App(['console', 'list']);

        $this->expectException(InvalidArgumentException::class);
        $app->setListHeader('My Console', Color::CYAN, -1);
    }

    private function listCommands(App $app): string
    {
        ob_start();
        $app->execute('list');

        return $this->stripColors((string) ob_get_clean());
    }

    private function stripColors(string $output): string
    {
        return (string) preg_replace('/\033\[[\d;]*m/', '', $output);
    }

    private function findLine(array $lines, string $text): string
    {
        foreach ($lines as $line) {
            if (str_contains($line, $text)) {
                return $line;
            }
        }

        self::fail("Line containing '{$text}' not found");
    }
}
