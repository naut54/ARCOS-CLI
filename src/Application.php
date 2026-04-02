<?php

declare(strict_types=1);

namespace Arcos\Cli;

use Arcos\Cli\IO\Output;
use Arcos\Cli\Commands\NewProject;
use Arcos\Cli\Commands\Make\MakeController;
use Arcos\Cli\Commands\Make\MakeService;
use Arcos\Cli\Commands\Make\MakeMiddleware;
use Arcos\Cli\Commands\Make\MakeModel;
use Arcos\Cli\Commands\Dev\DevServe;
use Arcos\Cli\Commands\Dev\DevEnv;
use Arcos\Cli\Commands\Dev\DevHealth;
use Arcos\Cli\Commands\Inspect\InspectRoutes;
use Arcos\Cli\Commands\Inspect\InspectMiddleware;
use Arcos\Cli\Commands\Inspect\InspectContainer;

class Application
{
    private const array COMMANDS = [
        'new'                => NewProject::class,
        'make:controller'    => MakeController::class,
        'make:service'       => MakeService::class,
        'make:middleware'    => MakeMiddleware::class,
        'make:model'         => MakeModel::class,
        'dev:serve'          => DevServe::class,
        'dev:env'            => DevEnv::class,
        'dev:health'         => DevHealth::class,
        'inspect:routes'     => InspectRoutes::class,
        'inspect:middleware' => InspectMiddleware::class,
        'inspect:container'  => InspectContainer::class,
    ];

    private string $commandName;

    private array $args;

    private array $flags;

    public function __construct(array $argv)
    {
        // $argv[0] is the script name — skip it.
        $tokens = array_slice($argv, 1);

        $this->commandName = $tokens[0] ?? 'help';
        [$this->args, $this->flags] = $this->parseTokens(array_slice($tokens, 1));
    }

    public function run(): int
    {
        if ($this->commandName === 'help' || $this->commandName === '--help') {
            $this->printHelp();
            return 0;
        }

        if (!isset(self::COMMANDS[$this->commandName])) {
            Output::error("Unknown command: {$this->commandName}");
            Output::line();
            Output::comment("Run 'arcos help' to see available commands.");
            return 1;
        }

        $class   = self::COMMANDS[$this->commandName];
        $command = new $class();

        return $command->handle($this->args, $this->flags);
    }

    // Argument parsing

    private function parseTokens(array $tokens): array
    {
        $args  = [];
        $flags = [];

        foreach ($tokens as $token) {
            if (str_starts_with($token, '--')) {
                $token = substr($token, 2); // Strip leading "--"

                if (str_contains($token, '=')) {
                    [$key, $value] = explode('=', $token, 2);
                    $flags[$key] = $value;
                } else {
                    $flags[$token] = true;
                }
            } else {
                $args[] = $token;
            }
        }

        return [$args, $flags];
    }

    // Help

    private function printHelp(): void
    {
        Output::header('ARCOS CLI');
        Output::comment('  API Routing Core Orchestrator System — developer tooling');
        Output::line();
        Output::bold('Usage:');
        Output::line('  arcos <command> [arguments] [--flags]');
        Output::line();
        Output::bold('Available commands:');
        Output::line();

        $groups = [
            'Scaffolding' => [],
            'make'        => [],
            'inspect'     => [],
            'dev'         => [],
        ];

        foreach (self::COMMANDS as $signature => $class) {
            $prefix = str_contains($signature, ':') ? explode(':', $signature)[0] : 'Scaffolding';
            $groups[$prefix][$signature] = $class::description();
        }
        foreach ($groups as $group => $commands) {
            if (empty($commands)) {
                continue;
            }

            Output::comment("  {$group}");

            foreach ($commands as $signature => $description) {
                printf("    %-28s %s\n", $signature, $description);
            }

            Output::line();
        }
    }
}