<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Application\Contracts\StudentRepository;
use App\Modules\Administracion\Domain\Models\Student;

final readonly class UpdateStudent
{
    public function __construct(
        private StudentRepository $repository,
        private BitacoraGateway $bitacora,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Student $student, array $data, int $usuarioId): Student
    {
        $actualizado = $this->repository->update($student, $data);

        $id = $actualizado->getKey();

        // Reactivar a un estudiante dado de baja es una operacion distinta:
        // en la bitacora tiene que poder distinguirse de una edicion.
        $operacion = array_key_exists('activo', $data) && $data['activo'] === true
            ? 'estudiante.reactivar'
            : 'estudiante.actualizar';

        $this->bitacora->registrar(
            $usuarioId,
            $operacion,
            'students',
            is_int($id) ? $id : null,
            null,
        );

        return $actualizado;
    }
}
