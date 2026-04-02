<?php

declare(strict_types=1);

namespace Arcos\Cli\Commands\Make;

use Arcos\Cli\Command;
use Arcos\Cli\IO\Output;

class MakeController extends Command
{
    public static function signature(): string
    {
        return 'make:controller';
    }

    public static function description(): string
    {
        return 'Generate a controller stub';
    }

    public function handle(array $args, array $flags): int
    {
        if (!$this->requireProjectContext()) {
            return 1;
        }

        if (empty($args[0])) {
            Output::error('Missing argument. Usage: arcos make:controller <name>');
            return 1;
        }

        // Normalize: strip "Controller" suffix if the user included it
        $name      = ucfirst(str_replace('Controller', '', $args[0]));
        $className = $name . 'Controller';
        $filePath  = $this->projectPath("app/Controllers/{$className}.php");

        if (file_exists($filePath)) {
            Output::error("File already exists: app/Controllers/{$className}.php");
            return 1;
        }

        // Parse --inject=ServiceA,ServiceB
        $injectFlag = isset($flags['inject']) && is_string($flags['inject']) ? $flags['inject'] : '';
        $services   = $injectFlag !== '' ? array_map('trim', explode(',', $injectFlag)) : [];

        $stub = $this->buildStub($className, $services);

        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        file_put_contents($filePath, $stub);
        Output::success("Created: app/Controllers/{$className}.php");

        if (isset($flags['register'])) {
            $this->register($className, $services);
        } else {
            $this->printSnippet($className, $services);
        }

        return 0;
    }

    // Stub

    private function buildStub(string $className, array $services): string
    {
        $useStatements = $this->buildUseStatements($services);
        $constructor   = $this->buildConstructor($services);

        $useLine = $useStatements !== '' ? $useStatements . "\n" : '';

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\Controllers;

        use Arcos\Core\Helpers\ErrorHelper;
        use Arcos\Core\Helpers\ResponseHelper;
        use Arcos\Core\Http\Request;
        use Arcos\Core\Http\Response;
        {$useLine}class {$className}
        {
        {$constructor}
            public function index(Request \$request): Response
            {
                return ResponseHelper::ok([]);
            }
        }
        PHP;
    }

    private function buildUseStatements(array $services): string
    {
        if (empty($services)) {
            return '';
        }

        return implode("\n", array_map(
            fn(string $s) => "use App\\Services\\{$s};",
            $services
        ));
    }

    private function buildConstructor(array $services): string
    {
        if (empty($services)) {
            return "    public function __construct(\n        // Inject services here\n    ) {}\n";
        }

        $params = implode("\n", array_map(
            fn(string $s) => "        private readonly {$s} \$" . lcfirst($s) . ',',
            $services
        ));

        return "    public function __construct(\n{$params}\n    ) {}\n";
    }

    // Registration

    private function printSnippet(string $className, array $services): void
    {
        $makeArgs = $this->buildMakeArgs($services);
        $binding  = "\$container->singleton({$className}::class, fn(\$c) => new {$className}({$makeArgs}));";

        Output::snippet(
            'To register this controller, add the following to index.php:',
            "use App\\Controllers\\{$className};\n\n{$binding}"
        );
    }

    private function register(string $className, array $services): void
    {
        $indexPath = $this->projectPath('index.php');

        if (!file_exists($indexPath)) {
            Output::error('Could not find index.php. Registration skipped.');
            $this->printSnippet($className, $services);
            return;
        }

        $content = file_get_contents($indexPath);

        if (!str_contains($content, '// 3. Container')) {
            Output::warn('Could not locate the container section in index.php. Add the binding manually:');
            $this->printSnippet($className, $services);
            return;
        }

        $makeArgs = $this->buildMakeArgs($services);
        $binding  = "\$container->singleton({$className}::class, fn(\$c) => new {$className}({$makeArgs}));";
        $useLine  = "use App\\Controllers\\{$className};";

        // Insert binding after the "// 3. Container" anchor
        $content = preg_replace(
            '/(\/\/ 3\. Container[^\n]*\n)/',
            "$1    {$binding}\n",
            $content,
            limit: 1
        );

        // Insert use statement after declare(strict_types=1);
        $content = preg_replace(
            '/(declare\(strict_types=1\);\n)/',
            "$1\n{$useLine}\n",
            $content,
            limit: 1
        );

        file_put_contents($indexPath, $content);
        Output::success("Registered in index.php: {$binding}");
    }

    private function buildMakeArgs(array $services): string
    {
        if (empty($services)) {
            return '';
        }

        return implode(', ', array_map(
            fn(string $s) => "\$c->make({$s}::class)",
            $services
        ));
    }
}