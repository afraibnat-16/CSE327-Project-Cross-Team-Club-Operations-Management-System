<?php

namespace App\Patterns\Proxy;

use RuntimeException;

// Our own error type, so a screen can catch exactly this case and show
// "The admin team cannot do this." in a red banner.

/** Thrown by the proxy when a team is not allowed to do something. */
final class AccessDenied extends RuntimeException {}
