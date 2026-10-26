<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Examenes\Application\Contracts\CodigoAulaGateway;
use App\Modules\Examenes\Domain\Enums\TipoExamen;
use App\Modules\Examenes\Domain\Models\ExamenAulaCodigo;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final readonly class EloquentCodigoAulaGateway implements CodigoAulaGateway
{
    /**
     * @param  (Closure(): string)|null  $sortear  quien elige el código; las pruebas lo fijan
     */
    public function __construct(
        private BitacoraGateway $bitacora,
        private ?Closure $sortear = null,
    ) {}

    public function aulaPerteneceAlExamen(int $examenId, int $aulaId): bool
    {
        return DB::table('examen_aula')
            ->where('examen_id', $examenId)
            ->where('aula_id', $aulaId)
            ->exists();
    }

    public function consultar(int $examenId, int $aulaId): array
    {
        /** @var object{codigo: string, generado_en: string}|null $registro */
        $registro = DB::table('examen_aula_codigos')
            ->where('examen_id', $examenId)
            ->where('aula_id', $aulaId)
            ->select(['codigo', 'generado_en'])
            ->first();

        return [
            'codigo' => $registro?->codigo,
            'generado_en' => $registro?->generado_en,
        ];
    }

    public function generar(int $examenId, int $aulaId, int $usuarioId): array
    {
        $fecha = $this->fechaDelExamen($examenId);

        // El código no se repite entre las aulas de exámenes del mismo día:
        // con él se encuentra el aula al entrar.
        do {
            $codigo = $this->sortear !== null
                ? ($this->sortear)()
                : str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $enUso = DB::table('examen_aula_codigos as c')
                ->join('examenes as e', 'e.id', '=', 'c.examen_id')
                ->where('c.codigo', $codigo)
                ->where(function (Builder $q) use ($examenId, $aulaId): void {
                    $q->where('c.examen_id', '!=', $examenId)
                        ->orWhere('c.aula_id', '!=', $aulaId);
                })
                ->whereDate('e.fecha', $fecha)
                ->exists();
        } while ($enUso);

        /** @var ExamenAulaCodigo|null $existente */
        $existente = ExamenAulaCodigo::where('examen_id', $examenId)
            ->where('aula_id', $aulaId)
            ->first();

        if ($existente instanceof ExamenAulaCodigo) {
            DB::table('accesos_codigo_aula')
                ->where('examen_id', $examenId)
                ->where('aula_id', $aulaId)
                ->update(['revocado_en' => now()]);

            $existente->codigo = $codigo;
            $existente->generado_en = now();
            $existente->save();

            $this->bitacora->registrar(
                $usuarioId,
                'examen_aula_codigo.regenerar',
                'examen_aula_codigos',
                $existente->id,
                sprintf('Código regenerado: %s.', $this->describir($examenId, $aulaId)),
            );

            return [
                'codigo' => $codigo,
                'generado_en' => (string) $existente->generado_en,
                'regenerado' => true,
            ];
        }

        $nuevo = ExamenAulaCodigo::create([
            'examen_id' => $examenId,
            'aula_id' => $aulaId,
            'codigo' => $codigo,
            'generado_en' => now()->toDateTimeString(),
        ]);

        $this->bitacora->registrar(
            $usuarioId,
            'examen_aula_codigo.generar',
            'examen_aula_codigos',
            $nuevo->id,
            sprintf('Código generado: %s.', $this->describir($examenId, $aulaId)),
        );

        return [
            'codigo' => $codigo,
            'generado_en' => (string) $nuevo->generado_en,
            'regenerado' => false,
        ];
    }

    private function fechaDelExamen(int $examenId): string
    {
        $fecha = DB::table('examenes')->where('id', $examenId)->value('fecha');

        return is_string($fecha) ? substr($fecha, 0, 10) : now()->toDateString();
    }

    /**
     * «Primer parcial · Cálculo I, aula 691A», para la bitácora.
     */
    private function describir(int $examenId, int $aulaId): string
    {
        /** @var object{tipo: string|null, asignatura: string|null}|null $examen */
        $examen = DB::table('examenes as e')
            ->leftJoin('asignaturas as a', 'a.id', '=', 'e.asignatura_id')
            ->where('e.id', $examenId)
            ->first(['e.tipo', 'a.nombre as asignatura']);

        $aula = DB::table('aulas')->where('id', $aulaId)->value('nombre');

        $tipo = TipoExamen::tryFrom((string) ($examen->tipo ?? ''))?->etiqueta();
        $titulo = implode(' · ', array_filter(
            [$tipo, $examen->asignatura ?? null],
            static fn (?string $parte): bool => $parte !== null && $parte !== '',
        ));

        return sprintf(
            '%s, aula %s',
            $titulo === '' ? 'examen' : $titulo,
            is_string($aula) ? $aula : '—',
        );
    }
}
