<?php

namespace App\Exceptions;

use RuntimeException;

/** Carries a message that is safe to show to the admin user. */
class ImageUploadException extends RuntimeException {}
