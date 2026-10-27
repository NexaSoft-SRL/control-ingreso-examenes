<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * La migracion que unifica las incidencias convierte lo que habia en
 * `incidencias_ingreso`, `incidencias_aula` y `expulsiones` sin perder
 * nada, y al revertirla las tres tablas quedan como estaban.
 */
final class UnificarIncidenciasMigracionTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    public function test_convierte_las_tres_tablas_y_vuelve_atras_sin_perder_nada(): void
    {
        $migracion = $this->migracion();
        $usuario = $this->usuarioConPermisos(['punto_control']);
        $docente = $this->docenteConCuenta($usuario);
        $grupo = $this->grupo($docente);
        $aula = $this->aula('691A');
        [$a, $b, $c] = $this->estudiantesInscritos($grupo, 3);
        $examen = $this->examen($docente, [$grupo], [$aula]);

        $ingresoA = $this->ingresar($examen, $a, $aula);
        $ingresoB = $this->ingresar($examen, $b, $aula);
        DB::table('ingresos')->where('id', $ingresoB)->update(['es_excepcion' => true]);

        $this->correr($migracion, 'down');
        self::assertFalse(Schema::hasTable('incidencias'));

        $cuando = '2026-10-20 08:40:00';
        DB::table('incidencias_ingreso')->insert([
            ['id' => 11, 'examen_id' => $examen->id, 'aula_id' => $aula->id, 'estudiante_id' => $c->id, 'registrado_por' => $usuario->id, 'tipo_rechazo' => 'NO_HABILITADO', 'detalle' => 'Sin prácticas.', 'registrado_en' => $cuando],
            ['id' => 12, 'examen_id' => $examen->id, 'aula_id' => $aula->id, 'estudiante_id' => $a->id, 'registrado_por' => $usuario->id, 'tipo_rechazo' => 'YA_INGRESO', 'detalle' => 'Ya tiene ingreso.', 'registrado_en' => $cuando],
            ['id' => 13, 'examen_id' => null, 'aula_id' => $aula->id, 'estudiante_id' => null, 'registrado_por' => $usuario->id, 'tipo_rechazo' => 'QR_NO_VALIDO', 'detalle' => 'Código QR no válido.', 'registrado_en' => $cuando],
        ]);
        DB::table('incidencias_aula')->insert([
            ['id' => 21, 'examen_id' => $examen->id, 'aula_id' => $aula->id, 'estudiante_id' => $c->id, 'reportada_por' => $usuario->id, 'tipo' => 'CONDUCTA', 'detalle' => 'Conversa.', 'reportada_en' => $cuando, 'atendida_por' => $usuario->id, 'atendida_en' => '2026-10-20 08:50:00'],
            ['id' => 22, 'examen_id' => $examen->id, 'aula_id' => $aula->id, 'estudiante_id' => $b->id, 'reportada_por' => $usuario->id, 'tipo' => 'EXCEPCION_CAMBIO_AULA', 'detalle' => 'Aula cerrada.', 'reportada_en' => $cuando, 'atendida_por' => null, 'atendida_en' => null],
        ]);
        DB::table('expulsiones')->insert([
            'id' => 31, 'ingreso_id' => $ingresoA, 'registrada_por' => $usuario->id, 'motivo' => 'Uso de celular.', 'registrada_en' => $cuando,
        ]);

        $this->correr($migracion, 'up');

        self::assertFalse(Schema::hasTable('incidencias_ingreso'));
        self::assertFalse(Schema::hasTable('incidencias_aula'));
        self::assertFalse(Schema::hasTable('expulsiones'));
        $this->assertDatabaseCount('incidencias', 6);

        $this->assertDatabaseHas('incidencias', [
            'legado_tabla' => 'incidencias_ingreso', 'legado_id' => 11, 'tipo' => 'INTENTO_NO_AUTORIZADO',
            'origen' => 'SISTEMA', 'causa' => 'NO_HABILITADO', 'estudiante_id' => $c->id, 'reportada_por' => $usuario->id,
        ]);
        $this->assertDatabaseHas('incidencias', [
            'legado_tabla' => 'incidencias_ingreso', 'legado_id' => 12, 'tipo' => 'INGRESO_REPETIDO', 'ingreso_id' => $ingresoA,
        ]);
        $this->assertDatabaseHas('incidencias', [
            'legado_tabla' => 'incidencias_ingreso', 'legado_id' => 13, 'examen_id' => null, 'causa' => 'QR_NO_VALIDO',
        ]);
        $this->assertDatabaseHas('incidencias', [
            'legado_tabla' => 'incidencias_aula', 'legado_id' => 21, 'tipo' => 'OTRA', 'origen' => 'PERSONA',
            'legado_tipo' => 'CONDUCTA', 'detalle' => 'Conducta · Conversa.', 'atendida_por' => $usuario->id,
        ]);
        $this->assertDatabaseHas('incidencias', [
            'legado_tabla' => 'incidencias_aula', 'legado_id' => 22, 'tipo' => 'CAMBIO_AULA', 'ingreso_id' => $ingresoB,
        ]);
        $this->assertDatabaseHas('incidencias', [
            'legado_tabla' => 'expulsiones', 'legado_id' => 31, 'tipo' => 'EXPULSION', 'ingreso_id' => $ingresoA,
            'estudiante_id' => $a->id, 'detalle' => 'Uso de celular.', 'reportada_por' => $usuario->id,
        ]);

        // Atras: las tres tablas con sus filas, sus ids y sus tipos.
        $this->correr($migracion, 'down');

        $this->assertDatabaseHas('incidencias_ingreso', ['id' => 12, 'tipo_rechazo' => 'YA_INGRESO']);
        $this->assertDatabaseHas('incidencias_ingreso', ['id' => 13, 'examen_id' => null, 'tipo_rechazo' => 'QR_NO_VALIDO']);
        $this->assertDatabaseHas('incidencias_aula', ['id' => 21, 'tipo' => 'CONDUCTA', 'detalle' => 'Conversa.', 'atendida_por' => $usuario->id]);
        $this->assertDatabaseHas('incidencias_aula', ['id' => 22, 'tipo' => 'EXCEPCION_CAMBIO_AULA', 'detalle' => 'Aula cerrada.']);
        $this->assertDatabaseHas('expulsiones', ['id' => 31, 'ingreso_id' => $ingresoA, 'motivo' => 'Uso de celular.']);
        $this->assertDatabaseCount('incidencias_ingreso', 3);
        $this->assertDatabaseCount('incidencias_aula', 2);

        $this->correr($migracion, 'up');
        $this->assertDatabaseCount('incidencias', 6);
    }

    public function test_la_bitacora_vieja_sigue_encontrando_su_incidencia(): void
    {
        $migracion = $this->migracion();
        $usuario = $this->usuarioConPermisos(['punto_control']);
        $docente = $this->docenteConCuenta($usuario);
        $aula = $this->aula('691A');
        $examen = $this->examen($docente, [$this->grupo($docente)], [$aula]);

        $this->correr($migracion, 'down');
        DB::table('incidencias_aula')->insert([
            'id' => 70, 'examen_id' => $examen->id, 'aula_id' => $aula->id, 'reportada_por' => $usuario->id,
            'tipo' => 'OTRO', 'detalle' => 'Algo pasó.', 'reportada_en' => now(),
            'atendida_por' => $usuario->id, 'atendida_en' => now(),
        ]);
        DB::table('bitacora_operaciones')->insert([
            'usuario_id' => $usuario->id, 'operacion' => 'INCIDENCIA_AULA_ATENDIDA',
            'tabla_afectada' => 'incidencias_aula', 'registro_id' => 70,
            'descripcion' => 'Incidencia de aula marcada como atendida.', 'fecha_operacion' => now(),
        ]);
        $this->correr($migracion, 'up');

        $this->actingAs($this->usuarioConRol('Administrador'))
            ->getJson('/api/bitacora?operacion=INCIDENCIA_AULA_ATENDIDA')
            ->assertOk()
            ->assertJsonPath('data.0.descripcion', 'Incidencia de 691A, examen '.$examen->id.'.');
    }

    private function correr(Migration $migracion, string $sentido): void
    {
        $paso = [$migracion, $sentido];
        self::assertIsCallable($paso);
        $paso();
    }

    private function migracion(): Migration
    {
        /** @var Migration $migracion */
        $migracion = require database_path('migrations/2026_10_27_000100_unificar_incidencias.php');

        return $migracion;
    }
}
