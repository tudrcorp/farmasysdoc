<?php

namespace App\Services\Partners;

use RuntimeException;

/**
 * Regla de negocio de convenios incumplida. El mensaje está en español y se muestra tal cual al usuario.
 */
final class PartnerAgreementException extends RuntimeException {}
