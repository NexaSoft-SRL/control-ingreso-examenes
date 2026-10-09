<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Examenes\Application\Contracts\PlantillaNormaGateway;
use App\Modules\Examenes\Application\DTOs\PlantillaNormaData;
use App\Modules\Examenes\Domain\Models\PlantillaNorma;
use Illuminate\Support\Facades\DB;

final class EloquentPlantillaNormaGateway implements PlantillaNormaGateway
{
    private const TABLA = 'plantillas_norma';

    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

    public function visiblesPara(int $usuarioId): array
    {
        $plantillas = PlantillaNorma::whereNull('usuario_id')
            ->orWhere('usuario_id', $usuarioId)
            ->orderByRaw('case when usuario_id is null then 0 else 1 end')
            ->orderBy('id')
            ->get();

        $datos = [];

        foreach ($plantillas as $plantilla) {
            $datos[] = $this->dato($plantilla);
        }

        return $datos;
    }

    public function buscar(int $plantillaId): ?PlantillaNormaData
    {
        $plantilla = PlantillaNorma::find($plantillaId);

        return $plantilla instanceof PlantillaNorma ? $this->dato($plantilla) : null;
    }

    public function crear(int $usuarioId, string $texto): PlantillaNormaData
    {
        return DB::transaction(function () use ($usuarioId, $texto): PlantillaNormaData {
            $plantilla = PlantillaNorma::create([
                'usuario_id' => $usuarioId,
                'texto' => $texto,
            ]);

            $this->bitacora->registrar(
                $usuarioId,
                'norma.plantilla_crear',
                self::TABLA,
                $plantilla->id,
                "Plantilla de norma creada: «{$texto}».",
            );

            return $this->dato($plantilla);
        }, 3);
    }

    public function editar(int $plantillaId, string $texto, int $usuarioId): ?PlantillaNormaData
    {
        return DB::transaction(function () use ($plantillaId, $texto, $usuarioId): ?PlantillaNormaData {
            $plantilla = PlantillaNorma::whereKey($plantillaId)->lockForUpdate()->first();

            if (! $plantilla instanceof PlantillaNorma) {
                return null;
            }

            $anterior = $plantilla->texto;

            // Solo cambia la plantilla: `examen_norma.texto` es una copia y
            // los examenes ya registrados la conservan.
            $plantilla->texto = $texto;
            $plantilla->save();

            $this->bitacora->registrar(
                $usuarioId,
                'norma.plantilla_editar',
                self::TABLA,
                $plantillaId,
                "Plantilla de norma editada: «{$anterior}» pasa a «{$texto}».",
            );

            return $this->dato($plantilla);
        }, 3);
    }

    public function quitar(int $plantillaId, int $usuarioId): bool
    {
        return DB::transaction(function () use ($plantillaId, $usuarioId): bool {
            $plantilla = PlantillaNorma::whereKey($plantillaId)->lockForUpdate()->first();

            if (! $plantilla instanceof PlantillaNorma) {
                return false;
            }

            $texto = $plantilla->texto;

            // La clave de `examen_norma.plantilla_id` queda nula y el texto
            // del examen se conserva.
            $plantilla->delete();

            $this->bitacora->registrar(
                $usuarioId,
                'norma.plantilla_quitar',
                self::TABLA,
                $plantillaId,
                "Plantilla de norma quitada: «{$texto}».",
            );

            return true;
        }, 3);
    }

    private function dato(PlantillaNorma $plantilla): PlantillaNormaData
    {
        return new PlantillaNormaData(
            id: $plantilla->id,
            texto: $plantilla->texto,
            usuarioId: $plantilla->usuario_id,
        );
    }
}
