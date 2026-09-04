<?php

declare(strict_types=1);

namespace Tests\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class HttpBoundaryGuardTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const FORBIDDEN_NAMESPACE_PREFIXES = [
        'Illuminate\\Http\\',
        'Illuminate\\Routing\\',
        'Symfony\\Component\\HttpFoundation\\',
        'Symfony\\Component\\HttpKernel\\',
    ];

    /**
     * @var list<string>
     */
    private const FORBIDDEN_HTTP_HELPERS = [
        'abort',
        'abort_if',
        'abort_unless',
        'response',
    ];

    public function test_domain_and_application_are_http_agnostic(): void
    {
        $violations = [];

        foreach ($this->guardedPhpFiles() as $file) {
            $source = file_get_contents($file);

            if ($source === false) {
                self::fail("No se pudo leer {$file}");
            }

            foreach ($this->findViolations($source) as $violation) {
                $violations[] = $this->relativePath($file)
                    .': '.$violation;
            }
        }

        self::assertSame(
            [],
            $violations,
            "Dependencias HTTP prohibidas:\n".implode(
                "\n",
                $violations
            )
        );
    }

    public function test_guard_detects_framework_http_dependencies(): void
    {
        $source = <<<'PHP'
<?php

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class Probe
{
    public function execute(): void
    {
        response()->json([]);
        abort(403);
    }
}
PHP;

        $violations = $this->findViolations($source);

        self::assertContains(
            'HTTP framework prohibido en línea 3: '
            .'Illuminate\Http\JsonResponse',
            $violations
        );

        self::assertContains(
            'HTTP framework prohibido en línea 4: '
            .'Symfony\Component\HttpKernel\Exception\HttpException',
            $violations
        );

        self::assertContains(
            'Helper HTTP prohibido en línea 10: response()',
            $violations
        );

        self::assertContains(
            'Helper HTTP prohibido en línea 11: abort()',
            $violations
        );
    }

    public function test_guard_does_not_confuse_methods_with_global_helpers(): void
    {
        $source = <<<'PHP'
<?php

$gateway->response();
Gateway::response();
PHP;

        self::assertSame([], $this->findViolations($source));
    }

    /**
     * @return list<string>
     */
    private function guardedPhpFiles(): array
    {
        $modulesPath = dirname(__DIR__, 2).'/app/Modules';
        $files = [];

        $modules = glob($modulesPath.'/*', GLOB_ONLYDIR);

        if ($modules === false) {
            self::fail('No se pudieron recorrer los módulos.');
        }

        foreach ($modules as $module) {
            foreach (['Domain', 'Application'] as $layer) {
                $layerPath = $module.'/'.$layer;

                if (! is_dir($layerPath)) {
                    continue;
                }

                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator(
                        $layerPath,
                        FilesystemIterator::SKIP_DOTS
                    )
                );

                foreach ($iterator as $item) {
                    if (
                        $item instanceof SplFileInfo
                        && $item->isFile()
                        && strtolower($item->getExtension()) === 'php'
                    ) {
                        $files[] = $item->getPathname();
                    }
                }
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    private function findViolations(string $source): array
    {
        $tokens = token_get_all($source);
        $violations = [];

        foreach ($tokens as $index => $token) {
            if (! is_array($token)) {
                continue;
            }

            [$type, $text, $line] = $token;

            if (
                in_array(
                    $type,
                    [
                        T_NAME_QUALIFIED,
                        T_NAME_FULLY_QUALIFIED,
                        T_NAME_RELATIVE,
                    ],
                    true
                )
            ) {
                $name = ltrim($text, '\\');

                foreach (self::FORBIDDEN_NAMESPACE_PREFIXES as $prefix) {
                    if (str_starts_with($name, $prefix)) {
                        $violations[] =
                            "HTTP framework prohibido en línea {$line}: "
                            .$name;
                    }
                }
            }

            $name = strtolower(ltrim($text, '\\'));

            if (
                ! in_array(
                    $name,
                    self::FORBIDDEN_HTTP_HELPERS,
                    true
                )
            ) {
                continue;
            }

            if ($this->nextSignificantToken($tokens, $index) !== '(') {
                continue;
            }

            $previous = $this->previousSignificantToken(
                $tokens,
                $index
            );

            if (
                is_array($previous)
                && in_array(
                    $previous[0],
                    [
                        T_OBJECT_OPERATOR,
                        T_NULLSAFE_OBJECT_OPERATOR,
                        T_DOUBLE_COLON,
                        T_FUNCTION,
                    ],
                    true
                )
            ) {
                continue;
            }

            $violations[] =
                "Helper HTTP prohibido en línea {$line}: {$name}()";
        }

        $violations = array_values(array_unique($violations));
        sort($violations);

        return $violations;
    }

    /**
     * @param  list<array{int, string, int}|string>  $tokens
     * @return array{int, string, int}|string|null
     */
    private function nextSignificantToken(
        array $tokens,
        int $currentIndex,
    ): array|string|null {
        for (
            $index = $currentIndex + 1;
            $index < count($tokens);
            $index++
        ) {
            if ($this->isInsignificant($tokens[$index])) {
                continue;
            }

            return $tokens[$index];
        }

        return null;
    }

    /**
     * @param  list<array{int, string, int}|string>  $tokens
     * @return array{int, string, int}|string|null
     */
    private function previousSignificantToken(
        array $tokens,
        int $currentIndex,
    ): array|string|null {
        for ($index = $currentIndex - 1; $index >= 0; $index--) {
            if ($this->isInsignificant($tokens[$index])) {
                continue;
            }

            return $tokens[$index];
        }

        return null;
    }

    /**
     * @param  array{int, string, int}|string  $token
     */
    private function isInsignificant(array|string $token): bool
    {
        return is_array($token)
            && in_array(
                $token[0],
                [
                    T_WHITESPACE,
                    T_COMMENT,
                    T_DOC_COMMENT,
                ],
                true
            );
    }

    private function relativePath(string $path): string
    {
        $root = dirname(__DIR__, 2).'/';

        return str_starts_with($path, $root)
            ? substr($path, strlen($root))
            : $path;
    }
}
