<?php

use App\Modules\Academico\Application\Actions\DetectarPeriodos;
use App\Modules\Academico\Application\Actions\ImportarOferta;
use App\Modules\Academico\Application\Contracts\FuenteOfertaGateway;
use App\Modules\Academico\Domain\Exceptions\FuenteNoDisponibleException;
use App\Modules\Administracion\Application\Actions\GenerarRespaldo;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Los periodos salen de los archivos de oferta y de los calendarios de
 * `database/umss/fuentes.json`. El estado no se guarda: se calcula con la
 * fecha de hoy en la zona de la universidad.
 */
Artisan::command('periodos:detectar', function (DetectarPeriodos $detectar) {
    $resultado = $detectar->execute();

    $zona = config('umss.zona_horaria');
    $hoy = now(is_string($zona) ? $zona : 'America/La_Paz')->toDateString();
    $filas = [];

    foreach ($resultado->periodos as $periodo) {
        $estado = match (true) {
            $periodo->fechaInicio === null || $periodo->fechaFin === null => 'Sin fechas',
            $hoy < $periodo->fechaInicio => 'Próximo',
            $hoy > $periodo->fechaFin => 'Cerrado',
            default => 'Vigente',
        };

        $filas[] = [
            $periodo->codigo,
            $periodo->tipoEtiqueta,
            $periodo->fechaInicio ?? '—',
            $periodo->fechaFin ?? '—',
            $estado,
        ];
    }

    $this->table(['Período', 'Tipo', 'Inicio', 'Fin', 'Estado'], $filas);

    $this->info(sprintf(
        '%d creados, %d actualizados.',
        $resultado->creados,
        $resultado->actualizados,
    ));
})->purpose('Detecta los periodos academicos en las fuentes');

/*
 * Importa la oferta de una facultad (fcyt, fce, fhce, fach) o de las
 * cuatro. Sale con codigo 1 si alguna falla.
 */
Artisan::command('oferta:importar {facultad? : fcyt, fce, fhce o fach; sin ella, las cuatro}', function (
    ImportarOferta $importar,
    FuenteOfertaGateway $fuente,
) {
    $pedida = $this->argument('facultad');
    $facultades = is_string($pedida) && $pedida !== '' ? [$pedida] : $fuente->facultades();
    $fallo = false;

    foreach ($facultades as $facultad) {
        try {
            $resumen = $importar->execute($facultad);
        } catch (FuenteNoDisponibleException $error) {
            $this->error($error->getMessage());
            $fallo = true;

            continue;
        }

        if (! $resumen->importada) {
            $this->error(sprintf('%s: %s', $resumen->sigla, (string) $resumen->error));
            $fallo = true;

            continue;
        }

        $this->info(sprintf(
            '%s: %d carreras, %d asignaturas, %d grupos (%d sin docente), %d docentes, %d aulas, %d sesiones descartadas.',
            $resumen->sigla,
            $resumen->carreras,
            $resumen->asignaturas,
            $resumen->grupos,
            $resumen->gruposSinDocente,
            $resumen->docentes,
            $resumen->aulas,
            $resumen->sesionesDescartadas,
        ));
    }

    return $fallo ? 1 : 0;
})->purpose('Importa la oferta academica de GENDA');

/*
 * HU-25: Genera un respaldo de la base de datos (por defecto tipo programado)
 * y lo registra en disco seguro y en el historial.
 */
Artisan::command('respaldo:generar {--tipo=programado : Tipo de respaldo: programado o puntual} {--incompatible : Generar respaldo con versión de esquema incompatible para pruebas}', function (
    GenerarRespaldo $generar,
) {
    if ((bool) $this->option('incompatible')) {
        return (int) $this->call('respaldo:incompatible');
    }

    $opcion = $this->option('tipo');
    $tipo = is_string($opcion) && $opcion !== '' ? $opcion : 'programado';

    try {
        $respaldo = $generar->execute($tipo, null);
        $this->info(sprintf(
            'Respaldo %s generado exitosamente: %s (%d bytes).',
            $respaldo->tipo,
            $respaldo->archivo,
            $respaldo->tamanoBytes,
        ));

        return 0;
    } catch (Throwable $error) {
        $this->error(sprintf('Error al generar respaldo: %s', $error->getMessage()));

        return 1;
    }
})->purpose('Genera un respaldo de la base de datos');

/*
 * HU-25 / CDI-26: Provee un respaldo con version de esquema anterior incompatible
 * para que el equipo de QA pueda verificar el criterio de rechazo de restauracion.
 */
Artisan::command('respaldo:incompatible', function () {
    $discoNombre = is_string(config('respaldo.disco')) ? config('respaldo.disco') : 'local';
    $directorio = is_string(config('respaldo.directorio')) ? config('respaldo.directorio') : 'respaldos';
    $disco = Storage::disk($discoNombre);

    $nombreArchivo = 'respaldo-20260901-000000.sql';
    $ruta = trim($directorio, '/').'/'.$nombreArchivo;
    $contenido = "-- Respaldo de esquema anterior incompatible (CDI-26)\n-- Version: 2026_09_01_000000_legacy_v0\nSELECT 1;\n";

    $disco->put($ruta, $contenido);

    DB::table('respaldos')->updateOrInsert(
        ['archivo' => $nombreArchivo],
        [
            'tipo' => 'programado',
            'tamano_bytes' => strlen($contenido),
            'estado' => 'correcto',
            'version_esquema' => '2026_09_01_000000_legacy_v0',
            'error' => null,
            'created_at' => '2026-09-01 00:00:00',
            'updated_at' => '2026-09-01 00:00:00',
        ]
    );

    $this->info("Respaldo incompatible registrado para pruebas: {$nombreArchivo}");

    return 0;
})->purpose('Crea un respaldo con versión de esquema incompatible para pruebas de restauración (CDI-26)');

Schedule::command('respaldo:generar')->dailyAt('03:00');
