<?php

namespace App\Services\Assistant;

use RuntimeException;

/** A safe, model-facing message: input was invalid or the record is not visible. */
class ToolInputError extends RuntimeException {}
