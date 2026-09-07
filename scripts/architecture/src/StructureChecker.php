<?php

declare(strict_types=1);

namespace NexaSoft\Architecture;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * @phpstan-type ArchitectureContract array{
 *     version: 1,
 *     app_roots: list<string>,
 *     required_app_roots: list<string>,
 *     modules: list<string>,
 *     layers: array<string, list<string>>,
 *     shared_categories: list<string>,
 *     forbidden_directory_names: list<string>,
 *     allowed_app_file_extensions: list<string>,
 *     directory_name_pattern: string,
 *     php_file_name_pattern: string,
 *     forbid_symlinks_in_app: bool
 * }
 */
final class StructureChecker
{
    public function __construct(
        private readonly string $root,
        private readonly string $contractPath,
    ) {}

    /**
     * @return list<string>
     */
    public function check(): array
    {
        $contract = $this->loadContract();
        $appPath = $this->root.'/app';

        if (! is_dir($appPath)) {
            return ['No existe el directorio obligatorio: app'];
        }

        $errors = [];

        $this->checkAppRoot($appPath, $contract, $errors);
        $this->checkModules($appPath, $contract, $errors);
        $this->checkShared($appPath, $contract, $errors);
        $this->checkRecursiveRules($appPath, $contract, $errors);

        $errors = array_values(array_unique($errors));
        sort($errors);

        return $errors;
    }

    /**
     * @return ArchitectureContract
     */
    private function loadContract(): array
    {
        if (! is_file($this->contractPath)) {
            throw new RuntimeException(
                'No existe el contrato arquitectónico: '.$this->contractPath
            );
        }

        $json = file_get_contents($this->contractPath);

        if ($json === false) {
            throw new RuntimeException(
                'No se pudo leer el contrato arquitectónico.'
            );
        }

        try {
            $contract = json_decode(
                $json,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $exception) {
            throw new RuntimeException(
                'El contrato arquitectónico contiene JSON inválido: '
                .$exception->getMessage(),
                previous: $exception
            );
        }

        if (! is_array($contract)) {
            throw new RuntimeException(
                'El contrato arquitectónico debe ser un objeto JSON.'
            );
        }

        $this->validateContract($contract);

        return $contract;
    }

    /**
     * @param  array<array-key, mixed>  $contract
     *
     * @phpstan-assert ArchitectureContract $contract
     */
    private function validateContract(array $contract): void
    {
        $requiredKeys = [
            'version',
            'app_roots',
            'required_app_roots',
            'modules',
            'layers',
            'shared_categories',
            'forbidden_directory_names',
            'allowed_app_file_extensions',
            'directory_name_pattern',
            'php_file_name_pattern',
            'forbid_symlinks_in_app',
        ];

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $contract)) {
                throw new RuntimeException(
                    "Falta la clave obligatoria '{$key}' en el contrato."
                );
            }
        }

        if ($contract['version'] !== 1) {
            throw new RuntimeException(
                'Versión de contrato arquitectónico no soportada.'
            );
        }

        foreach ([
            'app_roots',
            'required_app_roots',
            'modules',
            'shared_categories',
            'forbidden_directory_names',
            'allowed_app_file_extensions',
        ] as $key) {
            $this->assertStringList($contract[$key], $key);
        }

        if (! is_array($contract['layers'])) {
            throw new RuntimeException(
                "La clave 'layers' debe ser un objeto."
            );
        }

        foreach ($contract['layers'] as $layer => $categories) {
            if (! is_string($layer) || $layer === '') {
                throw new RuntimeException(
                    'Cada nombre de capa debe ser una cadena no vacía.'
                );
            }

            $this->assertStringList(
                $categories,
                "layers.{$layer}"
            );
        }

        foreach ($contract['required_app_roots'] as $requiredRoot) {
            if (! in_array($requiredRoot, $contract['app_roots'], true)) {
                throw new RuntimeException(
                    "La raíz obligatoria '{$requiredRoot}' "
                    .'no está incluida en app_roots.'
                );
            }
        }

        if (! is_string($contract['directory_name_pattern'])) {
            throw new RuntimeException(
                'directory_name_pattern debe ser una cadena.'
            );
        }

        if (! is_string($contract['php_file_name_pattern'])) {
            throw new RuntimeException(
                'php_file_name_pattern debe ser una cadena.'
            );
        }

        if (! is_bool($contract['forbid_symlinks_in_app'])) {
            throw new RuntimeException(
                'forbid_symlinks_in_app debe ser booleano.'
            );
        }

        $this->assertValidRegex(
            $contract['directory_name_pattern'],
            'directory_name_pattern'
        );

        $this->assertValidRegex(
            $contract['php_file_name_pattern'],
            'php_file_name_pattern'
        );
    }

    /**
     * @phpstan-assert list<string> $value
     */
    private function assertStringList(mixed $value, string $key): void
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new RuntimeException(
                "La clave '{$key}' debe ser una lista."
            );
        }

        $seen = [];

        foreach ($value as $item) {
            if (! is_string($item) || $item === '') {
                throw new RuntimeException(
                    "La clave '{$key}' contiene un valor inválido."
                );
            }

            if (in_array($item, $seen, true)) {
                throw new RuntimeException(
                    "La clave '{$key}' contiene el valor duplicado '{$item}'."
                );
            }

            $seen[] = $item;
        }
    }

    private function assertValidRegex(string $pattern, string $key): void
    {
        if (@preg_match('~'.$pattern.'~', '') === false) {
            throw new RuntimeException(
                "La expresión regular '{$key}' es inválida."
            );
        }
    }

    /**
     * @param  ArchitectureContract  $contract
     * @param  list<string>  $errors
     */
    private function checkAppRoot(
        string $appPath,
        array $contract,
        array &$errors,
    ): void {
        foreach ($this->childDirectories($appPath) as $directory) {
            if (! in_array($directory, $contract['app_roots'], true)) {
                $errors[] =
                    "Directorio de primer nivel no permitido: app/{$directory}";
            }
        }

        foreach ($this->childFiles($appPath) as $file) {
            $errors[] =
                "Archivo directo en app/ no permitido: app/{$file}";
        }

        foreach ($contract['required_app_roots'] as $requiredRoot) {
            if (! is_dir($appPath.'/'.$requiredRoot)) {
                $errors[] =
                    "Directorio obligatorio ausente: app/{$requiredRoot}";
            }
        }
    }

    /**
     * @param  ArchitectureContract  $contract
     * @param  list<string>  $errors
     */
    private function checkModules(
        string $appPath,
        array $contract,
        array &$errors,
    ): void {
        $modulesPath = $appPath.'/Modules';

        if (! is_dir($modulesPath)) {
            return;
        }

        foreach ($this->childFiles($modulesPath) as $file) {
            $errors[] =
                'Archivo directo en app/Modules no permitido: '
                ."app/Modules/{$file}";
        }

        foreach ($this->childDirectories($modulesPath) as $module) {
            if (! in_array($module, $contract['modules'], true)) {
                $errors[] = "Módulo no autorizado: app/Modules/{$module}";

                continue;
            }

            $modulePath = $modulesPath.'/'.$module;

            foreach ($this->childFiles($modulePath) as $file) {
                $errors[] =
                    'Archivo directo en módulo no permitido: '
                    ."app/Modules/{$module}/{$file}";
            }

            foreach ($this->childDirectories($modulePath) as $layer) {
                if (! array_key_exists($layer, $contract['layers'])) {
                    $errors[] =
                        'Capa no permitida: '
                        ."app/Modules/{$module}/{$layer}";

                    continue;
                }

                $layerPath = $modulePath.'/'.$layer;

                foreach ($this->childFiles($layerPath) as $file) {
                    $errors[] =
                        'Archivo directo en capa no permitido: '
                        ."app/Modules/{$module}/{$layer}/{$file}";
                }

                $allowedCategories = $contract['layers'][$layer];

                foreach (
                    $this->childDirectories($layerPath) as $category
                ) {
                    if (! in_array(
                        $category,
                        $allowedCategories,
                        true
                    )) {
                        $errors[] =
                            'Categoría no permitida: '
                            ."app/Modules/{$module}/"
                            ."{$layer}/{$category}";
                    }
                }
            }
        }
    }

    /**
     * @param  ArchitectureContract  $contract
     * @param  list<string>  $errors
     */
    private function checkShared(
        string $appPath,
        array $contract,
        array &$errors,
    ): void {
        $sharedPath = $appPath.'/Shared';

        if (! is_dir($sharedPath)) {
            return;
        }

        foreach ($this->childFiles($sharedPath) as $file) {
            $errors[] =
                "Archivo directo en Shared no permitido: app/Shared/{$file}";
        }

        foreach ($this->childDirectories($sharedPath) as $category) {
            if (! in_array(
                $category,
                $contract['shared_categories'],
                true
            )) {
                $errors[] =
                    "Categoría Shared no autorizada: app/Shared/{$category}";
            }
        }
    }

    /**
     * @param  ArchitectureContract  $contract
     * @param  list<string>  $errors
     */
    private function checkRecursiveRules(
        string $appPath,
        array $contract,
        array &$errors,
    ): void {
        $forbidden = array_map(
            static fn (string $name): string => strtolower($name),
            $contract['forbidden_directory_names']
        );

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $appPath,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if (! $item instanceof SplFileInfo) {
                throw new RuntimeException(
                    'El recorrido de app/ produjo una entrada inválida.'
                );
            }

            $relative = $this->relativePath($item->getPathname());

            if (
                $contract['forbid_symlinks_in_app']
                && $item->isLink()
            ) {
                $errors[] = "Enlace simbólico no permitido: {$relative}";

                continue;
            }

            if ($item->isDir()) {
                $name = $item->getFilename();

                if (! $this->matches(
                    $contract['directory_name_pattern'],
                    $name
                )) {
                    $errors[] =
                        "Nombre de directorio no permitido: {$relative}";
                }

                if (in_array(strtolower($name), $forbidden, true)) {
                    $errors[] =
                        "Directorio genérico prohibido: {$relative}";
                }

                continue;
            }

            if (! $item->isFile()) {
                continue;
            }

            $extension = '.'.pathinfo(
                $item->getFilename(),
                PATHINFO_EXTENSION
            );

            if (! in_array(
                $extension,
                $contract['allowed_app_file_extensions'],
                true
            )) {
                $errors[] =
                    "Extensión de archivo no permitida en app/: {$relative}";

                continue;
            }

            if (
                $extension === '.php'
                && ! $this->matches(
                    $contract['php_file_name_pattern'],
                    $item->getFilename()
                )
            ) {
                $errors[] =
                    "Nombre de archivo PHP no permitido: {$relative}";
            }
        }
    }

    /**
     * @return list<string>
     */
    private function childDirectories(string $path): array
    {
        return $this->childrenByType($path, true);
    }

    /**
     * @return list<string>
     */
    private function childFiles(string $path): array
    {
        return $this->childrenByType($path, false);
    }

    /**
     * @return list<string>
     */
    private function childrenByType(
        string $path,
        bool $directories,
    ): array {
        if (! is_dir($path)) {
            return [];
        }

        $entries = scandir($path);

        if ($entries === false) {
            throw new RuntimeException(
                "No se pudo leer el directorio: {$path}"
            );
        }

        $result = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = $path.DIRECTORY_SEPARATOR.$entry;

            if (
                $directories
                    ? is_dir($fullPath)
                    : is_file($fullPath)
            ) {
                $result[] = $entry;
            }
        }

        sort($result);

        return $result;
    }

    private function relativePath(string $path): string
    {
        $prefix = rtrim(
            $this->root,
            DIRECTORY_SEPARATOR
        ).DIRECTORY_SEPARATOR;

        return str_starts_with($path, $prefix)
            ? substr($path, strlen($prefix))
            : $path;
    }

    private function matches(string $pattern, string $value): bool
    {
        return preg_match('~'.$pattern.'~', $value) === 1;
    }
}
