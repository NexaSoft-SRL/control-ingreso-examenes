<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

final class ModulePublicApiGuardTest extends TestCase
{
    /** @var list<string> */
    private array $createdFiles = [];

    /** @var list<string> */
    private array $createdDirectories = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->createdFiles) as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        foreach (array_reverse($this->createdDirectories) as $directory) {
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }

        parent::tearDown();
    }

    public function test_only_application_contracts_are_public_between_modules(): void
    {
        $this->writeProbe(
            'app/Modules/Habilitacion/Application/Contracts/ArchitectureProbeContract.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Application\Contracts;

interface ArchitectureProbeContract
{
    public function execute(): bool;
}
PHP
        );

        $this->writeProbe(
            'app/Modules/Ingreso/Application/Actions/AllowedArchitectureProbe.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Habilitacion\Application\Contracts\ArchitectureProbeContract;

final readonly class AllowedArchitectureProbe
{
    public function __construct(
        private ArchitectureProbeContract $contract,
    ) {}

    public function execute(): bool
    {
        return $this->contract->execute();
    }
}
PHP
        );

        $allowed = $this->analyseModules();

        self::assertSame(
            0,
            $allowed->getExitCode(),
            $this->processOutput($allowed)
        );

        $this->writeProbe(
            'app/Modules/Habilitacion/Application/Actions/PrivateArchitectureProbeAction.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Application\Actions;

final class PrivateArchitectureProbeAction
{
    public function execute(): bool
    {
        return true;
    }
}
PHP
        );

        $privateApplication = $this->writeAndAnalyseConsumer(
            'ForbiddenApplicationProbe.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Habilitacion\Application\Actions\PrivateArchitectureProbeAction;

final class ForbiddenApplicationProbe
{
    public function execute(PrivateArchitectureProbeAction $action): bool
    {
        return $action->execute();
    }
}
PHP
        );

        $this->assertPrivateDependencyRejected(
            $privateApplication,
            'Application interno de otro módulo debe ser privado.'
        );

        $this->removeProbe(
            'app/Modules/Ingreso/Application/Actions/ForbiddenApplicationProbe.php'
        );

        $domain = $this->writeAndAnalyseConsumer(
            'ForbiddenDomainProbe.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Administracion\Domain\Models\User;

final class ForbiddenDomainProbe
{
    public function execute(User $user): int|string
    {
        return $user->getKey();
    }
}
PHP
        );

        $this->assertPrivateDependencyRejected(
            $domain,
            'Domain de otro módulo debe ser privado.'
        );

        $this->removeProbe(
            'app/Modules/Ingreso/Application/Actions/ForbiddenDomainProbe.php'
        );

        $this->writeProbe(
            'app/Modules/Habilitacion/Infrastructure/Providers/PrivateArchitectureProbeProvider.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Infrastructure\Providers;

final class PrivateArchitectureProbeProvider {}
PHP
        );

        $infrastructure = $this->writeAndAnalyseConsumer(
            'ForbiddenInfrastructureProbe.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Habilitacion\Infrastructure\Providers\PrivateArchitectureProbeProvider;

final class ForbiddenInfrastructureProbe
{
    public function execute(
        PrivateArchitectureProbeProvider $provider
    ): void {}
}
PHP
        );

        $this->assertPrivateDependencyRejected(
            $infrastructure,
            'Infrastructure de otro módulo debe ser privado.'
        );

        $this->removeProbe(
            'app/Modules/Ingreso/Application/Actions/ForbiddenInfrastructureProbe.php'
        );

        $this->writeProbe(
            'app/Modules/Habilitacion/Http/Controllers/PrivateArchitectureProbeController.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Http\Controllers;

final class PrivateArchitectureProbeController {}
PHP
        );

        $http = $this->writeAndAnalyseConsumer(
            'ForbiddenHttpProbe.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Habilitacion\Http\Controllers\PrivateArchitectureProbeController;

final class ForbiddenHttpProbe
{
    public function execute(
        PrivateArchitectureProbeController $controller
    ): void {}
}
PHP
        );

        $this->assertPrivateDependencyRejected(
            $http,
            'Http de otro módulo debe ser privado.'
        );
    }

    private function assertPrivateDependencyRejected(
        Process $process,
        string $message,
    ): void {
        self::assertNotSame(
            0,
            $process->getExitCode(),
            $message
        );

        self::assertStringContainsString(
            'DependsOnPrivateLayer',
            $this->processOutput($process)
        );
    }

    private function writeAndAnalyseConsumer(
        string $filename,
        string $contents,
    ): Process {
        $this->writeProbe(
            'app/Modules/Ingreso/Application/Actions/'.$filename,
            $contents
        );

        return $this->analyseModules();
    }

    private function writeProbe(string $relativePath, string $contents): void
    {
        $path = $this->root().'/'.$relativePath;

        $this->ensureDirectory(dirname($path));

        if (file_exists($path)) {
            throw new RuntimeException(
                "Ya existe la ruta reservada para el probe: {$relativePath}"
            );
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException(
                "No se pudo crear el probe arquitectónico: {$relativePath}"
            );
        }

        $this->createdFiles[] = $path;
    }

    private function removeProbe(string $relativePath): void
    {
        $path = $this->root().'/'.$relativePath;

        if (is_file($path)) {
            unlink($path);
        }

        $this->createdFiles = array_values(
            array_filter(
                $this->createdFiles,
                static fn (string $file): bool => $file !== $path
            )
        );
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        $parent = dirname($directory);

        if (! is_dir($parent)) {
            $this->ensureDirectory($parent);
        }

        if (! mkdir($directory) && ! is_dir($directory)) {
            throw new RuntimeException(
                "No se pudo crear el directorio temporal: {$directory}"
            );
        }

        $this->createdDirectories[] = $directory;
    }

    private function analyseModules(): Process
    {
        $process = new Process(
            [
                $this->root().'/vendor/bin/deptrac',
                'analyse',
                '--config-file=deptrac.modules.php',
                '--no-cache',
            ],
            $this->root()
        );

        $process->run();

        return $process;
    }

    private function processOutput(Process $process): string
    {
        return $process->getOutput().$process->getErrorOutput();
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
