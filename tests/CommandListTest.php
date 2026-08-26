<?php

declare(strict_types=1);

namespace MichalSkoula\Console\Tests;

use InvalidArgumentException;
use MichalSkoula\Console\App;
use PHPUnit\Framework\TestCase;

class CommandListTest extends TestCase
{
    public function test_lists_grouped_commands_in_registration_order_without_empty_rows(): void
    {
        $app = new App(['console', 'list']);
        $app->group('Build', 'blue', function (): void {
            $this->command('bravo', 'Bravo command', function (): void {});
            $this->command('alpha', 'Alpha command', function (): void {});
        });

        $output = $this->_list_commands($app);

        self::assertMatchesRegularExpression('/Build:\n\s*1\/ bravo\s+Bravo command\n\s*2\/ alpha\s+Alpha command/', $output);
        self::assertStringNotContainsString('General:', $output);
        self::assertStringNotContainsString('list                  Show available commands', $output);
        self::assertStringContainsString("\nType '<command> --help' for usage information\nType 'list' to show all commands", $output);
    }

    public function test_keeps_group_registration_order(): void
    {
        $app = new App(['console', 'list']);
        $app->group('Second', 'red', function (): void {
            $this->command('second', 'Second command', function (): void {});
        });
        $app->group('First', 'blue', function (): void {
            $this->command('first', 'First command', function (): void {});
        });

        $output = $this->_list_commands($app);

        self::assertGreaterThan(strpos($output, 'Second:'), strpos($output, 'First:'));
    }

    public function test_displays_unicode_list_header_and_command_color(): void
    {
        $app = new App(['console', 'list']);
        $app->setListHeader("My Console\n█", 'cyan');
        $app->command('danger', 'Danger command', function (): void {}, 'red');

        ob_start();
        $app->execute('list');
        $output = (string) ob_get_clean();

        self::assertStringContainsString("My Console\n█", $this->_strip_colors($output));
        self::assertStringContainsString("\033[0;31mdanger\033[0m", $output);
    }

    public function test_rejects_negative_header_typing_delay(): void
    {
        $app = new App(['console', 'list']);

        $this->expectException(InvalidArgumentException::class);
        $app->setListHeader('My Console', 'cyan', -1);
    }

    private function _list_commands(App $app): string
    {
        ob_start();
        $app->execute('list');

        return $this->_strip_colors((string) ob_get_clean());
    }

    private function _strip_colors(string $output): string
    {
        return (string) preg_replace('/\033\[[\d;]*m/', '', $output);
    }
}
