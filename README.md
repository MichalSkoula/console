MichalSkoula Console
====================

MichalSkoula Console is a simple PHP library for creating command-line applications.
This library strongly inspired by [Laravel Artisan Console](https://laravel.com/docs/5.4/artisan).

This project is a fork of [rakit/console](https://github.com/rakit/console).

## Features

* Closure command. You don't need to create class for simple command.
* Built-in command `list`.
* Auto help handler for each commands.
* Easy command signature.
* Password input.
* Simple Coloring.

## Installation

Until the package is published on Packagist, add its GitHub repository to your project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/MichalSkoula/console"
        }
    ]
}
```

Then install the package:

```bash
composer require michalskoula/console:^0.2
```

## Quickstart

#### 1. Create App

Create a file named `cli` (without extension).

```php
<?php

use MichalSkoula\Console\App;

require('vendor/autoload.php');

// 1. Initialize app
$app = new App;

// 2. Register commands
$app->command('hello {name}', 'Say hello to someone', function($name) {
    $this->writeln("Hello {$name}");
});

// 3. Run app
$app->run();
```

#### 2. Running Command

Open terminal/cmd, go to your app directory, run this command:

```
php cli hello "John Doe"
```

#### 3. Command List

You can see available commands by typing this:

```
php cli list
```

#### 4. Show Help

You can show help by putting `--help` or `-h` for each command. For example:

```
php cli hello --help
```

## Command groups and colors

Use groups to organize the command list. Commands are displayed in registration order.

```php
use MichalSkoula\Console\Color;

$app->group('Maintenance', Color::RED, function () {
    $this->command('maintenance:on', 'Enable maintenance mode', function () {
        // ...
    });
});
```

The fourth argument of `command()` changes the command name color:

```php
$app->command('danger', 'Run a dangerous command', function () {
    // ...
}, Color::RED);
```

Available foreground colors are defined as `Color` constants, for example `Color::LIGHT_GREEN` and `Color::YELLOW`.

## List header

Use `setListHeader()` to replace the default `Available Commands:` text. The header supports multiple lines, including ASCII art.

```php
$app->setListHeader(<<<'HEADER'
My Console
==========
HEADER, Color::CYAN);
```

Pass a third argument in milliseconds to display the header with a typing effect:

```php
$app->setListHeader($asciiArt, Color::CYAN, 12);
```
