<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a pasted curl command can't be read as a request. The message is
 * shown to whoever pasted it, so it should say what's wrong with the input.
 */
class InvalidCurlCommandException extends RuntimeException {}
