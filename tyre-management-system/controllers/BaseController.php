<?php
declare(strict_types=1);

class BaseController
{
    protected function view(string $path): void
    {
        require __DIR__ . '/../' . ltrim($path, '/');
    }
}

