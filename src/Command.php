<?php

declare(strict_types=1);

namespace Arcos\Cli;

use Arcos\Cli\IO\Output;

abstract class Command
{
    /**
     * The command signature as declared in Application::COMMANDS.
     * e.g. "make:controller", "dev:serve".
     */
    abstract public static function signature(): string;

    /**
     * One-line description shown in `arcos help`.
     */
    abstract public static function description(): string;

    /**
     * Execute the command.
     *
     * @param array<int, string> $args    Positional arguments (e.g. ["Products"]).
     * @param array<string, string|true> $flags  Parsed flags (e.g. ["port" => "9000", "register" => true]).
     */
    abstract public function handle(array $args, array $flags): int;

    /**
     * Verify the current working directory is an ARCOS project.
     * Commands that operate on project files must call this first.
     */
    protected function requireProjectContext(): bool
    {
        $cwd = getcwd();

        if (!file_exists($cwd . '/composer.json') || !file_exists($cwd . '/index.php')) {
            Output::error('Not an ARCOS project. Run this command from the project root.');
            return false;
        }

        return true;
    }

    /**
     * Resolve the absolute path to a file inside the target project.
     */
    protected function projectPath(string $relative): string
    {
        return getcwd() . '/' . ltrim($relative, '/');
    }
}