<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Exceptions;

use RuntimeException;

/**
 * La invitacion ya se respondio: solo se responde una pendiente.
 */
final class InvitacionYaRespondidaException extends RuntimeException {}
