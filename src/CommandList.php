<?php

namespace MichalSkoula\Console;

class CommandList extends Command
{

    protected $signature = "list {keyword?}";

    protected $description = "Show available commands";

    public function handle($keyword)
    {
        $count = 0;
        $maxLen = 0;
        if ($keyword) {
            $commands = $this->getCommandsLike($keyword);
            $this->writeln(PHP_EOL.$this->color(" Here are commands like '{$keyword}': ", 'blue').PHP_EOL);
        } else {
            $commands = $this->getRegisteredCommands();
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
                $this->writeln(PHP_EOL.$this->color(" Available Commands: ", 'blue').PHP_EOL);
            }
        }

        foreach(array_keys($commands) as $name) {
            if (strlen($name ) > $maxLen) $maxLen = strlen($name);
        }
        $pad = $maxLen + 3;

        $commandGroups = [];
        foreach ($this->getCommandGroups() as $group) {
            $commandGroups[$group['name']] = $group + ['commands' => []];
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
                'commands' => $ungroupedCommands
            ];
        }

        $groupCount = 0;
        foreach ($commandGroups as $group) {
            if ($group['commands'] === []) {
                continue;
            }

            if ($group['name'] !== null) {
                if ($groupCount > 0) {
                    $this->writeln('');
                }
                $this->writeln($this->color($group['name'] . ':', $group['color']));
            }

            foreach ($group['commands'] as $name => $command) {
                $no = ++$count.'/ ';
                $this->write(str_repeat(' ', 4 - strlen($no)).$this->color($no, 'dark_gray'));
                $this->write($this->color($name, $command['color']).str_repeat(' ', $pad - strlen($name)));
                $this->writeln($command['description']);
            }

            ++$groupCount;
        }

        $this->writeln('');
        $this->writeln("Type '" . $this->color('<command> --help', 'blue') . "' for usage information");
        $this->writeln("Type '" . $this->color('list', 'blue') . "' to show all commands" . PHP_EOL);
    }

}
