<?php

namespace MichalSkoula\Console;

use Exception;

abstract class Command
{
    protected ?App $app = null;

    protected string $signature = '';

    protected string $description = '';

    public function getSignature(): string
    {
        return $this->signature;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function defineApp(App $app): void
    {
        if (!$this->app) {
            $this->app = $app;
        }
    }

    public function __call(string $method, array $args): mixed
    {
        if ($this->app AND method_exists($this->app, $method)) {
            return call_user_func_array([$this->app, $method], $args);
        } else {
            $class = get_class($this);
            throw new Exception("Call to undefined method {$class}::{$method}");
        }
    }

}
