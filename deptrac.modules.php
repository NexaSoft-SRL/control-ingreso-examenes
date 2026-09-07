<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\DirectoryConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

/**
 * Cada módulo expone únicamente Application/Contracts a otros módulos.
 * Todo el resto de su implementación es privado.
 */
$moduleLayer = static function (string $name): Layer {
    $base = "app/Modules/{$name}";

    return Layer::withName($name)->collectors(
        DirectoryConfig::create(
            "{$base}/Application/Contracts/.*"
        ),
        DirectoryConfig::create(
            "{$base}/(?!Application/Contracts/).*"
        )->private(),
    );
};

return static function (DeptracConfig $config) use ($moduleLayer): void {
    $administracion = $moduleLayer('Administracion');
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
            $estudiantes,
            $examenes,
            $habilitacion,
            $ingreso,
            $monitoreo,
            $reportes,
        )
        ->rulesets(
            Ruleset::forLayer($administracion),

            Ruleset::forLayer($estudiantes)
                ->accesses($administracion),

            Ruleset::forLayer($examenes)
                ->accesses(
                    $estudiantes,
                    $administracion,
                ),

            Ruleset::forLayer($habilitacion)
                ->accesses(
                    $estudiantes,
                    $examenes,
                    $administracion,
                ),

            Ruleset::forLayer($ingreso)
                ->accesses(
                    $estudiantes,
                    $examenes,
                    $habilitacion,
                    $administracion,
                ),

            Ruleset::forLayer($monitoreo)
                ->accesses(
                    $estudiantes,
                    $examenes,
                    $habilitacion,
                    $ingreso,
                    $administracion,
                ),

            Ruleset::forLayer($reportes)
                ->accesses(
                    $estudiantes,
                    $examenes,
                    $habilitacion,
                    $ingreso,
                    $administracion,
                ),
        );
};
