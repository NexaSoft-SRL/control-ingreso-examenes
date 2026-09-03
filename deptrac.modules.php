<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\BoolConfig;
use Deptrac\Deptrac\Contract\Config\Collector\DirectoryConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

return static function (DeptracConfig $config): void {
    $config
        ->paths('./app/Modules')
        ->cacheFile('storage/framework/cache/deptrac-modules.cache')
        ->layers(
            $administracion = Layer::withName('Administracion')->collectors(
                BoolConfig::create()
                    ->must(
                        DirectoryConfig::create(
                            'app/Modules/Administracion/.*'
                        )
                    )
                    ->mustNot(
                        DirectoryConfig::create(
                            'app/Modules/Administracion/Http/.*'
                        )
                    ),
                DirectoryConfig::create(
                    'app/Modules/Administracion/Http/.*'
                )->private(),
            ),

            $estudiantes = Layer::withName('Estudiantes')->collectors(
                BoolConfig::create()
                    ->must(
                        DirectoryConfig::create(
                            'app/Modules/Estudiantes/.*'
                        )
                    )
                    ->mustNot(
                        DirectoryConfig::create(
                            'app/Modules/Estudiantes/Http/.*'
                        )
                    ),
                DirectoryConfig::create(
                    'app/Modules/Estudiantes/Http/.*'
                )->private(),
            ),

            $examenes = Layer::withName('Examenes')->collectors(
                BoolConfig::create()
                    ->must(
                        DirectoryConfig::create(
                            'app/Modules/Examenes/.*'
                        )
                    )
                    ->mustNot(
                        DirectoryConfig::create(
                            'app/Modules/Examenes/Http/.*'
                        )
                    ),
                DirectoryConfig::create(
                    'app/Modules/Examenes/Http/.*'
                )->private(),
            ),

            $habilitacion = Layer::withName('Habilitacion')->collectors(
                BoolConfig::create()
                    ->must(
                        DirectoryConfig::create(
                            'app/Modules/Habilitacion/.*'
                        )
                    )
                    ->mustNot(
                        DirectoryConfig::create(
                            'app/Modules/Habilitacion/Http/.*'
                        )
                    ),
                DirectoryConfig::create(
                    'app/Modules/Habilitacion/Http/.*'
                )->private(),
            ),

            $ingreso = Layer::withName('Ingreso')->collectors(
                BoolConfig::create()
                    ->must(
                        DirectoryConfig::create(
                            'app/Modules/Ingreso/.*'
                        )
                    )
                    ->mustNot(
                        DirectoryConfig::create(
                            'app/Modules/Ingreso/Http/.*'
                        )
                    ),
                DirectoryConfig::create(
                    'app/Modules/Ingreso/Http/.*'
                )->private(),
            ),

            $monitoreo = Layer::withName('Monitoreo')->collectors(
                BoolConfig::create()
                    ->must(
                        DirectoryConfig::create(
                            'app/Modules/Monitoreo/.*'
                        )
                    )
                    ->mustNot(
                        DirectoryConfig::create(
                            'app/Modules/Monitoreo/Http/.*'
                        )
                    ),
                DirectoryConfig::create(
                    'app/Modules/Monitoreo/Http/.*'
                )->private(),
            ),

            $reportes = Layer::withName('Reportes')->collectors(
                BoolConfig::create()
                    ->must(
                        DirectoryConfig::create(
                            'app/Modules/Reportes/.*'
                        )
                    )
                    ->mustNot(
                        DirectoryConfig::create(
                            'app/Modules/Reportes/Http/.*'
                        )
                    ),
                DirectoryConfig::create(
                    'app/Modules/Reportes/Http/.*'
                )->private(),
            ),
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
