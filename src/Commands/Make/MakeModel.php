<?php

declare(strict_types=1);

namespace Arcos\Cli\Commands\Make;

use Arcos\Cli\Command;
use Arcos\Cli\IO\Output;

class MakeModel extends Command
{
    public static function signature(): string
    {
        return 'make:model';
    }

    public static function description(): string
    {
        return 'Generate a model stub';
    }

    public function handle(array $args, array $flags): int
    {
        if (!$this->requireProjectContext()) {
            return 1;
        }

        if (empty($args[0])) {
            Output::error('Missing argument. Usage: arcos make:model <n>');
            return 1;
        }

        $className = ucfirst($args[0]);
        $filePath  = $this->projectPath("app/Models/{$className}.php");

        if (file_exists($filePath)) {
            Output::error("File already exists: app/Models/{$className}.php");
            return 1;
        }

        $stub = $this->buildStub($className);

        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        file_put_contents($filePath, $stub);
        Output::success("Created: app/Models/{$className}.php");

        // Models have no base class and do not require container registration
        // unless they have dependencies — so we only print a note, not a snippet.
        Output::line();
        Output::comment('  Models are plain PHP classes. No container registration required');
        Output::comment('  unless this model has dependencies — in that case, add a binding');
        Output::comment('  to index.php manually following the same pattern as services.');

        return 0;
    }

    // Stub

    private function buildStub(string $className): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\Models;

        class {$className}
        {
            // Encapsulate data access here.
            // Models read and write data. They do not contain business logic.
        }
        PHP;
    }
}