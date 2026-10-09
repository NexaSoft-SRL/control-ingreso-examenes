<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Retira las tablas del dominio anterior (asignaturas y ambientes cargados a
 * mano, examen por grupo, normas por estudiante). Las migraciones que siguen
 * crean el esquema nuevo. El orden respeta las claves foraneas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('asignaciones_ambiente');
        Schema::dropIfExists('examen_ambiente');
        Schema::dropIfExists('normas_examenes');
        Schema::dropIfExists('habilitaciones_examen');
        Schema::dropIfExists('examenes');
        Schema::dropIfExists('grupos_asignatura');
        Schema::dropIfExists('docentes');
        Schema::dropIfExists('asignaturas');
        Schema::dropIfExists('ambientes');
        Schema::dropIfExists('students');
    }

    public function down(): void
    {
        // Sin vuelta atras: el diseno anterior no se recrea. Sus datos de
        // dominio no se migran; la base se rehace con `migrate:fresh`.
    }
};
