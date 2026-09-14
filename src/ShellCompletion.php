<?php

namespace MichalSkoula\Console;

final class ShellCompletion
{
    private string $filename;

    private array $values = [];

    public function __construct(string $filename)
    {
        $this->filename = $filename;
    }

    public function setValues(string $command, string $argument, array $values): void
    {
        $this->values[$command][$argument] = array_values($values);
    }

    public function getSuggestions(array $commands, array $words = []): array
    {
        $commandName = (string) ($words[0] ?? '');
        $currentWord = $words === [] ? '' : (string) end($words);

        if (count($words) <= 1) {
            return $this->_filter_values(array_keys($commands), $currentWord, $commands);
        }

        if (!isset($commands[$commandName])) {
            return [];
        }

        $command = $commands[$commandName];
        if (str_starts_with($currentWord, '-')) {
            $options = [];
            foreach ($command['options'] as $name => $option) {
                $options[] = '--' . $name;
                if ($option['alias'] !== null) {
                    $options[] = '-' . $option['alias'];
                }
            }

            return $this->_filter_values($options, $currentWord);
        }

        $argumentNames = array_keys($command['args']);
        $argumentPosition = count($words) - 2;
        $argumentName = $argumentNames[$argumentPosition] ?? null;
        if ($argumentName === null) {
            return [];
        }

        return $this->_filter_values(
            $this->values[$commandName][$argumentName] ?? [],
            $currentWord
        );
    }

    public function getScript(string $shell = 'bash', ?string $command = null): ?string
    {
        if (strtolower($shell) !== 'bash') {
            return null;
        }

        if ($command === null || $command === '') {
            $command = $this->filename;
        }

        $safeCommand = (string) preg_replace('/[^a-zA-Z0-9_]/', '_', $command);
        $functionName = 'php_console_completion_' . $safeCommand;
        $escapedCommand = escapeshellarg($command);

        return sprintf(<<<'SCRIPT'
_%1$s() {
  local input="${COMP_LINE:0:COMP_POINT}"
  local -a words
  read -r -a words <<< "$input"

  if [[ "$input" == *[[:space:]] ]]; then
    words+=("")
  fi

  local current="${words[${#words[@]}-1]}"
  local prefix=""
  if [[ "$current" == *:* ]]; then
    prefix="${current%%:*}:"
  fi

  local suggestion
  COMPREPLY=()
  while IFS= read -r suggestion; do
    if [[ -n "$prefix" ]]; then
      suggestion="${suggestion#"$prefix"}"
    fi
    COMPREPLY+=("$suggestion")
  done < <( %2$s __complete "${words[@]:1}" )
}
complete -F _%1$s %2$s
SCRIPT, $functionName, $escapedCommand);
    }

    private function _filter_values(array $values, string $prefix, array $commands = []): array
    {
        $suggestions = [];

        foreach ($values as $value) {
            $value = (string) $value;
            if (isset($commands[$value]) && !empty($commands[$value]['hidden'])) {
                continue;
            }
            if ($prefix === '' || str_starts_with($value, $prefix)) {
                $suggestions[] = $value;
            }
        }

        return $suggestions;
    }
}
