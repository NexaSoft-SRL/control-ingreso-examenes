<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\DirectoryConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

/**
 * Cada módulo expone a los demás su Application/Contracts y los
 * Application/DTOs con los que esos contratos hablan: un contrato que
 * devuelve un dato estructurado obliga a que ese dato sea público. Todo el
 * resto ---modelos, acciones, infraestructura y HTTP--- es privado.
 */
$moduleLayer = static function (string $name): Layer {
    $base = "app/Modules/{$name}";

    return Layer::withName($name)->collectors(
        DirectoryConfig::create(
            "{$base}/Application/(?:Contracts|DTOs)/.*"
        ),
        DirectoryConfig::create(
            "{$base}/(?!Application/(?:Contracts|DTOs)/).*"
        )->private(),
    );
};

return static function (DeptracConfig $config) use ($moduleLayer): void {
    $administracion = $moduleLayer('Administracion');
    $academico = $moduleLayer('Academico');
    $estudiantes = $moduleLayer('Estudiantes');
    $examenes = $moduleLayer('Examenes');
    $habilitacion = $moduleLayer('Habilitacion');
    $ingreso = $moduleLayer('Ingreso');
    $monitoreo = $moduleLayer('Monitoreo');
    $reportes = $moduleLayer('Reportes');

    $config
        ->paths('./app/Modules')
        ->cacheFile('storage/framework/cache/deptrac-modules.cache')
        ->layers(
            $administracion,
            $academico,
            $estudiantes,
            $examenes,
            $habilitacion,
            $ingreso,
            $monitoreo,
            $reportes,
        )
        ->rulesets(
            Ruleset::forLayer($administracion),

            Ruleset::forLayer($academico)
                ->accesses($administracion),

            Ruleset::forLayer($estudiantes)
                ->accesses(
                    $administracion,
                    $academico,
                ),

            Ruleset::forLayer($examenes)
                ->accesses(
                    $administracion,
                    $academico,
                    $estudiantes,
                ),

            Ruleset::forLayer($habilitacion)
                ->accesses(
                    $administracion,
                    $academico,
                    $estudiantes,
                    $examenes,
                ),

            Ruleset::forLayer($ingreso)
                ->accesses(
                    $administracion,
                    $academico,
                    $estudiantes,
                    $examenes,
                    $habilitacion,
                ),

            Ruleset::forLayer($monitoreo)
                ->accesses(
                    $administracion,
                    $academico,
                    $estudiantes,
                    $examenes,
                    $habilitacion,
                    $ingreso,
                ),

            Ruleset::forLayer($reportes)
                ->accesses(
                    $administracion,
                    $academico,
                    $estudiantes,
                    $examenes,
                    $habilitacion,
                    $ingreso,
                ),
        );
};
