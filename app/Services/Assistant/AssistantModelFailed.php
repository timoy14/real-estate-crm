<?php

namespace App\Services\Assistant;

use RuntimeException;

/** Sanitised failure from the Claude API: never carries the request, headers or key. */
class AssistantModelFailed extends RuntimeException {}
