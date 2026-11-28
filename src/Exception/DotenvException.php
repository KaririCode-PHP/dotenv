<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Exception;

/**
 * Root of every exception thrown by KaririCode\Dotenv.
 *
 * Abstract (Spec V4.0 E-006): only the semantic subclasses are thrown,
 * so a single `catch (DotenvException $e)` covers the whole library.
 *
 * @package KaririCode\Dotenv
 * @since   4.0.0 ARFA 1.3
 */
abstract class DotenvException extends \RuntimeException {}
