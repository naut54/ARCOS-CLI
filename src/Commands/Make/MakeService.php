<?php

declare(strict_types=1);

namespace Arcos\Cli\Commands\Make;

use Arcos\Cli\Command;
use Arcos\Cli\IO\Output;

class MakeService extends Command
{
    public static function signature(): string
    {
        return 'make:service';
    }

    public static function description(): string
    {
        return 'Generate a service stub';
    }

    public function handle(array $args, array $flags): int
    {
        if (!$this->requireProjectContext()) {
            return 1;
        }

        if (empty($args[0])) {
            Output::error('Missing argument. Usage: arcos make:service <n>');
            return 1;
        }

        // Normalize: strip "Service" suffix if the user included it
        $name      = ucfirst(str_replace('Service', '', $args[0]));
        $className = $name . 'Service';
        $filePath  = $this->projectPath("app/Services/{$className}.php");

        if (file_exists($filePath)) {
            Output::error("File already exists: app/Services/{$className}.php");
            return 1;
        }

        $stub = $this->buildStub($className);

        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        file_put_contents($filePath, $stub);
        Output::success("Created: app/Services/{$className}.php");

        if (isset($flags['register'])) {
            $this->register($className);
        } else {
            $this->printSnippet($className);
        }

        return 0;
    }

    // Stub

    private function buildStub(string $className): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\Services;

        use Arcos\Services\BaseService;

        class {$className} extends BaseService
        {
            protected string \$baseUrl = '';
            protected int    \$timeout = 5;

            public function health(): array
            {
                \$response = \$this->get('/health');

                return \$response['status'] === 'ok'
                    ? \$this->ok()
                    : \$this->down('{$className} health check failed.');
            }
        }
        PHP;
    }

    // Registration 

    private function printSnippet(string $className): void
    {
        $binding = "\$container->singleton({$className}::class, fn(\$c) => new {$className}());";

        Output::snippet(
            'To register this service, add the following to index.php:',
            "use App\\Services\\{$className};\n\n{$binding}"
        );

        Output::snippet(
            'To inject it into a controller, add it to the constructor:',
            "use App\\Services\\{$className};\n\npublic function __construct(\n    private readonly {$className} \$" . lcfirst($className) . ",\n) {}"
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

        $binding = "\$container->singleton({$className}::class, fn(\$c) => new {$className}());";
        $useLine = "use App\\Services\\{$className};";

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