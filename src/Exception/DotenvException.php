<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Exception;

use KaririCode\Contract\Exception\ExceptionInterface;
use KaririCode\Exception\ErrorCode;

// ── Exception: DotenvException ───────────────────────────────────────
// Marker of every exception the package throws.
// ─────────────────────────────────────────────────────────────────────

/**
 * The marker every exception of kariricode/dotenv implements, so one
 * `catch` covers the package, as the class of the same name did up to
 * 4.5.0; each concrete exception also extends the kariricode/exception
 * base of exactly one kariricode/contract family and carries an EnvCode
 * case in `$errorCode`.
 *
 * Preconditions: implemented only by the final exceptions of this
 * namespace.
 *
 * Thread-safety: an interface; no state.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     try {
 *         $dotenv->load();
 *     } catch (DotenvException $e) {
 *         fwrite(STDERR, $e->getMessage() . "\n"); // [ENV_27xx] …, no secret values
 *     }
 *
 * References: ADR-010; Spec V4.3.3 §8.4. ARFA 1.3 P5: one catch point,
 * coded failures.
 *
 * @see https://github.com/KaririCode-PHP/exception/blob/main/README.md
 */
interface DotenvException extends ExceptionInterface
{
    /** The EnvCode case of the failure; `getCode()` is its integer. */
    public ErrorCode $errorCode { get; }
}
