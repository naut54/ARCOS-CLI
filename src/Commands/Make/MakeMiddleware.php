<?php

declare(strict_types=1);

namespace Arcos\Cli\Commands\Make;

use Arcos\Cli\Command;
use Arcos\Cli\IO\Output;

class MakeMiddleware extends Command
{
    public static function signature(): string
    {
        return 'make:middleware';
    }

    public static function description(): string
    {
        return 'Generate a middleware stub';
    }

    public function handle(array $args, array $flags): int
    {
        if (!$this->requireProjectContext()) {
            return 1;
        }

        if (empty($args[0])) {
            Output::error('Missing argument. Usage: arcos make:middleware <n> [--mandatory] [--route-aware]');
            return 1;
        }

        // Normalize: strip "Middleware" suffix if the user included it
        $name      = ucfirst(str_replace('Middleware', '', $args[0]));
        $className = $name . 'Middleware';
        $filePath  = $this->projectPath("app/Middleware/{$className}.php");

        if (file_exists($filePath)) {
            Output::error("File already exists: app/Middleware/{$className}.php");
            return 1;
        }

        $isMandatory  = isset($flags['mandatory']);
        $isRouteAware = isset($flags['route-aware']);

        $stub = $this->buildStub($className, $isMandatory, $isRouteAware);

        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        file_put_contents($filePath, $stub);
        Output::success("Created: app/Middleware/{$className}.php");

        if (isset($flags['register'])) {
            $this->register($className);
        } else {
            $this->printSnippet($className);
        }

        return 0;
    }

    // Stub

    private function buildStub(string $className, bool $isMandatory, bool $isRouteAware): string
    {
        $interfaces    = $this->buildInterfaces($isMandatory, $isRouteAware);
        $useStatements = $this->buildUseStatements($isMandatory, $isRouteAware);
        $methods       = $this->buildMethods($isMandatory, $isRouteAware);

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\Middleware;

        use Arcos\Core\Http\Request;
        use Arcos\Core\Http\Response;
        use Arcos\Core\Middleware\MiddlewareInterface;
        {$useStatements}
        class {$className} implements {$interfaces}
        {
        {$methods}
        }
        PHP;
    }

    private function buildInterfaces(bool $isMandatory, bool $isRouteAware): string
    {
        $interfaces = ['MiddlewareInterface'];

        if ($isMandatory) {
            $interfaces[] = 'MandatoryMiddlewareInterface';
        }

        if ($isRouteAware) {
            $interfaces[] = 'RouteAwareInterface';
        }

        return implode(', ', $interfaces);
    }

    private function buildUseStatements(bool $isMandatory, bool $isRouteAware): string
    {
        $lines = [];

        if ($isMandatory) {
            $lines[] = 'use Arcos\Core\Middleware\MandatoryMiddlewareInterface;';
        }

        if ($isRouteAware) {
            $lines[] = 'use Arcos\Core\Middleware\RouteAwareInterface;';
        }

        return empty($lines) ? '' : implode("\n", $lines) . "\n";
    }

    private function buildMethods(bool $isMandatory, bool $isRouteAware): string
    {
        $methods = [];

        // Base handle() — always present
        $methods[] = <<<'PHP'
            public function handle(Request $request, callable $next): Response
            {
                // Inspect or validate the request here.
                // Return ErrorHelper::respond('...') to short-circuit.
                // Call $next($request) to continue the chain.

                return $next($request);
            }
        PHP;

        // handleMandatory() — only when --mandatory
        if ($isMandatory) {
            $methods[] = <<<'PHP'
                public function handleMandatory(Request $request, Response $response): Response
                {
                    // Called on the second pass if this middleware was skipped.
                    return $response;
                }
            PHP;
        }

        // withAllowedMethods() — only when --route-aware
        if ($isRouteAware) {
            $methods[] = <<<'PHP'
                public function withAllowedMethods(array $methods): static
                {
                    // Store $methods and use them in handle() to validate the HTTP verb.
                    return $this;
                }
            PHP;
        }

        return implode("\n\n", $methods);
    }

    // Registration 

    private function printSnippet(string $className): void
    {
        $binding = "\$container->bind({$className}::class, fn(\$c) => new {$className}());";

        Output::snippet(
            'To register this middleware, add the following to index.php:',
            "use App\\Middleware\\{$className};\n\n{$binding}"
        );

        Output::snippet(
            'Then reference it in config/middleware.php or routes/api.php:',
            "\$router->always([\n    new MiddlewareLink({$className}::class),\n]);"
        );
    }

    private function register(string $className): void
    {
        $indexPath = $this->projectPath('index.php');

        if (!file_exists($indexPath)) {
            Output::error('Could not find index.php. Registration skipped.');
            $this->printSnippet($className);
            return;
        }

        $content = file_get_contents($indexPath);

        if (!str_contains($content, '// 3. Container')) {
            Output::warn('Could not locate the container section in index.php. Add the binding manually:');
            $this->printSnippet($className);
            return;
        }

        // Middleware uses bind() (fresh instance per make()), not singleton()
        $binding = "\$container->bind({$className}::class, fn(\$c) => new {$className}());";
        $useLine = "use App\\Middleware\\{$className};";

        $content = preg_replace(
            '/(\/\/ 3\. Container[^\n]*\n)/',
            "$1    {$binding}\n",
            $content,
            limit: 1
        );

        $content = preg_replace(
            '/(declare\(strict_types=1\);\n)/',
            "$1\n{$useLine}\n",
            $content,
            limit: 1
        );

        file_put_contents($indexPath, $content);
        Output::success("Registered in index.php: {$binding}");
    }
}