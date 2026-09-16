<?php

declare(strict_types=1);

namespace MichalSkoula\Console;

use Closure;
use Exception;
use InvalidArgumentException;

class App
{
    use Concerns\InputUtils;

    protected static ?bool $stty = null;

    protected static string|false|null $shell = null;

    protected ?string $filename = null;

    protected ?string $command = null;

    protected array $arguments = [];

    protected array $options = [];

    protected array $optionsAlias = [];

    protected array $commands = [];

    protected array $commandGroups = [];

    protected ?array $currentCommandGroup = null;

    protected ?string $listHeader = null;

    protected ?string $listHeaderColor = null;

    protected ?int $listHeaderTypingDelay = null;

    protected ShellCompletion $shellCompletion;

    protected array $resolvedOptions = [];

    protected array $foregroundColors = [
        'black' => '0;30',
        'dark_gray' => '1;30',
        'blue' => '0;34',
        'light_blue' => '1;34',
        'green' => '0;32',
        'light_green' => '1;32',
        'cyan' => '0;36',
        'light_cyan' => '1;36',
        'red' => '0;31',
        'light_red' => '1;31',
        'purple' => '0;35',
        'light_purple' => '1;35',
        'brown' => '0;33',
        'yellow' => '1;33',
        'light_gray' => '0;37',
        'white' => '1;37',
    ];

    protected array $backgroundColors = [
        'black' => '40',
        'red' => '41',
        'green' => '42',
        'yellow' => '43',
        'blue' => '44',
        'magenta' => '45',
        'cyan' => '46',
        'light_gray' => '47',
    ];

    public function __construct(?array $argv = null)
    {
        $argv ??= $GLOBALS['argv'];

        [$this->filename, $this->command, $this->arguments, $this->options, $this->optionsAlias] = $this->parseArgv($argv);

        $this->shellCompletion = new ShellCompletion((string) $this->filename);
        $this->register(new CommandList());
        $this->registerCompletionCommand();
        $this->registerCompletionGenerator();
    }

    /**
     * Register command
     */
    public function register(Command $command): void
    {
        [$commandName, $args, $options] = $this->parseCommand($command->getSignature());

        if (! $commandName) {
            $class = $command::class;
            throw new InvalidArgumentException("Command '{$class}' must have a name defined in signature");
        }

        if (! method_exists($command, 'handle')) {
            $class = $command::class;
            throw new InvalidArgumentException("Command '{$class}' must have method handle");
        }

        $command->defineApp($this);

        $this->commands[$commandName] = [
            'handler' => [$command, 'handle'],
            'description' => $command->getDescription(),
            'args' => $args,
            'options' => $options,
            'group' => $this->currentCommandGroup,
            'color' => 'green',
            'hidden' => false,
        ];
    }

    /**
     * Register closure command
     *
     * @param string $signature     command signature
     * @param string $description   command description
     * @param Closure $handler      command handler
     */
    public function command(string $signature, string $description, Closure $handler, ?string $color = null): void
    {
        [$commandName, $args, $options] = $this->parseCommand($signature);

        $this->commands[$commandName] = [
            'handler' => $handler,
            'description' => $description,
            'args' => $args,
            'options' => $options,
            'group' => $this->currentCommandGroup,
            'color' => $color ?: 'green',
            'hidden' => false,
        ];
    }

    /**
     * Register internal completion command.
     */
    protected function registerCompletionCommand(): void
    {
        $this->registerInternalCommand('__complete {words*}', 'Internal command for shell completion', function (array $words = []): void {
            $commands = $this->getCompletionSuggestions($words);
            foreach ($commands as $command) {
                $this->writeln($command);
            }
        }, 'dark_gray', true);
    }

    /**
     * Register completion script generator command.
     */
    protected function registerCompletionGenerator(): void
    {
        $this->registerInternalCommand(
            'completion {shell=bash} {command?}',
            'Generate shell completion script',
            function (string $shell = 'bash', ?string $command = null): void {
                $script = $this->getCompletionScript($shell, $command);
                if ($script === null) {
                    $this->error("Unsupported shell '{$shell}'");
                    return;
                }

                $this->writeln($script);
            },
            'dark_gray',
            true
        );
    }

    /**
     * Register a framework command without passing through a consumer's
     * overridden command() method.
     */
    protected function registerInternalCommand(
        string $signature,
        string $description,
        Closure $handler,
        ?string $color = null,
        bool $hidden = false
    ): void {
        [$commandName, $args, $options] = $this->parseCommand($signature);

        $this->commands[$commandName] = [
            'handler' => $handler,
            'description' => $description,
            'args' => $args,
            'options' => $options,
            'group' => null,
            'color' => $color ?: 'green',
            'hidden' => $hidden,
        ];
    }

    public function group(string $name, string $color, Closure $handler): void
    {
        $this->commandGroups[$name] = [
            'name' => $name,
            'color' => $color,
        ];

        $previousGroup = $this->currentCommandGroup;
        $this->currentCommandGroup = $this->commandGroups[$name];

        try {
            $handler->call($this);
        } finally {
            $this->currentCommandGroup = $previousGroup;
        }
    }

    public function getRegisteredCommands(): array
    {
        return $this->commands;
    }

    public function getCommandGroups(): array
    {
        return $this->commandGroups;
    }

    /**
     * Configure the allowed shell-completion values for a command argument.
     */
    public function setCompletionValues(string $command, string $argument, array $values): void
    {
        $this->shellCompletion->setValues($command, $argument, $values);
    }

    public function setListHeader(string $header, ?string $color = null, ?int $typingDelay = null): void
    {
        if ($typingDelay !== null && $typingDelay < 0) {
            throw new InvalidArgumentException('List header typing delay must not be negative');
        }

        $this->listHeader = $header;
        $this->listHeaderColor = $color;
        $this->listHeaderTypingDelay = $typingDelay;
    }

    public function getListHeader(): ?array
    {
        if ($this->listHeader === null) {
            return null;
        }

        return [
            'text' => $this->listHeader,
            'color' => $this->listHeaderColor,
            'typing_delay' => $this->listHeaderTypingDelay,
        ];
    }

    /**
     * Get commands like given keyword
     */
    public function getCommandsLike(string $keyword): array
    {
        $regex = preg_quote($keyword);
        $commands = $this->getRegisteredCommands();
        $matchedCommands = [];
        foreach ($commands as $name => $command) {
            if (! empty($command['hidden'])) {
                continue;
            }

            if ((bool) preg_match('/' . $regex . '/', $name)) {
                $matchedCommands[$name] = $command;
            }
        }

        return $matchedCommands;
    }

    /**
     * Get command names for tab completion.
     *
     * @param array $words Command words, excluding the executable name.
     */
    public function getCompletionSuggestions(array $words = []): array
    {
        return $this->shellCompletion->getSuggestions($this->commands, $words);
    }

    /**
     * Get shell completion script.
     */
    public function getCompletionScript(string $shell = 'bash', ?string $command = null): ?string
    {
        return $this->shellCompletion->getScript($shell, $command);
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Run app
     */
    public function run(): void
    {
        $this->execute($this->command);
    }

    /**
     * Execute command
     *
     * @param string $command command name
     */
    public function execute(?string $command): void
    {
        if (! $command) {
            $command = 'list';
        }

        if (! isset($this->commands[$command])) {
            $this->showCommandsLike($command);
            return;
        }

        if (array_key_exists('help', $this->options) || array_key_exists('h', $this->optionsAlias)) {
            $this->showHelp($command);
            return;
        }

        try {
            $handler = $this->commands[$command]['handler'];
            $arguments = $this->validateAndResolveArguments($command);
            $this->validateAndResolveOptions($command);

            if ($handler instanceof \Closure) {
                $handler = $handler->bindTo($this);
            }

            call_user_func_array($handler, $arguments);
        } catch (Exception $exception) {
            $this->handleError($exception);
        }
    }

    /**
     * Get option by given key
     */
    public function option(string $key): mixed
    {
        return $this->resolvedOptions[$key] ?? null;
    }

    /**
     * Write text
     */
    public function write(string $message, ?string $fgColor = null, ?string $bgColor = null): void
    {
        if ($fgColor || $bgColor) {
            $message = $this->color($message, $fgColor, $bgColor);
        }

        print $message;
    }

    /**
     * Write text line
     */
    public function writeln(string $message, ?string $fgColor = null, ?string $bgColor = null): void
    {
        $this->write($message . PHP_EOL, $fgColor, $bgColor);
    }

    /**
     * Write error message
     */
    public function error(string $message, bool $exit = true): void
    {
        $this->writeln($message, 'red');
        if ($exit) {
            exit();
        }
    }

    /**
     * Coloring text
     */
    public function color(string $text, ?string $fgColor, ?string $bgColor = null): string
    {
        if ($this->isWindows()) {
            return $text;
        }

        $coloredString = '';
        $colored = false;

        // Check if given foreground color found
        if (isset($this->foregroundColors[$fgColor])) {
            $colored = true;
            $coloredString .= "\033[" . $this->foregroundColors[$fgColor] . 'm';
        }

        // Check if given background color found
        if (isset($this->backgroundColors[$bgColor])) {
            $colored = true;
            $coloredString .= "\033[" . $this->backgroundColors[$bgColor] . 'm';
        }

        // Add string and end coloring
        $coloredString .= $text . ($colored ? "\033[0m" : '');

        return $coloredString;
    }

    /**
     * Parse Command Definition
     *
     * @param array $command
     * @return array<int, string|array<string, array<string, bool|string|null>>>
     */
    protected function parseCommand(string $command): array
    {
        $exp = explode(' ', trim($command), 2);
        $command = trim($exp[0]);
        $args = [];
        $options = [];

        if (isset($exp[1])) {
            preg_match_all("/\{(?<name>\w+)(?<arr>\*)?((=(?<default>[^\}]+))|(?<optional>\?))?(::(?<desc>[^}]+))?\}/i", $exp[1], $matchArgs);
            preg_match_all("/\{--((?<alias>[a-zA-Z])\|)?(?<name>[a-zA-Z][a-zA-Z0-9_-]*)((?<valuable>=)(?<default>[^\}]+)?)?(::(?<desc>[^}]+))?\}/i", $exp[1], $matchOptions);
            foreach ($matchArgs['name'] as $i => $argName) {
                $default = $matchArgs['default'][$i];
                $expDefault = explode('::', $default, 2);
                if (count($expDefault) > 1) {
                    $default = $expDefault[0];
                    $description = $expDefault[1];
                } else {
                    $default = $expDefault[0];
                    $description = $matchArgs['desc'][$i];
                }

                $args[$argName] = [
                    'is_array' => ! empty($matchArgs['arr'][$i]),
                    'is_optional' => ! empty($matchArgs['optional'][$i]) || ! empty($default),
                    'default' => $default ?: null,
                    'description' => $description,
                ];
            }

            foreach ($matchOptions['name'] as $i => $optName) {
                $default = $matchOptions['default'][$i];
                $expDefault = explode('::', $default, 2);
                if (count($expDefault) > 1) {
                    $default = $expDefault[0];
                    $description = $expDefault[1];
                } else {
                    $default = $expDefault[0];
                    $description = $matchOptions['desc'][$i];
                }

                $options[$optName] = [
                    'is_valuable' => ! empty($matchOptions['valuable'][$i]),
                    'default' => $default ?: null,
                    'description' => $description,
                    'alias' => $matchOptions['alias'][$i] ?: null,
                ];
            }
        }

        return [$command, $args, $options];
    }

    /**
     * Parse PHP argv
     * @param array<int, mixed> $argv
     * @return array<int, string|array<string|int, mixed>|null>
     */
    protected function parseArgv(array $argv): array
    {
        $filename = array_shift($argv);
        $filename = $filename === null ? null : (string) $filename;

        $command = array_shift($argv);
        $command = $command === null ? null : (string) $command;

        $arguments = [];
        $options = [];
        $optionsAlias = [];

        while (count($argv)) {
            $arg = (string) array_shift($argv);
            if ($this->isOption($arg)) {
                $optName = ltrim($arg, '-');
                if ($this->isOptionWithValue($arg)) {
                    [$optName, $optvalue] = explode('=', $optName);
                } else {
                    $nextArg = $argv[0] ?? null;
                    $optvalue = $nextArg !== null && ! $this->isOption((string) $nextArg) && ! $this->isOptionAlias((string) $nextArg)
                        ? array_shift($argv)
                        : null;
                }

                $options[$optName] = $optvalue;
            } elseif ($this->isOptionAlias($arg)) {
                $alias = ltrim($arg, '-');
                $exp = explode('=', $alias);
                $aliases = str_split($exp[0]);
                if (count($aliases) > 1) {
                    foreach ($aliases as $aliasName) {
                        $optionsAlias[$aliasName] = null;
                    }
                } else {
                    $aliasName = $aliases[0];
                    if (count($exp) > 1) {
                        [$aliasName, $aliasValue] = $exp;
                    } else {
                        $aliasValue = array_shift($argv);
                    }

                    $optionsAlias[$aliasName] = $aliasValue;
                }
            } else {
                $arguments[] = $arg;
            }
        }

        return [$filename, $command, $arguments, $options, $optionsAlias];
    }

    /**
     * Check whether OS is windows
     */
    private function isWindows(): bool
    {
        return '\\' === DIRECTORY_SEPARATOR;
    }

    /**
     * Check whether Stty is available or not.
     */
    private function hasSttyAvailable(): bool
    {
        if (self::$stty !== null) {
            return self::$stty;
        }

        exec('stty 2>&1', $output, $exitcode);
        return self::$stty = $exitcode === 0;
    }

    /**
     * Returns a valid unix shell.
     *
     * @return string|false The valid shell name, false in case no valid shell is found
     */
    private function getShell(): string|false
    {
        if (self::$shell !== null) {
            return self::$shell;
        }

        self::$shell = false;
        if (file_exists('/usr/bin/env')) {
            // handle other OSs with bash/zsh/ksh/csh if available to hide the answer
            $test = "/usr/bin/env %s -c 'echo OK' 2> /dev/null";
            foreach (['bash', 'zsh', 'ksh', 'csh'] as $sh) {
                if (rtrim((string) shell_exec(sprintf($test, $sh))) === 'OK') {
                    self::$shell = $sh;
                    break;
                }
            }
        }

        return self::$shell;
    }

    /**
     * Check whether argument is option or not
     */
    protected function isOption(string $arg): bool
    {
        return (bool) preg_match('/^--[a-zA-Z][a-zA-Z0-9_-]*(?:=|$)/', $arg);
    }

    /**
     * Check whether argument is option alias or not
     */
    protected function isOptionAlias(string $arg): bool
    {
        return (bool) preg_match('/^-[a-z]+/i', $arg);
    }

    /**
     * Check whether argument is option with value or not
     */
    protected function isOptionWithValue(string $arg): bool
    {
        return str_contains($arg, '=');
    }

    /**
     * @return array resolved arguments
     */
    protected function validateAndResolveArguments(string $command): array
    {
        $args = $this->arguments;
        $commandArgs = $this->commands[$command]['args'];
        $resolvedArgs = [];
        foreach ($commandArgs as $argName => $argOption) {
            if (! $argOption['is_optional'] && $args === []) {
                $this->error("Argument {$argName} is required");
                return [];
            }

            if ($argOption['is_array']) {
                $value = $args;
            } else {
                $value = array_shift($args) ?: $argOption['default'];
            }

            $resolvedArgs[$argName] = $value;
        }

        return $resolvedArgs;
    }

    protected function validateAndResolveOptions(string $command): void
    {
        $options = $this->options;
        $optionsAlias = $this->optionsAlias;
        $commandOptions = $this->commands[$command]['options'];
        $resolvedOptions = $options;

        foreach ($commandOptions as $optName => $optionSetting) {
            $alias = $optionSetting['alias'];
            if ($alias && array_key_exists($alias, $optionsAlias)) {
                $value = array_key_exists($alias, $optionsAlias) ? $optionsAlias[$alias] : $optionSetting['default'];
            } else {
                $value = array_key_exists($optName, $options) ? $options[$optName] : $optionSetting['default'];
            }

            if (! $optionSetting['is_valuable']) {
                $resolvedOptions[$optName] = array_key_exists($alias, $optionsAlias) || array_key_exists($optName, $options);
            } else {
                $resolvedOptions[$optName] = $value;
            }
        }

        $this->resolvedOptions = $resolvedOptions;
    }

    /**
     * Show commands like given command
     */
    protected function showCommandsLike(string $keyword): void
    {
        $matchedCommands = $this->getCommandsLike($keyword);

        if (count($matchedCommands) === 1) {
            $keys = array_keys($matchedCommands);
            $name = array_shift($keys);
            $this->writeln(PHP_EOL . $this->color(" Command '{$keyword}' is not available. Did you mean '{$name}'?", 'red') . PHP_EOL);
        } else {
            $this->writeln(PHP_EOL . $this->color(" Command '{$keyword}' is not available.", 'red'));
            $commandList = $this->commands['list']['handler'];
            $commandList(count($matchedCommands) ? $keyword : null);
        }
    }

    /**
     * Show command help
     */
    protected function showHelp(string $commandName): void
    {
        $command = $this->commands[$commandName];
        $maxLen = 0;
        $args = $command['args'];
        $opts = $command['options'];
        $usageArgs = [$commandName];
        $displayArgs = [];
        $displayOpts = [];
        foreach ($args as $argName => $argSetting) {
            $usageArgs[] = '<' . $argName . '>';
            $displayArg = $argName;
            if ($argSetting['is_optional']) {
                $displayArg .= ' (optional)';
            }

            if (strlen($displayArg) > $maxLen) {
                $maxLen = strlen($displayArg);
            }

            $displayArgs[$displayArg] = $argSetting['description'];
        }

        $usageArgs[] = '[options]';

        foreach ($opts as $optName => $optSetting) {
            $displayOpt = $optSetting['alias'] ? str_pad('-' . $optSetting['alias'] . ',', 4) : str_repeat(' ', 4);
            $displayOpt .= '--' . $optName;
            if (strlen($displayOpt) > $maxLen) {
                $maxLen = strlen($displayOpt);
            }

            $displayOpts[$displayOpt] = $optSetting['description'];
        }

        $pad = $maxLen + 3;
        $this->writeln('');
        $descriptionLines = preg_split('/\R/', $command['description']) ?: [''];
        foreach ($descriptionLines as $descriptionLine) {
            $this->writeln(' ' . $descriptionLine);
        }

        $this->writeln('');
        $this->writeln($this->color(' Usage:', 'blue'));
        $this->writeln('');
        $this->writeln('  ' . implode(' ', $usageArgs));
        $this->writeln('');
        $this->writeln($this->color(' Arguments: ', 'blue') . PHP_EOL);
        foreach ($displayArgs as $argName => $argDesc) {
            $this->writeln('  ' . $this->color($argName, 'green') . str_repeat(' ', $pad - strlen($argName)) . $argDesc);
        }

        $this->writeln('');
        $this->writeln($this->color(' Options: ', 'blue') . PHP_EOL);
        foreach ($displayOpts as $optName => $optDesc) {
            $this->writeln(
                '  ' . $this->color($optName, 'green')
                . str_repeat(' ', $pad - strlen($optName))
                . $this->color((string) $optDesc, 'dark_gray')
            );
        }

        $this->writeln('');
    }

    /**
     * Error Handler
     */
    public function handleError(Exception $exception): void
    {
        $indent = str_repeat(' ', 2);
        $class = $exception::class;
        $file = $exception->getFile();
        $line = $exception->getLine();
        $filepath = (fn(string $file): string => str_replace(dirname(__DIR__) . DIRECTORY_SEPARATOR, '', $file));
        $message = $exception->getMessage();
        $verbose = (array_key_exists('verbose', $this->options) || array_key_exists('v', $this->optionsAlias));

        $this->writeln(
            PHP_EOL
            . $indent . 'Whops! You got an ' . $class
            . PHP_EOL
            . $indent . $message
            . PHP_EOL,
            'red'
        );

        if ($verbose) {
            $this->writeln(
                $indent . 'File: ' . $filepath($file)
                . PHP_EOL
                . $indent . 'Line: ' . $line
                . PHP_EOL,
                'dark_gray'
            );

            $traces = $exception->getTrace();
            $count = count($traces);
            $traceFunction = function (array $trace): string {
                $args = implode(', ', array_map($this->stringify(...), $trace['args']));
                if ($trace['function'] == '{closure}') {
                    return 'Closure(' . $args . ')';
                }

                if (! isset($trace['class'])) {
                    return $trace['function'] . '(' . $args . ')';
                }

                return $trace['class'] . $trace['type'] . $trace['function'] . '(' . $args . ')';
            };
            $x = $count > 9 ? 2 : 1;

            $this->writeln($indent . 'Traces:');
            foreach ($traces as $i => $trace) {
                $space = str_repeat(' ', $x + 2);
                $no = str_pad($count - $i, $x, ' ', STR_PAD_LEFT);
                $func = $traceFunction($trace);
                $file = isset($trace['file']) ? $filepath($trace['file']) : 'unknown';
                $line = $trace['line'] ?? 'unknown';
                $this->writeln("{$indent}{$no}) {$func}");
                $this->writeln("{$indent}{$space}File: {$file}", 'dark_gray');
                $this->writeln("{$indent}{$space}Line: {$line}", 'dark_gray');
                $this->writeln('');
            }
        }
    }

    /**
     * Stringify value
     */
    protected function stringify(mixed $value): string
    {
        if (is_object($value)) {
            return $value::class;
        }

        if (is_array($value)) {
            if (count($value) > 3) {
                return 'Array';
            }

            return implode(', ', array_map($this->stringify(...), $value));
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_string($value)) {
            return '"' . addslashes($value) . '"';
        }

        if ($value === null) {
            return 'null';
        }

        return (string) $value;
    }
}
