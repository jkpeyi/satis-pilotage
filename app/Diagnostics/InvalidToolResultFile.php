<?php

namespace App\Diagnostics;

use DomainException;

/**
 * The file does not match the chosen format. The message is shown to the user.
 */
final class InvalidToolResultFile extends DomainException {}
