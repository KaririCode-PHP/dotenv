<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Enum;

/**
 * Controls how environment variables interact with pre-existing values.
 *
 * Immutable mode (default) prevents overwriting variables that were already
 * set before load() — typical for containerized deployments where the
 * orchestrator injects secrets via real environment variables.
 *
 * ARFA 1.3 P1: Immutable State Transformation — once loaded, values are sealed.
 *
 * Preconditions: none.
 *
 * Thread-safety: an enum; immutable.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $dotenv = new Dotenv(__DIR__, new DotenvConfiguration(loadMode: LoadMode::SkipExisting));
 *
 * References: ADR-006. ARFA 1.3 P1 and E-003: a pure enum.
 *
 * @since   4.0.0 ARFA 1.3
 */
enum LoadMode
{
    /** Reject overwrites of already-defined variables (safe default). */
    case Immutable;

    /** Overwrite any pre-existing value — use only in controlled environments. */
    case Overwrite;

    /** Skip .env entries when the variable already exists in the environment. */
    case SkipExisting;
}
