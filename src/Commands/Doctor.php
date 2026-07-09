<?php

declare(strict_types=1);

namespace Arcos\Cli\Commands;

use Arcos\Cli\Command;
use Arcos\Cli\Commands\Dev\DevEnv;
use Arcos\Cli\Commands\Inspect\InspectContainer;
use Arcos\Cli\Commands\Inspect\RuntimeBridge;
use Arcos\Cli\IO\Output;

/**
 * Diagnoses whether a project is structurally healthy: correct PHP version,
 * dependencies installed, environment configured, and — the check that
 * matters most — a real boot of index.php actually succeeds. Read-only,
 * no live HTTP (that's dev:health's job), no auto-fixing.
 */
class Doctor extends Command
{
    public static function signature(): string
    {
        return 'doctor';
    }

    public static function description(): string
    {
        return 'Diagnose project health (env, dependencies, boot, routes)';
    }

    public function handle(array $args, array $flags): int
    {
        if (!$this->requireProjectContext()) {
            return 1;
        }

        Output::header('ARCOS Doctor');
        Output::line();

        $healthy = true;

        $php = $this->checkPhpVersion();
        $this->render($php);
        $healthy = $healthy && $php['status'] !== 'fail';

        $deps = $this->checkDependencies();
        $this->render($deps);
        $healthy = $healthy && $deps['status'] !== 'fail';

        $env = $this->checkEnvironment();
        $this->render($env, 'Environment');
        $healthy = $healthy && $env['status'] !== 'fail';

        $boot = $this->checkBoot();
        $this->render($boot, 'Boot');
        $healthy = $healthy && $boot['status'] !== 'fail';

        if ($boot['status'] === 'fail') {
            // Nothing further to inspect — routes/bindings both depend on a
            // successful boot.
            Output::line();
            Output::error('Project is unhealthy.');
            return 1;
        }

        $routes = $this->summarizeRoutes($boot['routes']);
        $this->render($routes, 'Routes');

        $bindings = $this->checkContainerBindings();
        $this->render($bindings, 'Container');

        Output::line();

        if ($healthy) {
            Output::success('Project is healthy.');
            return 0;
        }

        Output::error('Project is unhealthy.');
        return 1;
    }

    // Checks

    /**
     * Only handles the simple ">=X.Y" form ARCOS itself always generates
     * (NewProject's templates, ARCOS/composer.json) — not general Composer
     * constraint syntax (^, ||, etc.). Adding composer/semver as a dependency
     * for full constraint parsing is out of scope.
     *
     * @return array{status: string, message: string}
     */
    private function checkPhpVersion(): array
    {
        $composerJsonPath = $this->projectPath('composer.json');

        if (!file_exists($composerJsonPath)) {
            return ['status' => 'skip', 'message' => 'composer.json not found — skipping PHP version check.'];
        }

        $composerJson = json_decode(file_get_contents($composerJsonPath), associative: true);
        $constraint   = $composerJson['require']['php'] ?? null;

        if ($constraint === null) {
            return ['status' => 'skip', 'message' => 'No php constraint declared in composer.json.'];
        }

        if (!preg_match('/^>=\s*([\d.]+)$/', trim($constraint), $matches)) {
            return [
                'status'  => 'skip',
                'message' => "Unrecognized php constraint [{$constraint}] — only \">=X.Y\" is checked automatically.",
            ];
        }

        $satisfies = version_compare(PHP_VERSION, $matches[1], '>=');

        return [
            'status'  => $satisfies ? 'ok' : 'fail',
            'message' => $satisfies
                ? sprintf('PHP %s satisfies %s', PHP_VERSION, $constraint)
                : sprintf('PHP %s does not satisfy %s', PHP_VERSION, $constraint),
        ];
    }

    /**
     * @return array{status: string, message: string}
     */
    private function checkDependencies(): array
    {
        if (!file_exists($this->projectPath('vendor/autoload.php'))) {
            return ['status' => 'fail', 'message' => 'vendor/autoload.php not found. Run composer install.'];
        }

        if (!is_dir($this->projectPath('vendor/pylon/arcos'))) {
            return ['status' => 'fail', 'message' => 'pylon/arcos is not installed in vendor/. Run composer require pylon/arcos.'];
        }

        if (!file_exists($this->projectPath('composer.lock'))) {
            return ['status' => 'warn', 'message' => 'composer.lock not found — dependency versions are not pinned.'];
        }

        return ['status' => 'ok', 'message' => 'vendor/ installed (pylon/arcos present)'];
    }

    /**
     * Reuses DevEnv::check() rather than re-implementing env-parsing here.
     *
     * @return array{status: string, message: string}
     */
    private function checkEnvironment(): array
    {
        $envPath        = $this->projectPath('.env');
        $envExamplePath = $this->projectPath('.env.example');
        $gitignorePath  = $this->projectPath('.gitignore');

        if (!file_exists($envExamplePath)) {
            return ['status' => 'fail', 'message' => '.env.example not found.'];
        }

        if (!file_exists($envPath)) {
            return ['status' => 'fail', 'message' => '.env not found.'];
        }

        $result = (new DevEnv())->check($envExamplePath, $envPath, $gitignorePath);

        if ($result['errors'] > 0) {
            $noun = $result['errors'] === 1 ? 'variable' : 'variables';
            return ['status' => 'fail', 'message' => "{$result['errors']} required {$noun} not set."];
        }

        if ($result['gitignore']['status'] !== 'ok') {
            return ['status' => 'warn', 'message' => 'all required variables present, but .env may not be gitignored'];
        }

        return ['status' => 'ok', 'message' => 'all required variables present'];
    }

    /**
     * The check that matters most: does index.php actually boot? Reuses the
     * same RuntimeBridge/arcos-inspect.php subprocess path inspect:routes and
     * inspect:middleware already rely on.
     *
     * @return array{status: string, message: string, routes: ?array}
     */
    private function checkBoot(): array
    {
        $bridge = new RuntimeBridge();
        $result = $bridge->run('routes', getcwd());

        if (!$result['ok']) {
            return ['status' => 'fail', 'message' => $result['error'], 'routes' => null];
        }

        return ['status' => 'ok', 'message' => 'index.php booted cleanly', 'routes' => $result['data']];
    }

    /**
     * @param array<int, array<string, mixed>> $routes
     * @return array{status: string, message: string}
     */
    private function summarizeRoutes(array $routes): array
    {
        if (empty($routes)) {
            return ['status' => 'warn', 'message' => 'no routes registered'];
        }

        $subdomains  = array_unique(array_map(fn($r) => $r['subdomain'], $routes));
        $routeNoun   = count($routes) === 1 ? 'route' : 'routes';
        $domainNoun  = count($subdomains) === 1 ? 'subdomain' : 'subdomains';

        return [
            'status'  => 'ok',
            'message' => sprintf('%d %s across %d %s', count($routes), $routeNoun, count($subdomains), $domainNoun),
        ];
    }

    /**
     * @return array{status: string, message: string}
     */
    private function checkContainerBindings(): array
    {
        $indexPath = $this->projectPath('index.php');

        if (!file_exists($indexPath)) {
            return ['status' => 'warn', 'message' => 'index.php not found'];
        }

        $bindings = InspectContainer::parseBindings(file_get_contents($indexPath));

        if (empty($bindings)) {
            return ['status' => 'warn', 'message' => 'no bindings declared'];
        }

        $noun = count($bindings) === 1 ? 'binding' : 'bindings';

        return ['status' => 'ok', 'message' => sprintf('%d %s declared', count($bindings), $noun)];
    }

    // Rendering

    /**
     * @param array{status: string, message: string} $result
     */
    private function render(array $result, ?string $label = null): void
    {
        $text = $label !== null ? "{$label} — {$result['message']}" : $result['message'];

        match ($result['status']) {
            'ok'   => Output::success($text),
            'warn' => Output::warn($text),
            'fail' => Output::error($text),
            'skip' => Output::comment("  {$text}"),
        };
    }
}
