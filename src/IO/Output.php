<?php

declare(strict_types=1);

namespace Arcos\Cli\IO;

class Output
{
    // ANSI color codes
    private const string GREEN  = "\033[32m";
    private const string RED    = "\033[31m";
    private const string YELLOW = "\033[33m";
    private const string CYAN   = "\033[36m";
    private const string GRAY   = "\033[90m";
    private const string BOLD   = "\033[1m";
    private const string RESET  = "\033[0m";

    public static function line(string $text = ''): void
    {
        echo $text . PHP_EOL;
    }

    public static function success(string $text): void
    {
        echo self::GREEN . '  ✓  ' . self::RESET . $text . PHP_EOL;
    }

    public static function error(string $text): void
    {
        echo self::RED . '  ✗  ' . self::RESET . $text . PHP_EOL;
    }

    public static function warn(string $text): void
    {
        echo self::YELLOW . '  ⚠  ' . self::RESET . $text . PHP_EOL;
    }

    public static function info(string $text): void
    {
        echo self::CYAN . '  →  ' . self::RESET . $text . PHP_EOL;
    }

    public static function comment(string $text): void
    {
        echo self::GRAY . $text . self::RESET . PHP_EOL;
    }

    public static function bold(string $text): void
    {
        echo self::BOLD . $text . self::RESET . PHP_EOL;
    }

    public static function header(string $text): void
    {
        echo PHP_EOL . self::BOLD . $text . self::RESET . PHP_EOL;
    }

    /**
     * Print a snippet the developer needs to add manually.
     * Used by make:* commands when --register is not passed.
     */
    public static function snippet(string $label, string $code): void
    {
        echo PHP_EOL;
        echo self::GRAY . $label . self::RESET . PHP_EOL;
        echo PHP_EOL;

        foreach (explode(PHP_EOL, $code) as $codeLine) {
            echo '    ' . $codeLine . PHP_EOL;
        }

        echo PHP_EOL;
    }
}