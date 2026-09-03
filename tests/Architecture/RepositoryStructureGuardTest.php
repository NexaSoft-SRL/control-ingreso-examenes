<?php

declare(strict_types=1);

namespace Tests\Architecture;

use FilesystemIterator;
use NexaSoft\Architecture\StructureChecker;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

require_once dirname(__DIR__, 2)
    .'/scripts/architecture/src/StructureChecker.php';

final class RepositoryStructureGuardTest extends TestCase
{
    private string $fixtureRoot;

    private string $contractPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureRoot = sys_get_temp_dir()
            .'/nexasoft-architecture-'
            .bin2hex(random_bytes(8));

        $this->contractPath = dirname(__DIR__, 2)
            .'/docs/architecture/architecture-contract.json';

        $this->makeDirectory('app/Modules');
        $this->makeDirectory('app/Providers');
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->fixtureRoot);

        parent::tearDown();
    }

    public function test_current_repository_respects_the_contract(): void
    {
        $repositoryRoot = dirname(__DIR__, 2);

        $checker = new StructureChecker(
            $repositoryRoot,
            $repositoryRoot
                .'/docs/architecture/architecture-contract.json'
        );

        $this->assertSame([], $checker->check());
    }

    public function test_minimum_valid_structure_is_accepted(): void
    {
        $this->assertSame([], $this->errors());
    }

    public function test_unexpected_app_root_directory_is_rejected(): void
    {
        $this->makeDirectory('app/Helpers');

        $this->assertErrorsContain(
            'Directorio de primer nivel no permitido: app/Helpers'
        );
    }

    public function test_file_directly_inside_app_is_rejected(): void
    {
        $this->makeFile('app/Foo.php');

        $this->assertErrorsContain(
            'Archivo directo en app/ no permitido: app/Foo.php'
        );
    }

    public function test_unknown_module_is_rejected(): void
    {
        $this->makeDirectory('app/Modules/Inventado');

        $this->assertErrorsContain(
            'Módulo no autorizado: app/Modules/Inventado'
        );
    }

    public function test_unknown_layer_is_rejected(): void
    {
        $this->makeDirectory(
            'app/Modules/Administracion/Services'
        );

        $this->assertErrorsContain(
            'Capa no permitida: '
            .'app/Modules/Administracion/Services'
        );
    }

    public function test_unknown_category_is_rejected(): void
    {
        $this->makeDirectory(
            'app/Modules/Administracion/Domain/Foo'
        );

        $this->assertErrorsContain(
            'Categoría no permitida: '
            .'app/Modules/Administracion/Domain/Foo'
        );
    }

    public function test_unapproved_shared_category_is_rejected(): void
    {
        $this->makeDirectory('app/Shared/Foo');

        $this->assertErrorsContain(
            'Categoría Shared no autorizada: app/Shared/Foo'
        );
    }

    public function test_forbidden_name_is_case_insensitive(): void
    {
        $this->makeDirectory(
            'app/Modules/Administracion/Domain/Models/helpers'
        );

        $this->assertErrorsContain(
            'Directorio genérico prohibido: '
            .'app/Modules/Administracion/Domain/Models/helpers'
        );
    }

    public function test_non_php_file_inside_app_is_rejected(): void
    {
        $this->makeDirectory(
            'app/Modules/Administracion/Domain/Models'
        );

        $this->makeFile(
            'app/Modules/Administracion/Domain/Models/Readme.txt'
        );

        $this->assertErrorsContain(
            'Extensión de archivo no permitida en app/: '
            .'app/Modules/Administracion/Domain/Models/Readme.txt'
        );
    }

    public function test_missing_required_app_root_is_rejected(): void
    {
        rmdir($this->fixtureRoot.'/app/Providers');

        $this->assertErrorsContain(
            'Directorio obligatorio ausente: app/Providers'
        );
    }

    public function test_symlink_inside_app_is_rejected(): void
    {
        if (! function_exists('symlink')) {
            $this->markTestSkipped(
                'La plataforma no permite crear enlaces simbólicos.'
            );
        }

        $this->makeDirectory(
            'app/Modules/Administracion/Domain/Models'
        );

        $target = $this->fixtureRoot.'/External';
        mkdir($target);

        $link = $this->fixtureRoot
            .'/app/Modules/Administracion/Domain/Models/Linked';

        if (! symlink($target, $link)) {
            $this->markTestSkipped(
                'No fue posible crear el enlace simbólico.'
            );
        }

        $this->assertErrorsContain(
            'Enlace simbólico no permitido: '
            .'app/Modules/Administracion/Domain/Models/Linked'
        );
    }

    /**
     * @return list<string>
     */
    private function errors(): array
    {
        $checker = new StructureChecker(
            $this->fixtureRoot,
            $this->contractPath
        );

        return $checker->check();
    }

    private function assertErrorsContain(string $expected): void
    {
        $errors = $this->errors();

        $this->assertContains(
            $expected,
            $errors,
            'Errores obtenidos: '.implode(' | ', $errors)
        );
    }

    private function makeDirectory(string $relativePath): void
    {
        $path = $this->fixtureRoot.'/'.$relativePath;

        if (
            ! is_dir($path)
            && ! mkdir($path, 0777, true)
            && ! is_dir($path)
        ) {
            self::fail(
                "No se pudo crear el directorio de prueba: {$path}"
            );
        }
    }

    private function makeFile(string $relativePath): void
    {
        $path = $this->fixtureRoot.'/'.$relativePath;
        $parent = dirname($path);

        if (! is_dir($parent)) {
            $this->makeDirectory(
                substr(
                    $parent,
                    strlen($this->fixtureRoot) + 1
                )
            );
        }

        if (file_put_contents($path, '') === false) {
            self::fail(
                "No se pudo crear el archivo de prueba: {$path}"
            );
        }
    }

    private function removeTree(string $path): void
    {
        if (! file_exists($path) && ! is_link($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $path,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isLink() || $item->isFile()) {
                unlink($item->getPathname());

                continue;
            }

            rmdir($item->getPathname());
        }

        rmdir($path);
    }
}
