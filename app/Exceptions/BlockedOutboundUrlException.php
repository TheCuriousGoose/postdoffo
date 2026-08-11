<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when OutboundUrlGuard refuses a target. The executor already turns a
 * failed send into an error on the response, so the message here is written to
 * be read by the person who typed the URL.
 */
class BlockedOutboundUrlException extends RuntimeException {}
