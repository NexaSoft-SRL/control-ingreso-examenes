<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Ingreso;

use App\Modules\Estudiantes\Application\Contracts\InscritosDeExamenGateway;
use App\Modules\Estudiantes\Application\Contracts\PadronGateway;
use App\Modules\Estudiantes\Application\DTOs\EstudianteData;
use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Examenes\Application\DTOs\ExamenActualData;
use App\Modules\Habilitacion\Application\Contracts\HabilitacionGateway;
use App\Modules\Habilitacion\Application\DTOs\HabilitacionData;
use App\Modules\Ingreso\Application\Contracts\EstadoExpulsionGateway;
use App\Modules\Ingreso\Application\Contracts\IngresoGateway;
use App\Modules\Ingreso\Application\Queries\ConsultarCandidato;
use App\Modules\Ingreso\Application\Queries\ResolverExamenEnPuerta;
use App\Modules\Ingreso\Domain\Rules\EvaluarIngreso;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResolverExamenEnPuertaTest extends TestCase
{
    /**
     * Cada estado representa un examen:
     * inscrito, habilitado, aula, ingreso previo, expulsado.
     *
     * @return array<string, array{array<int, array{inscrito: bool, habilitado: bool|null, aula: int|null, yaIngreso: bool, expulsado: bool}>, int|null}>
     */
    public static function escenarios(): array
    {
        return [
            'un examen admisible' => [
                [
                    5 => self::estado(true, true, 30),
                ],
                5,
            ],
            'no inscrito en ningun examen' => [
                [
                    5 => self::estado(false, null, null),
                    6 => self::estado(false, null, null),
                ],
                null,
            ],
            'solo inscrito en el segundo' => [
                [
                    5 => self::estado(false, null, null),
                    6 => self::estado(true, true, 30),
                ],
                6,
            ],
            'prioriza el examen que admite' => [
                [
                    5 => self::estado(true, false, null),
                    6 => self::estado(true, true, 30),
                ],
                6,
            ],
            'prioriza el aula correcta' => [
                [
                    5 => self::estado(true, true, 40),
                    6 => self::estado(true, true, 30),
                ],
                6,
            ],
            'prioriza el que no tiene ingreso previo' => [
                [
                    5 => self::estado(true, true, 30, true),
                    6 => self::estado(true, true, 30),
                ],
                6,
            ],
            'no readmite al expulsado' => [
                [
                    5 => self::estado(true, true, 30, true, true),
                    6 => self::estado(true, true, 30),
                ],
                6,
            ],
            'mantiene examen unico no habilitado' => [
                [
                    5 => self::estado(true, false, null),
                    6 => self::estado(false, null, null),
                ],
                5,
            ],
            // El aula del reparto es una sugerencia: el habilitado de otra
            // aula entra por esta.
            'prefiere al habilitado de otra aula sobre el no habilitado' => [
                [
                    5 => self::estado(true, false, null),
                    6 => self::estado(true, true, 40),
                ],
                6,
            ],
            'admite al habilitado sin aula asignada' => [
                [
                    5 => self::estado(true, true, null),
                ],
                5,
            ],
        ];
    }

    /**
     * @return array{inscrito: bool, habilitado: bool|null, aula: int|null, yaIngreso: bool, expulsado: bool}
     */
    private static function estado(
        bool $inscrito,
        ?bool $habilitado,
        ?int $aula,
        bool $yaIngreso = false,
        bool $expulsado = false,
    ): array {
        return compact(
            'inscrito',
            'habilitado',
            'aula',
            'yaIngreso',
            'expulsado',
        );
    }

    /**
     * @param  array<int, array{inscrito: bool, habilitado: bool|null, aula: int|null, yaIngreso: bool, expulsado: bool}>  $estados
     */
    private function crearResolver(array $estados): ResolverExamenEnPuerta
    {
        $estudiante = new EstudianteData(
            17,
            '20260017',
            '9876543',
            'Ana',
            'Pérez',
            true,
        );

        $padron = $this->createMock(PadronGateway::class);
        $padron->method('porCodigos')
            ->willReturn(['20260017' => $estudiante]);
        $padron->method('codigosPorDocumento')
            ->willReturn(['9876543' => '20260017']);

        $examenes = $this->createMock(ExamenGateway::class);
        $examenes->method('actual')
            ->willReturnCallback(
                static fn (int $id): ?ExamenActualData => isset($estados[$id])
                    ? new ExamenActualData(
                        $id,
                        1,
                        2,
                        'PARCIAL',
                        '2026-10-20',
                        '08:00:00',
                        120,
                        [$id + 100],
                        [30],
                    )
                    : null
            );

        $inscritos = $this->createMock(InscritosDeExamenGateway::class);
        $inscritos->method('estudiantesDe')
            ->willReturnCallback(
                static function (array $grupos) use (
                    $estados,
                    $estudiante,
                ): array {
                    $grupoId = $grupos[0] ?? null;
                    if (! is_int($grupoId)) {
                        return [];
                    }
                    $id = $grupoId - 100;

                    return ($estados[$id]['inscrito'] ?? false)
                        ? [$estudiante]
                        : [];
                }
            );

        $habilitaciones = $this->createMock(HabilitacionGateway::class);
        $habilitaciones->method('condicionDe')
            ->willReturnCallback(
                static function (int $id) use ($estados): ?HabilitacionData {
                    $estado = $estados[$id];

                    if ($estado['habilitado'] === null) {
                        return null;
                    }

                    return new HabilitacionData(
                        $id,
                        17,
                        $estado['habilitado'],
                        $estado['aula'],
                        null,
                        null,
                        null,
                        null,
                        null,
                    );
                }
            );

        $ingresos = $this->createMock(IngresoGateway::class);
        $ingresos->method('yaIngreso')
            ->willReturnCallback(
                static fn (int $id): bool => $estados[$id]['yaIngreso']
            );

        $expulsiones = $this->createMock(EstadoExpulsionGateway::class);
        $expulsiones->method('estaExpulsado')
            ->willReturnCallback(
                static fn (int $id): bool => $estados[$id]['expulsado']
            );

        return new ResolverExamenEnPuerta(
            new ConsultarCandidato(
                $padron,
                $inscritos,
                $examenes,
                $habilitaciones,
            ),
            new EvaluarIngreso,
            $ingresos,
            $expulsiones,
        );
    }

    /**
     * @param  array<int, array{inscrito: bool, habilitado: bool|null, aula: int|null, yaIngreso: bool, expulsado: bool}>  $estados
     */
    #[DataProvider('escenarios')]
    public function test_resuelve_examen_correcto(
        array $estados,
        ?int $esperado,
    ): void {
        $resolver = $this->crearResolver($estados);

        self::assertSame(
            $esperado,
            $resolver->resolverManual(
                array_keys($estados),
                30,
                'codigo',
                '20260017',
            ),
        );
    }

    public function test_resuelve_por_documento(): void
    {
        $resolver = $this->crearResolver([
            5 => self::estado(true, true, 30),
        ]);

        self::assertSame(
            5,
            $resolver->resolverManual(
                [5],
                30,
                'documento',
                '9876543',
            ),
        );
    }

    public function test_rechaza_lista_vacia(): void
    {
        $resolver = $this->crearResolver([]);

        $this->expectException(InvalidArgumentException::class);

        $resolver->resolverManual([], 30, 'codigo', '20260017');
    }

    public function test_rechaza_tipo_invalido(): void
    {
        $resolver = $this->crearResolver([]);

        $this->expectException(InvalidArgumentException::class);

        $resolver->resolverManual([5], 30, 'qr', '20260017');
    }

    public function test_rechaza_examen_invalido(): void
    {
        $resolver = $this->crearResolver([]);

        $this->expectException(InvalidArgumentException::class);

        $resolver->resolverManual([0], 30, 'codigo', '20260017');
    }
}
