<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\BoolConfig;
use Deptrac\Deptrac\Contract\Config\Collector\ClassNameRegexConfig;
use Deptrac\Deptrac\Contract\Config\Collector\ComposerConfig;
use Deptrac\Deptrac\Contract\Config\Collector\DirectoryConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

return static function (DeptracConfig $config): void {
    $config
        ->paths('./app/Modules')
        ->cacheFile('storage/framework/cache/deptrac.cache')
        ->layers(
            $domain = Layer::withName('Domain')->collectors(
                DirectoryConfig::create(
                    'app/Modules/[^/]+/Domain/.*'
                ),
            ),

            $application = Layer::withName('Application')->collectors(
                DirectoryConfig::create(
                    'app/Modules/[^/]+/Application/.*'
                ),
            ),

            $infrastructure = Layer::withName('Infrastructure')->collectors(
                DirectoryConfig::create(
                    'app/Modules/[^/]+/Infrastructure/.*'
                ),
            ),

            $http = Layer::withName('Http')->collectors(
                DirectoryConfig::create(
                    'app/Modules/[^/]+/Http/.*'
                ),
            ),

            $laravelDomainSupport = Layer::withName(
                'LaravelDomainSupport'
            )->collectors(
                BoolConfig::create()
                    ->must(
                        ComposerConfig::create()
                            ->addPackage('laravel/framework')
                    )
                    ->must(
                        ClassNameRegexConfig::create(
                            '#^Illuminate\Foundation\Auth\User$#'
                        )
                    ),

                BoolConfig::create()
                    ->must(
                        ComposerConfig::create()
                            ->addPackage('laravel/framework')
                    )
                    ->must(
                        ClassNameRegexConfig::create(
                            '#^Illuminate\Notifications\Notifiable$#'
                        )
                    ),
            ),

            $laravelHttpSupport = Layer::withName(
                'LaravelHttpSupport'
            )->collectors(
                BoolConfig::create()
                    ->must(
                        ComposerConfig::create()
                            ->addPackage('laravel/framework')
                    )
                    ->must(
                        ClassNameRegexConfig::create(
                            '#^Illuminate\\Http\\(?:JsonResponse|Request|Response)$#'
                        )
                    ),

                BoolConfig::create()
                    ->must(
                        ComposerConfig::create()
                            ->addPackage('laravel/framework')
                    )
                    ->must(
                        ClassNameRegexConfig::create(
                            '#^Illuminate\\Foundation\\Http\\FormRequest$#'
                        )
                    ),
            ),

            $laravelAuthSupport = Layer::withName(
                'LaravelAuthSupport'
            )->collectors(
                BoolConfig::create()
                    ->must(
                        ComposerConfig::create()
                            ->addPackage('laravel/framework')
                    )
                    ->must(
                        ClassNameRegexConfig::create(
                            '#^Illuminate\\Support\\Facades\\Auth$#'
                        )
                    ),
            ),

            $laravelPersistenceSupport = Layer::withName(
                'LaravelPersistenceSupport'
            )->collectors(
                BoolConfig::create()
                    ->must(
                        ComposerConfig::create()
                            ->addPackage('laravel/framework')
                    )
                    ->must(
                        ClassNameRegexConfig::create(
                            '#^Illuminate\\Support\\Facades\\(?:DB|Hash)$#'
                        )
                    ),
            ),
        )
        ->rulesets(
            Ruleset::forLayer($domain)
                ->accesses($laravelDomainSupport),

            Ruleset::forLayer($application)
                ->accesses($domain),

            Ruleset::forLayer($infrastructure)
                ->accesses(
                    $application,
                    $domain,
                    $laravelPersistenceSupport,
                ),

            Ruleset::forLayer($http)
                ->accesses(
                    $application,
                    $domain,
                    $laravelHttpSupport,
                    $laravelAuthSupport,
                ),

            Ruleset::forLayer($laravelDomainSupport),

            Ruleset::forLayer($laravelHttpSupport),

            Ruleset::forLayer($laravelAuthSupport),

            Ruleset::forLayer($laravelPersistenceSupport),
        );
};
