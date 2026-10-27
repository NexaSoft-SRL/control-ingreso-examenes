<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las incidencias en un solo modelo, como en el Proyecto Guia: lo que
 * rechaza la puerta (`incidencias_ingreso`), lo que reporta una persona
 * (`incidencias_aula`) y las expulsiones (`expulsiones`) pasan a
 * `incidencias`, con su tipo del catalogo, su origen (persona o sistema),
 * la evidencia con foto y la correccion del docente.
 *
 * No se pierde nada: cada fila convertida guarda de donde vino
 * (`legado_tabla`, `legado_id`) y su tipo anterior (`legado_tipo`); el
 * detalle de los tipos que no estan en el catalogo nuevo lleva delante su
 * etiqueta («Conducta · …»). Asi la bitacora vieja sigue encontrando sus
 * incidencias y `down()` deja las tres tablas como estaban.
 *
 * `intentos_punto_control` se queda: es la huella (HMAC) de identificadores
 * que no se pudieron atribuir a nadie, no una incidencia de un examen.
 */
return new class extends Migration
{
    private const ETIQUETAS_AULA = [
        'INCIDENTE_GENERAL' => 'Incidente general',
        'DOCUMENTO_OBSERVADO' => 'Documento observado',
        'CONDUCTA' => 'Conducta',
        'EXCEPCION_IDENTIFICACION' => 'Excepción: problema de identificación',
        'EXCEPCION_NO_HABILITADO' => 'Excepción: estudiante no habilitado',
    ];

    private const TIPOS_RECHAZO = [
        'NO_HABILITADO',
        'OTRA_AULA',
        'YA_INGRESO',
        'NO_CORRESPONDE',
        'EXPULSADO',
        'NO_INSCRITO',
        'NO_REGISTRADO',
        'QR_NO_VALIDO',
    ];

    public function up(): void
    {
        Schema::create('incidencias', function (Blueprint $table): void {
            $table->id();

            // Nulo solo en intentos que la puerta no pudo atribuir a un
            // examen del aula.
            $table->foreignId('examen_id')
                ->nullable()
                ->constrained('examenes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('aula_id')
                ->nullable()
                ->constrained('aulas')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('estudiante_id')
                ->nullable()
                ->constrained('estudiantes')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // Lo que escribio quien reporta cuando no se lo identifico.
            $table->string('estudiante_texto', 150)->nullable();

            $table->foreignId('ingreso_id')
                ->nullable()
                ->constrained('ingresos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('tipo', 30);

            // PERSONA o SISTEMA.
            $table->string('origen', 8);

            // Solo en las del sistema: por que la puerta no lo dejo entrar.
            $table->string('causa', 20)->nullable();

            $table->text('detalle');

            // Ruta de la foto en el disco privado.
            $table->string('evidencia', 200)->nullable();

            $table->timestamp('ocurrida_en');

            // Nulo si la reporto una sesion con codigo de aula.
            $table->foreignId('reportada_por')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('acceso_codigo_aula_id')
                ->nullable()
                ->constrained('accesos_codigo_aula')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->timestamp('atendida_en')->nullable();

            $table->foreignId('atendida_por')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->timestamp('corregida_en')->nullable();

            $table->foreignId('corregida_por')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // De donde vino una fila convertida del modelo anterior.
            $table->string('legado_tabla', 30)->nullable();
            $table->unsignedBigInteger('legado_id')->nullable();
            $table->string('legado_tipo', 60)->nullable();

            $table->timestamps();

            $table->index(['examen_id', 'atendida_en'], 'idx_incidencia_examen_atendida');
            $table->index(['examen_id', 'aula_id'], 'idx_incidencia_examen_aula');
            $table->index(['aula_id', 'ocurrida_en'], 'idx_incidencia_aula_hora');
            $table->unique(['legado_tabla', 'legado_id'], 'uq_incidencia_legado');
        });

        if (DB::getDriverName() === 'pgsql') {
            // Un ingreso se expulsa una sola vez.
            DB::statement(
                "CREATE UNIQUE INDEX uq_expulsion_por_ingreso
                 ON incidencias (ingreso_id)
                 WHERE tipo = 'EXPULSION'"
            );

            // Expulsar exige haber entrado.
            DB::statement(
                "ALTER TABLE incidencias
                 ADD CONSTRAINT chk_incidencia_expulsion_con_ingreso
                 CHECK (tipo <> 'EXPULSION' OR ingreso_id IS NOT NULL)"
            );

            DB::statement(
                'ALTER TABLE incidencias
                 ADD CONSTRAINT chk_incidencia_detalle
                 CHECK (length(btrim(detalle)) > 0)'
            );
        }

        $this->convertirRechazos();
        $this->convertirReportadas();
        $this->convertirExpulsiones();

        Schema::dropIfExists('expulsiones');
        Schema::dropIfExists('incidencias_aula');
        Schema::dropIfExists('incidencias_ingreso');
    }

    public function down(): void
    {
        $this->crearTablasAnteriores();

        $this->devolverRechazos();
        $this->devolverReportadas();
        $this->devolverExpulsiones();

        foreach (['incidencias_ingreso', 'incidencias_aula', 'expulsiones'] as $tabla) {
            $this->ajustarSecuencia($tabla);
        }

        Schema::dropIfExists('incidencias');
    }

    private function convertirRechazos(): void
    {
        DB::table('incidencias_ingreso')->orderBy('id')->chunk(500, function ($filas): void {
            $nuevas = [];

            foreach ($filas as $fila) {
                $columnas = get_object_vars($fila);
                $tipo = self::texto($columnas['tipo_rechazo']);
                $ingresoId = $tipo === 'YA_INGRESO' && $columnas['examen_id'] !== null && $columnas['estudiante_id'] !== null
                    ? DB::table('ingresos')
                        ->where('examen_id', $columnas['examen_id'])
                        ->where('estudiante_id', $columnas['estudiante_id'])
                        ->value('id')
                    : null;
                $repetido = $ingresoId !== null;

                $nuevas[] = [
                    'examen_id' => $columnas['examen_id'],
                    'aula_id' => $columnas['aula_id'],
                    'estudiante_id' => $columnas['estudiante_id'],
                    'ingreso_id' => $ingresoId,
                    'tipo' => $repetido ? 'INGRESO_REPETIDO' : 'INTENTO_NO_AUTORIZADO',
                    'origen' => 'SISTEMA',
                    'causa' => $repetido ? null : $tipo,
                    'detalle' => $columnas['detalle'],
                    'ocurrida_en' => $columnas['registrado_en'],
                    'reportada_por' => $columnas['registrado_por'],
                    'legado_tabla' => 'incidencias_ingreso',
                    'legado_id' => $columnas['id'],
                    'legado_tipo' => $tipo,
                    'created_at' => $columnas['registrado_en'],
                    'updated_at' => $columnas['registrado_en'],
                ];
            }

            DB::table('incidencias')->insert($nuevas);
        });
    }

    private function convertirReportadas(): void
    {
        DB::table('incidencias_aula')->orderBy('id')->chunk(500, function ($filas): void {
            $nuevas = [];

            foreach ($filas as $fila) {
                $columnas = get_object_vars($fila);
                $tipo = self::texto($columnas['tipo']);
                $detalle = self::texto($columnas['detalle']);

                [$nuevo, $prefijo] = match ($tipo) {
                    'EXCEPCION_CAMBIO_AULA' => ['CAMBIO_AULA', null],
                    'OTRO' => ['OTRA', null],
                    'EXCEPCION_NO_HABILITADO' => ['INTENTO_NO_AUTORIZADO', self::ETIQUETAS_AULA[$tipo]],
                    default => ['OTRA', self::ETIQUETAS_AULA[$tipo] ?? $tipo],
                };

                $ingresoId = $nuevo === 'CAMBIO_AULA' && $columnas['estudiante_id'] !== null
                    ? DB::table('ingresos')
                        ->where('examen_id', $columnas['examen_id'])
                        ->where('estudiante_id', $columnas['estudiante_id'])
                        ->where('es_excepcion', true)
                        ->value('id')
                    : null;

                $nuevas[] = [
                    'examen_id' => $columnas['examen_id'],
                    'aula_id' => $columnas['aula_id'],
                    'estudiante_id' => $columnas['estudiante_id'],
                    'ingreso_id' => $ingresoId,
                    'tipo' => $nuevo,
                    'origen' => 'PERSONA',
                    'causa' => null,
                    'detalle' => $prefijo === null ? $detalle : $prefijo.' · '.$detalle,
                    'ocurrida_en' => $columnas['reportada_en'],
                    'reportada_por' => $columnas['reportada_por'],
                    'atendida_en' => $columnas['atendida_en'],
                    'atendida_por' => $columnas['atendida_por'],
                    'legado_tabla' => 'incidencias_aula',
                    'legado_id' => $columnas['id'],
                    'legado_tipo' => $tipo,
                    'created_at' => $columnas['reportada_en'],
                    'updated_at' => $columnas['atendida_en'] ?? $columnas['reportada_en'],
                ];
            }

            DB::table('incidencias')->insert($nuevas);
        });
    }

    private function convertirExpulsiones(): void
    {
        DB::table('expulsiones as x')
            ->join('ingresos as i', 'i.id', '=', 'x.ingreso_id')
            ->orderBy('x.id')
            ->select([
                'x.id',
                'x.ingreso_id',
                'x.registrada_por',
                'x.motivo',
                'x.registrada_en',
                'i.examen_id',
                'i.aula_id',
                'i.estudiante_id',
            ])
            ->chunk(500, function ($filas): void {
                $nuevas = [];

                foreach ($filas as $fila) {
                    $columnas = get_object_vars($fila);

                    $nuevas[] = [
                        'examen_id' => $columnas['examen_id'],
                        'aula_id' => $columnas['aula_id'],
                        'estudiante_id' => $columnas['estudiante_id'],
                        'ingreso_id' => $columnas['ingreso_id'],
                        'tipo' => 'EXPULSION',
                        'origen' => 'PERSONA',
                        'causa' => null,
                        'detalle' => $columnas['motivo'],
                        'ocurrida_en' => $columnas['registrada_en'],
                        'reportada_por' => $columnas['registrada_por'],
                        'legado_tabla' => 'expulsiones',
                        'legado_id' => $columnas['id'],
                        'legado_tipo' => null,
                        'created_at' => $columnas['registrada_en'],
                        'updated_at' => $columnas['registrada_en'],
                    ];
                }

                DB::table('incidencias')->insert($nuevas);
            });
    }

    private function crearTablasAnteriores(): void
    {
        Schema::create('expulsiones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ingreso_id')->constrained('ingresos')->restrictOnDelete();
            $table->foreignId('registrada_por')->constrained('usuarios')->restrictOnDelete();
            $table->text('motivo');
            $table->timestamp('registrada_en')->useCurrent();
            $table->unique('ingreso_id', 'uq_expulsion_ingreso');
        });

        Schema::create('incidencias_ingreso', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('examen_id')->nullable()->constrained('examenes')->restrictOnDelete();
            $table->foreignId('aula_id')->constrained('aulas')->restrictOnDelete();
            $table->foreignId('estudiante_id')->nullable()->constrained('estudiantes')->nullOnDelete();
            $table->foreignId('registrado_por')->constrained('usuarios')->restrictOnDelete();
            $table->string('tipo_rechazo', 40);
            $table->text('detalle');
            $table->timestamp('registrado_en')->useCurrent();
            $table->index(['examen_id', 'registrado_en'], 'idx_incidencias_ingreso_examen_fecha');
            $table->index(['aula_id', 'registrado_en'], 'idx_incidencias_ingreso_aula_fecha');
        });

        Schema::create('incidencias_aula', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('examen_id')->constrained('examenes')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('aula_id')->constrained('aulas')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('estudiante_id')->nullable()->constrained('estudiantes')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('reportada_por')->constrained('usuarios')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('tipo', 60);
            $table->text('detalle');
            $table->timestamp('reportada_en')->useCurrent();
            $table->foreignId('atendida_por')->nullable()->constrained('usuarios')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('atendida_en')->nullable();
            $table->index(['examen_id', 'aula_id', 'reportada_en'], 'idx_incidencia_aula_examen_aula_fecha');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE expulsiones
                 ADD CONSTRAINT chk_expulsion_motivo
                 CHECK (length(btrim(motivo)) > 0)'
            );
            DB::statement(sprintf(
                'ALTER TABLE incidencias_ingreso
                 ADD CONSTRAINT chk_incidencia_tipo_rechazo
                 CHECK (tipo_rechazo IN (%s))',
                implode(', ', array_map(static fn (string $tipo): string => "'{$tipo}'", self::TIPOS_RECHAZO)),
            ));
            DB::statement(
                'ALTER TABLE incidencias_ingreso
                 ADD CONSTRAINT chk_incidencia_detalle
                 CHECK (length(btrim(detalle)) > 0)'
            );
            DB::statement(
                'ALTER TABLE incidencias_aula
                 ADD CONSTRAINT chk_incidencia_aula_detalle
                 CHECK (length(btrim(tipo)) > 0 AND length(btrim(detalle)) > 0)'
            );
        }
    }

    /**
     * Las del sistema vuelven a `incidencias_ingreso`. Las nuevas sin autor
     * (las asento una sesion con codigo de aula) no caben: esa tabla exige
     * una cuenta.
     */
    private function devolverRechazos(): void
    {
        $filas = DB::table('incidencias')
            ->where('origen', 'SISTEMA')
            ->where(static function (Builder $consulta): void {
                $consulta->where('legado_tabla', 'incidencias_ingreso')->orWhereNull('legado_tabla');
            })
            ->whereNotNull('aula_id')
            ->whereNotNull('reportada_por')
            ->orderBy('id')
            ->get();

        foreach ($filas as $fila) {
            $columnas = get_object_vars($fila);
            $tipo = $columnas['legado_tipo']
                ?? ($columnas['tipo'] === 'INGRESO_REPETIDO' ? 'YA_INGRESO' : $columnas['causa']);

            if (! in_array($tipo, self::TIPOS_RECHAZO, true)) {
                $tipo = $columnas['examen_id'] === null ? 'NO_INSCRITO' : 'NO_CORRESPONDE';
            }

            DB::table('incidencias_ingreso')->insert(array_filter([
                'id' => $columnas['legado_tabla'] === 'incidencias_ingreso' ? $columnas['legado_id'] : null,
                'examen_id' => $columnas['examen_id'],
                'aula_id' => $columnas['aula_id'],
                'estudiante_id' => $columnas['estudiante_id'],
                'registrado_por' => $columnas['reportada_por'],
                'tipo_rechazo' => $tipo,
                'detalle' => $columnas['detalle'],
                'registrado_en' => $columnas['ocurrida_en'],
            ], static fn (mixed $valor, string $clave): bool => $clave !== 'id' || $valor !== null, ARRAY_FILTER_USE_BOTH));
        }
    }

    private function devolverReportadas(): void
    {
        $filas = DB::table('incidencias')
            ->where('origen', 'PERSONA')
            ->where('tipo', '<>', 'EXPULSION')
            ->whereNotNull('examen_id')
            ->whereNotNull('aula_id')
            ->whereNotNull('reportada_por')
            ->orderBy('id')
            ->get();

        foreach ($filas as $fila) {
            $columnas = get_object_vars($fila);
            $legado = $columnas['legado_tabla'] === 'incidencias_aula';
            $tipo = $legado ? self::texto($columnas['legado_tipo']) : match ($columnas['tipo']) {
                'CAMBIO_AULA' => 'EXCEPCION_CAMBIO_AULA',
                default => 'OTRO',
            };
            $detalle = self::texto($columnas['detalle']);
            $prefijo = $legado ? (self::ETIQUETAS_AULA[$tipo] ?? ($tipo === 'OTRO' || $tipo === 'EXCEPCION_CAMBIO_AULA' ? null : $tipo)) : null;

            if ($prefijo !== null && str_starts_with($detalle, $prefijo.' · ')) {
                $detalle = substr($detalle, strlen($prefijo.' · '));
            }

            DB::table('incidencias_aula')->insert(array_filter([
                'id' => $legado ? $columnas['legado_id'] : null,
                'examen_id' => $columnas['examen_id'],
                'aula_id' => $columnas['aula_id'],
                'estudiante_id' => $columnas['estudiante_id'],
                'reportada_por' => $columnas['reportada_por'],
                'tipo' => $tipo,
                'detalle' => $detalle,
                'reportada_en' => $columnas['ocurrida_en'],
                'atendida_por' => $columnas['atendida_por'],
                'atendida_en' => $columnas['atendida_en'],
            ], static fn (mixed $valor, string $clave): bool => $clave !== 'id' || $valor !== null, ARRAY_FILTER_USE_BOTH));
        }
    }

    private function devolverExpulsiones(): void
    {
        $filas = DB::table('incidencias')
            ->where('tipo', 'EXPULSION')
            ->whereNotNull('ingreso_id')
            ->whereNotNull('reportada_por')
            ->orderBy('id')
            ->get();

        foreach ($filas as $fila) {
            $columnas = get_object_vars($fila);

            DB::table('expulsiones')->insert(array_filter([
                'id' => $columnas['legado_tabla'] === 'expulsiones' ? $columnas['legado_id'] : null,
                'ingreso_id' => $columnas['ingreso_id'],
                'registrada_por' => $columnas['reportada_por'],
                'motivo' => $columnas['detalle'],
                'registrada_en' => $columnas['ocurrida_en'],
            ], static fn (mixed $valor, string $clave): bool => $clave !== 'id' || $valor !== null, ARRAY_FILTER_USE_BOTH));
        }
    }

    private static function texto(mixed $valor): string
    {
        return is_scalar($valor) ? (string) $valor : '';
    }

    private function ajustarSecuencia(string $tabla): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(sprintf(
            "SELECT setval(pg_get_serial_sequence('%s', 'id'), coalesce((SELECT max(id) FROM %s), 0) + 1, false)",
            $tabla,
            $tabla,
        ));
    }
};
