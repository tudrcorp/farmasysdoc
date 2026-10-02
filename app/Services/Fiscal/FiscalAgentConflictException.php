<?php

namespace App\Services\Fiscal;

use RuntimeException;

/**
 * El agente reportó algo incompatible con el estado actual del documento (HTTP 409).
 */
final class FiscalAgentConflictException extends RuntimeException {}
