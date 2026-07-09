<?php

declare(strict_types=1);

namespace Arcos\Cli\Tests\Fixtures;

use Arcos\Cli\Commands\Make\MakeController;
use Arcos\Cli\Commands\Make\MakeMiddleware;
use Arcos\Cli\Commands\Make\MakeModel;
use Arcos\Cli\Commands\Make\MakeService;
use Arcos\Cli\Tests\Concerns\UsesTempProject;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;

/**
 * Exact-content snapshots of one representative stub per make:* command.
 * Per docs/arcos-guidelines.md §10, a Fixtures-tier failure signals wording
 * drift worth a human look — it is advisory, not a build blocker.
 */
class MakeStubSnapshotTest extends TestCase
{
    use UsesTempProject;

    #[After]
    public function cleanup(): void
    {
        if (isset($this->tempProjectDir)) {
            $this->cleanupTempProjectDir();
        }
    }

    public function test_make_controller_stub_matches_the_known_baseline(): void
    {
        $dir = $this->createTempProjectDir();
        $this->captureOutput(fn() => (new MakeController())->handle(['Products'], []));

        $expected = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Controllers;

        use Arcos\Core\Helpers\ErrorHelper;
        use Arcos\Core\Helpers\ResponseHelper;
        use Arcos\Core\Http\Request;
        use Arcos\Core\Http\Response;
        class ProductsController
        {
            public function __construct(
                // Inject services here
            ) {}

            public function index(Request $request): Response
            {
                return ResponseHelper::ok([]);
            }
        }
        PHP;

        $this->assertSame($expected, file_get_contents($dir . '/app/Controllers/ProductsController.php'));
    }

    public function test_make_service_stub_matches_the_known_baseline(): void
    {
        $dir = $this->createTempProjectDir();
        $this->captureOutput(fn() => (new MakeService())->handle(['Inventory'], []));

        $expected = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Services;

        use Arcos\Services\BaseService;

        class InventoryService extends BaseService
        {
            protected string $baseUrl = '';
            protected int    $timeout = 5;

            public function health(): array
            {
                $response = $this->get('/health');

                return $response['status'] === 'ok'
                    ? $this->ok()
                    : $this->down('InventoryService health check failed.');
            }
        }
        PHP;

        $this->assertSame($expected, file_get_contents($dir . '/app/Services/InventoryService.php'));
    }

    public function test_make_middleware_default_stub_matches_the_known_baseline(): void
    {
        $dir = $this->createTempProjectDir();
        $this->captureOutput(fn() => (new MakeMiddleware())->handle(['Auth'], []));

        $expected = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Middleware;

        use Arcos\Core\Http\Request;
        use Arcos\Core\Http\Response;
        use Arcos\Core\Middleware\MiddlewareInterface;

        class AuthMiddleware implements MiddlewareInterface
        {
            public function handle(Request $request, callable $next): Response
            {
                // Inspect or validate the request here.
                // Return ErrorHelper::respond('...') to short-circuit.
                // Call $next($request) to continue the chain.

                return $next($request);
            }
        }
        PHP;

        $this->assertSame($expected, file_get_contents($dir . '/app/Middleware/AuthMiddleware.php'));
    }

    public function test_make_model_stub_matches_the_known_baseline(): void
    {
        $dir = $this->createTempProjectDir();
        $this->captureOutput(fn() => (new MakeModel())->handle(['Product'], []));

        $expected = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Models;

        class Product
        {
            // Encapsulate data access here.
            // Models read and write data. They do not contain business logic.
        }
        PHP;

        $this->assertSame($expected, file_get_contents($dir . '/app/Models/Product.php'));
    }
}
