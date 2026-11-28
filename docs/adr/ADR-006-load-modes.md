# ADR-006: Three Load Modes for Environment Variable Precedence

## Status

Accepted — introduced in 4.0.0.

## Date

2026-02-28 · revised 2026-09-29 (4.5.0)

## Context

When a `.env` file declares `DB_HOST=localhost` but the operating system already has `DB_HOST=production-host` set via the container orchestrator, which value wins? The answer depends on the deployment model:

- **Containerized (Kubernetes, ECS):** The orchestrator injects real secrets via environment variables. The `.env` file contains development defaults. Real environment must take precedence.
- **Traditional server:** The `.env` file is the source of truth. No environment variables are pre-set.
- **Development:** Developers want `.env` to override everything for local testing.

A single behavior cannot satisfy all three models.

## Decision

Three mutually exclusive load modes, selected via `DotenvConfiguration::$loadMode`:

### `LoadMode::Immutable` (Default)

Throws `ImmutableException` if a `.env` variable conflicts with a pre-existing environment variable (one that existed **before** `load()` was called). Variables loaded by the Dotenv instance itself can be overridden across multiple files (e.g., `.env` → `.env.local`).

**Rationale:** The safest default. If the container orchestrator set `DB_PASSWORD`, the `.env` file's value is almost certainly stale. Throwing an exception surfaces the conflict immediately rather than silently using the wrong value.

**Scope of immutability:** Only variables that existed in `$_ENV`, `$_SERVER`, or `getenv()` before the Dotenv instance started loading are protected. Variables introduced by earlier `.env` files in the same load sequence are not protected — this allows cascade loading (`.env` → `.env.local`) to work naturally.

### `LoadMode::Overwrite`

Always overwrites, regardless of prior state. The `.env` file is the absolute source of truth.

**Use case:** Development environments, test suites, and applications where the `.env` file is managed by deployment tooling (Ansible, Chef) and is the canonical source.

### `LoadMode::SkipExisting`

Silently skips a variable that was already in the process before this instance started loading. No exception, no overwrite. Later files of the same cascade still override earlier ones.

**Use case:** Container images that ship sensible defaults in `.env` but allow runtime overrides via `docker run -e DB_HOST=...`. The `.env` provides fallbacks only.

**References follow the effective value.** Because the process value wins, `${NAME}` references resolve to it as well — `DSN="pgsql:host=${DB_HOST}"` uses the same host the application sees in `DB_HOST`, never the skipped file value.

### Decision Matrix

| Scenario | Process | File(s) | Immutable | Overwrite | SkipExisting |
|---|---|---|---|---|---|
| Conflict | `DB_HOST=prod` | `.env`: `DB_HOST=dev` | ❌ `ImmutableException` | `dev` | `prod` |
| No conflict | *(absent)* | `.env`: `DB_HOST=dev` | `dev` | `dev` | `dev` |
| Cascade | *(absent)* | `.env`: `X=a`, `.env.local`: `X=b` | `b` | `b` | `b` |
| Reference | `DB_HOST=prod` | `.env`: `DB_HOST=dev`, `DSN=${DB_HOST}` | ❌ throws | `DSN=dev` | `DSN=prod` |

### Implementation

Both guarded modes share one definition of *pre-existing*, computed in `Dotenv::setVariable()`:

```php
// Pre-existing = present in the process before this instance loaded it.
// Variables loaded by an earlier file of this instance are cascade overrides.
$preExisting = !isset($this->variables[$name]) && $this->existsInProcess($name);

if ($preExisting && $this->configuration->loadMode === LoadMode::Immutable) {
    throw ImmutableException::alreadyDefined($name);
}

if ($preExisting && $this->configuration->loadMode === LoadMode::SkipExisting) {
    return;
}
```

`existsInProcess()` checks `$_ENV`, `$_SERVER` and `getenv()`. For references, `Dotenv` passes the pre-existing values as `InterpolationContext::$locked` when the mode is `SkipExisting`, so the parser resolves them before same-file definitions (SPEC-002).

**Defect fixed in 4.5.0:** in 4.4 the "not loaded by this instance" guard applied only to `Immutable`. Under `SkipExisting`, a value that `.env` had just written into `$_ENV` counted as pre-existing, so `.env.local` could not override `.env` — contradicting the matrix above — and references used the skipped file value.

## Consequences

### Positive

- Explicit control over precedence — no guessing which value is active.
- Safe default (Immutable) prevents silent credential conflicts in production.
- The same pre-existing rule drives all modes, so cascade overrides behave identically in each.
- Under SkipExisting, derived values (DSNs, URLs) stay consistent with the variables they reference.

### Negative

- Immutable mode can cause startup failures if the deployment pipeline sets variables that also appear in `.env`. This is by design — the failure surfaces the misconfiguration.
- SkipExisting can hide stale `.env` values: pre-existing process variables always win.

## Alternatives Considered

| Alternative | Why not |
|---|---|
| Always let the process win | local development, where `.env` should override stale shell variables, would need workarounds |
| Always let the file win | secrets injected by an orchestrator would be replaced by the defaults committed in `.env` |
| A per-variable precedence list | configuration that grows with every variable, for what one mode per load expresses |

## References

- ARFA 1.3 P1 (Immutable State Transformation).
- SPEC-001; ADR-007.
- ADR-010 (`ImmutableException`, ENV_2711).
- Errata applied by the 4.5.0 review (CODE-REVIEW.md): E-011 (`ImmutableException::alreadyDefined()`).
