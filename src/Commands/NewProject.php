<?php

declare(strict_types=1);

namespace Arcos\Cli\Commands;

use Arcos\Cli\Command;
use Arcos\Cli\IO\Output;

class NewProject extends Command
{
    public static function signature(): string
    {
        return 'new';
    }

    public static function description(): string
    {
        return 'Create a new ARCOS project';
    }

    public function handle(array $args, array $flags): int
    {
        if (empty($args[0])) {
            Output::error('Missing argument. Usage: arcos new <project-name> [--lite]');
            return 1;
        }

        $projectName = $args[0];
        $isLite      = isset($flags['lite']);
        $targetPath  = getcwd() . DIRECTORY_SEPARATOR . $projectName;

        if (is_dir($targetPath)) {
            Output::error("Directory already exists: {$projectName}");
            return 1;
        }

        Output::header("Creating ARCOS project: {$projectName}");
        Output::comment($isLite ? '  Mode: lite (no framework dependency)' : '  Mode: full (requires spire/arcos)');
        Output::line();

        // 1. Directory structure
        $this->createDirectories($targetPath);
        Output::success('Directory structure created');

        // 2. composer.json
        file_put_contents($targetPath . '/composer.json', $this->buildComposerJson($projectName, $isLite));
        Output::success('composer.json');

        // 3. index.php
        file_put_contents($targetPath . '/index.php', $this->buildIndexPhp($isLite));
        Output::success('index.php');

        // 4. routes/api.php — includes /health route
        file_put_contents($targetPath . '/routes/api.php', $this->buildRoutesFile());
        Output::success('routes/api.php');

        // 5. HealthController
        file_put_contents(
            $targetPath . '/app/Controllers/HealthController.php',
            $this->buildHealthController()
        );
        Output::success('app/Controllers/HealthController.php');

        // 6. config/middleware.php
        file_put_contents($targetPath . '/config/middleware.php', $this->buildMiddlewareConfig());
        Output::success('config/middleware.php');

        // 7. .env.example
        file_put_contents($targetPath . '/.env.example', "APP_ENV=\nAPP_KEY=\n");
        Output::success('.env.example');

        // 8. .env
        file_put_contents($targetPath . '/.env', "APP_ENV=\nAPP_KEY=\n");
        Output::success('.env (created empty — fill in APP_KEY before starting)');

        // 9. .gitignore
        file_put_contents($targetPath . '/.gitignore', ".env\nvendor/\n");
        Output::success('.gitignore');

        // 10. composer dump-autoload or install
        Output::line();
        $this->runComposer($targetPath, $isLite);

        // Done
        Output::line();
        Output::header('Project ready. Next steps:');
        Output::line();
        Output::line("  1. cd {$projectName}");
        Output::line('  2. Fill in APP_KEY in .env');

        if ($isLite) {
            Output::line('  3. Add your framework source to src/');
        } else {
            Output::line('  3. arcos dev:serve');
            Output::line('  4. curl http://localhost:8000/health');
        }

        Output::line();

        return 0;
    }

    // Directory structure

    private function createDirectories(string $base): void
    {
        $dirs = [
            'src/Core/Http/Resolvers',
            'src/Core/Routing/Attributes',
            'src/Core/Middleware',
            'src/Core/Container',
            'src/Core/Helpers',
            'src/Services',
            'app/Controllers',
            'app/Services',
            'app/Middleware',
            'app/Models',
            'config',
            'routes',
            'tests/Unit',
            'tests/Support',
            'tests/Fixtures',
        ];

        foreach ($dirs as $dir) {
            mkdir($base . DIRECTORY_SEPARATOR . $dir, 0755, recursive: true);
        }

        // Keep empty directories in git
        $keepDirs = [
            'app/Services',
            'app/Middleware',
            'app/Models',
            'tests/Unit',
            'tests/Support',
            'tests/Fixtures',
        ];

        foreach ($keepDirs as $dir) {
            file_put_contents($base . DIRECTORY_SEPARATOR . $dir . '/.gitkeep', '');
        }
    }

    // File generators

    private function buildComposerJson(string $projectName, bool $isLite): string
    {
        $slug    = strtolower(preg_replace('/[^a-z0-9-]/i', '-', $projectName));
        $require = $isLite
            ? '"php": ">=8.3"'
            : '"php": ">=8.3",' . PHP_EOL . '        "pylon/arcos": "^1.0"';

        return <<<JSON
        {
            "name": "your/{$slug}",
            "description": "",
            "type": "project",
            "license": "MIT",
            "require": {
                {$require}
            },
            "autoload": {
                "psr-4": {
                    "Arcos\\\\": "src/",
                    "App\\\\": "app/"
                }
            },
            "autoload-dev": {
                "psr-4": {
                    "Arcos\\\\Tests\\\\": "tests/"
                }
            }
        }
        JSON;
    }

    private function buildIndexPhp(bool $isLite): string
    {
        if ($isLite) {
            return <<<'PHP'
<?php

declare(strict_types=1);

// 1. Autoloader
require_once __DIR__ . '/vendor/autoload.php';

// 2. Environment
$env = parse_ini_file(__DIR__ . '/.env');

if ($env === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server configuration error.']);
    exit;
}

foreach (['APP_ENV', 'APP_KEY'] as $required) {
    if (empty($env[$required])) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server configuration error.']);
        exit;
    }
}

foreach ($env as $key => $value) {
    $_ENV[$key] = $value;
}

// 3. Container — register all bindings
// $container = new Container();

// 4. Router — register subdomain contexts
// $router = new Router();

// 5. Active subdomain
// $router->setActiveSubdomain('api');

// 6. Routes and middleware config
// (require __DIR__ . '/routes/api.php')($router);
// (require __DIR__ . '/config/middleware.php')($router);

// 7. Boot
// $router->boot(__DIR__);

// 8. Kernel and Request
// $kernel  = new Kernel($container, $router);
// $request = Request::fromGlobals($router->activeResolver());
// $kernel->handle($request);
PHP;
        }

        return <<<'PHP'
<?php

declare(strict_types=1);

use Arcos\Core\Container\Container;
use Arcos\Core\Http\Kernel;
use Arcos\Core\Http\Request;
use Arcos\Core\Http\Resolvers\PathUriResolver;
use Arcos\Core\Routing\Router;
use App\Controllers\HealthController;

// 1. Autoloader
require_once __DIR__ . '/vendor/autoload.php';

// 2. Environment
$env = parse_ini_file(__DIR__ . '/.env');

if ($env === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server configuration error.']);
    exit;
}

foreach (['APP_ENV', 'APP_KEY'] as $required) {
    if (empty($env[$required])) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server configuration error.']);
        exit;
    }
}

foreach ($env as $key => $value) {
    $_ENV[$key] = $value;
}

// 3. Container — register all bindings
$container = new Container();

$container->singleton(HealthController::class, fn($c) => new HealthController());

// 4. Router — register subdomain contexts
$router = new Router();

$router->registerSubdomain(
    subdomain:        'api',
    resolver:         new PathUriResolver(),
    middlewareGroups: ['api'],
);

// 5. Active subdomain
$router->setActiveSubdomain('api');

// 6. Routes and middleware config
(require __DIR__ . '/routes/api.php')($router);
(require __DIR__ . '/config/middleware.php')($router);

// 7. Boot
$router->boot(__DIR__);

// 8. Kernel and Request
if (empty($_SERVER['ARCOS_INSPECT'])) {
    $kernel  = new Kernel($container, $router);
    $request = Request::fromGlobals($router->activeResolver());
    $kernel->handle($request);
}
PHP;
    }

    private function buildRoutesFile(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

use Arcos\Core\Routing\Router;
use App\Controllers\HealthController;

return function (Router $router): void {
    $router->subdomain('api', function (Router $router): void {
        $router->add('GET', '/health', HealthController::class, 'index');

        // $router->add('GET', '/example', ExampleController::class, 'index');
    });
};
PHP;
    }

    private function buildHealthController(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Controllers;

use Arcos\Core\Helpers\ResponseHelper;
use Arcos\Core\Http\Request;
use Arcos\Core\Http\Response;

class HealthController
{
    public function __construct(
        // Inject services here to include their health() in the response
    ) {}

    public function index(Request $request): Response
    {
        $services = [
            // $this->inventoryService->health(),
            // $this->notificationService->health(),
        ];

        $overall = 'ok';

        foreach ($services as $service) {
            if ($service['status'] === 'down') {
                $overall = 'down';
                break;
            }

            if ($service['status'] === 'degraded') {
                $overall = 'degraded';
            }
        }

        return ResponseHelper::ok([
            'status'   => $overall,
            'services' => $services,
        ]);
    }
}
PHP;
    }

    private function buildMiddlewareConfig(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

use Arcos\Core\Middleware\MiddlewareLink;
use Arcos\Core\Routing\Router;

return function (Router $router): void {
    // Global middleware — runs on every route
    $router->always([
        // new MiddlewareLink(LoggerMiddleware::class, isMandatory: true, canHaveGroup: false),
    ]);

    // Groups
    // $router->group('api', [
    //     new MiddlewareLink(VerbValidationMiddleware::class),
    // ]);
};
PHP;
    }

    // Composer

    private function runComposer(string $targetPath, bool $isLite): void
    {
        $command = $isLite
            ? 'composer dump-autoload'
            : 'composer install --no-interaction';

        Output::info("Running: {$command}");

        $cwd = getcwd();
        chdir($targetPath);
        passthru($command, $exitCode);
        chdir($cwd);

        if ($exitCode !== 0) {
            Output::warn("Composer exited with code {$exitCode}. Run it manually inside the project directory.");
        } else {
            Output::success($isLite ? 'composer dump-autoload' : 'composer install');
        }
    }
}