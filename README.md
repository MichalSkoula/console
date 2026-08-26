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
