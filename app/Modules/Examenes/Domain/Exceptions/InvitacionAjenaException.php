<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Exceptions;

use RuntimeException;

/**
 * La invitacion no es de un grupo de la cuenta.
 */
final class InvitacionAjenaException extends RuntimeException {}
