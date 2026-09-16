<?php

declare(strict_types=1);

namespace MichalSkoula\Console\Concerns;

use RuntimeException;

trait InputUtils
{
    protected string $questionSuffix = PHP_EOL . '> ';

    /**
     * Asking question
     */
    public function ask(string $question, mixed $default = null): mixed
    {
        if ($default) {
            $question = $question . ' ' . $this->color("[{$default}]", 'green');
        }

        $this->write($question . $this->questionSuffix, 'blue');

        $handle = fopen('php://stdin', 'r');
        if ($handle === false) {
            throw new RuntimeException('Unable to read input.');
        }

        $value = fgets($handle);
        fclose($handle);
        $answer = $value === false ? '' : trim($value);
        return $answer ?: $default;
    }

    /**
     * Asking secret question
     */
    public function askSecret(string $question, mixed $default = null): mixed
    {
        if ($default) {
            $question = $question . ' ' . $this->color("[{$default}]", 'green');
        }

        $this->write($question . $this->questionSuffix);

        if ($this->isWindows()) {
            throw new RuntimeException('Secret input is not supported on Windows');
        }

        if ($this->hasSttyAvailable()) {
            $sttyMode = shell_exec('stty -g');
            shell_exec('stty -echo');
            $handle = fopen('php://stdin', 'r');
            if ($handle === false) {
                throw new RuntimeException('Unable to read input.');
            }

            $value = fgets($handle, 4096);
            shell_exec(sprintf('stty %s', (string) $sttyMode));
            fclose($handle);
            if ($value === false) {
                throw new RuntimeException('Aborted');
            }

            $value = trim($value);
            $this->writeln('');
            return $value ?: $default;
        }

        if (false !== $shell = $this->getShell()) {
            $readCmd = $shell === 'csh' ? 'set mypassword = $<' : 'read -r mypassword';
            $command = sprintf("/usr/bin/env %s -c 'stty -echo; %s; stty echo; echo \$mypassword'", $shell, $readCmd);
            $value = rtrim((string) shell_exec($command));
            $this->writeln('');
            return $value ?: $default;
        }

        throw new RuntimeException('Unable to hide the response.');
    }

    /**
     * Input confirmation
     */
    public function confirm(string $question, bool $default = false): bool
    {
        $availableAnswers = [
            'yes' => true,
            'no' => false,
            'y' => true,
            'n' => false,
        ];

        $result = null;
        do {
            if ($default) {
                $suffix = $this->color('[', 'dark_gray') . $this->color('Y', 'green') . $this->color('/n]', 'dark_gray');
            } else {
                $suffix = $this->color('[y/', 'dark_gray') . $this->color('N', 'green') . $this->color(']', 'dark_gray');
            }

            $answer = $this->ask($question . ' ' . $suffix) ?: ($default ? 'y' : 'n');

            if (! isset($availableAnswers[$answer])) {
                $this->writeln('Please type: y, n, yes, or no.', 'red');
            } else {
                $result = $availableAnswers[$answer];
            }
        } while ($result === null);

        return $availableAnswers[$answer];
    }
}
