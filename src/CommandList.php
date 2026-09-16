<?php

declare(strict_types=1);

namespace MichalSkoula\Console;

/**
 * @see \MichalSkoula\Console\Tests\CommandListTest
 */
class CommandList extends Command
{
    protected string $signature = 'list {keyword?}';

    protected string $description = 'Show available commands';

    public function handle(?string $keyword): void
    {
        $count = 0;
        $maxLen = 0;
        if ($keyword) {
            $commands = $this->getCommandsLike($keyword);
            $this->writeln(PHP_EOL . $this->color(" Here are commands like '{$keyword}': ", 'blue') . PHP_EOL);
        } else {
            $commands = $this->getRegisteredCommands();
            $commands = array_filter($commands, fn($command, $name): bool => empty($command['hidden']) && $name !== '__complete', ARRAY_FILTER_USE_BOTH);
            unset($commands['list']);
            $header = $this->getListHeader();
            if ($header !== null) {
                if ($header['typing_delay'] !== null && $header['typing_delay'] > 0) {
                    $this->writeln('');
                    foreach (preg_split('//u', $header['text'], -1, PREG_SPLIT_NO_EMPTY) as $character) {
                        $this->write($character, $header['color']);
                        usleep($header['typing_delay'] * 1000);
                    }

                    $this->writeln(PHP_EOL);
                } else {
                    $this->writeln(PHP_EOL . $header['text'] . PHP_EOL, $header['color']);
                }
            } else {
                $this->writeln(PHP_EOL . $this->color(' Available Commands: ', 'blue') . PHP_EOL);
            }
        }

        foreach (array_keys($commands) as $name) {
            if (strlen($name) > $maxLen) {
                $maxLen = strlen($name);
            }
        }

        $pad = $maxLen + 3;

        $commandGroups = [];
        foreach ($this->getCommandGroups() as $commandGroup) {
            $commandGroups[$commandGroup['name']] = $commandGroup + [
                'commands' => [],
            ];
        }

        $ungroupedCommands = [];
        foreach ($commands as $name => $command) {
            if ($command['group'] !== null) {
                $commandGroups[$command['group']['name']]['commands'][$name] = $command;
            } else {
                $ungroupedCommands[$name] = $command;
            }
        }

        if ($ungroupedCommands !== []) {
            $commandGroups[] = [
                'name' => $this->getCommandGroups() === [] ? null : 'General',
                'color' => 'dark_gray',
                'commands' => $ungroupedCommands,
            ];
        }

        $groupCount = 0;
        foreach ($commandGroups as $commandGroup) {
            if ($commandGroup['commands'] === []) {
                continue;
            }

            if ($commandGroup['name'] !== null) {
                if ($groupCount > 0) {
                    $this->writeln('');
                }

                $this->writeln($this->color($commandGroup['name'] . ':', $commandGroup['color']));
            }

            foreach ($commandGroup['commands'] as $name => $command) {
                $no = ++$count . '/ ';
                $this->write(str_repeat(' ', 4 - strlen($no)) . $this->color($no, 'dark_gray'));
                $this->write($this->color($name, $command['color']) . str_repeat(' ', $pad - strlen($name)));
                $descriptionLines = preg_split('/\R/', $command['description']) ?: [''];
                $this->writeln(array_shift($descriptionLines));
                foreach ($descriptionLines as $descriptionLine) {
                    $this->writeln(str_repeat(' ', 4 + $pad) . $descriptionLine);
                }
            }

            ++$groupCount;
        }

        $this->writeln('');
        $this->writeln("Type '" . $this->color('<command> --help', 'blue') . "' for usage information");
        $this->writeln("Type '" . $this->color('list', 'blue') . "' to show all commands" . PHP_EOL);
    }
}
