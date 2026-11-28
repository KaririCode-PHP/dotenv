# SPEC-008: CLI Tooling

| Field | Value |
|---|---|
| **Version** | 4.5.0 |
| **Introduced** | 4.4.0 |
| **ARFA 1.3** | P5 (Continuous Observability) |
| **Errata** | E-011 (every `DotenvException` becomes `✗ message` + exit 1) |
| **Date** | 2026-02-28 · revised 2026-09-29 (4.5.0) |

Every output block in this document was captured from a real run of 4.5.0 (keys and paths redacted).

## 1. Overview

`vendor/bin/kariricode-dotenv` (declared in `composer.json` → `bin`) provides 9 commands. Commands that read a project (`debug`, `validate`, `cache:dump`) load it **exactly like an application calling `bootEnv()`**: `.env` → `.env.local` → `.env.{env}` → `.env.{env}.local`.

## 2. Installation

Available after `composer install`; no setup.

```bash
vendor/bin/kariricode-dotenv help
```

## 3. Options That Control Loading

Used by `debug`, `validate` and `cache:dump`.

| Option | Default | Description |
|---|---|---|
| `--dir=<path>` | Current directory | Project directory holding the `.env` files. |
| `--env=<name>` | `APP_ENV` (files or process), else `dev` | Environment for the cascade. Must match `[A-Za-z0-9][A-Za-z0-9_-]*`. |
| `--mode=<mode>` | `immutable` | `immutable`, `overwrite` or `skip-existing` (see ADR-006). |

The CLI runs with the shell's environment. In the default `immutable` mode, a variable defined both in the shell and in a `.env` file is an error — use `--mode=overwrite` or `--mode=skip-existing` when that is intended.

File-path arguments of `encrypt`, `decrypt`, `diff` and `example:generate` are relative to the current directory, not `--dir`.

## 4. Commands

### 4.1 `debug`

```bash
vendor/bin/kariricode-dotenv debug [--dir] [--env] [--mode] [--reveal]
```

Prints every loaded variable with its detected type and the file it came from. Values of variables stored as `encrypted:` are shown as `[encrypted]` unless `--reveal` is given (with `DOTENV_PRIVATE_KEY` set, `--reveal` prints the plaintext). Values longer than 40 characters are truncated.

```
VARIABLE                       TYPE       SOURCE                   VALUE
────────────────────────────────────────────────────────────────────────
APP_ENV                        String     .env                     'staging'
APP_DEBUG                      Boolean    .env                     true
DB_HOST                        String     .env.staging             'staging-db.internal' (overridden)
DB_PORT                        Integer    .env                     5432

Total: 4 variables.
```

`(overridden)` means a later file of the cascade replaced the value (here `.env.staging` replaced `.env.local`).

### 4.2 `validate`

```bash
vendor/bin/kariricode-dotenv validate [--dir] [--env] [--mode] [--schema=<file>] [--example=<file>]
```

- **Schema mode** (`--schema` given): applies the `.env.schema` rules (SPEC-006) to the loaded project.
- **Example mode** (default): every name declared in `.env.example` (or `--example`) must be defined — loaded, or present in the process environment (`$_ENV`, `$_SERVER` or `getenv()`). Variables injected by an orchestrator therefore count, even with `variables_order = "GPCS"`.

Success (STDOUT, exit 0):

```
✓ All schema rules passed.
✓ All variables from .env.example are defined.
```

Failure (STDERR, exit 1):

```
✗ Schema validation failed:
  - API_KEY is required but not defined.
```

```
✗ Missing variables:
  - DB_HOST is required but not defined.
  - DB_PASSWORD is required but not defined.
```

With neither `--schema` nor an example file: `✗ No .env.example or --schema found. Nothing to validate.` (exit 1).

### 4.3 `encrypt`

```bash
vendor/bin/kariricode-dotenv encrypt [file=.env] [--key=<hex64>] [--output=<file>]
```

Encrypts values with AES-256-GCM (SPEC-004) and rewrites the file (or writes `--output`, leaving the input untouched).

Invariant — **the application loads identical values before and after encryption**:

1. Each value is taken exactly as `DotenvParser` reads it: escapes resolved, quotes removed, inline comment dropped.
2. It is written back as `NAME="encrypted:…"` (the `export` prefix and a trailing ` # comment` are kept).
3. Comments, blank lines and multiline values (PEM keys) keep their layout.
4. Values containing `${VAR}` / `$VAR` references are **skipped** and reported: encrypting them would freeze the reference.
5. Values already encrypted are kept as they are and not counted.

Without `--key`, a new key is generated and printed **to STDOUT** — do not run it where output is logged.

```
✓ Encrypted 5 value(s) in secrets.env
  skipped DSN: contains a variable reference
Store DOTENV_PRIVATE_KEY securely — it is required for decryption.
```

```
Generated new encryption key:
DOTENV_PRIVATE_KEY=<64 hex chars>
Key ID: <8 hex chars>

✓ Encrypted 1 value(s) in other.env
Store DOTENV_PRIVATE_KEY securely — it is required for decryption.
```

### 4.4 `decrypt`

```bash
vendor/bin/kariricode-dotenv decrypt [file=.env] [--key=<hex64>] [--output=<file>]
```

The key comes from `--key`, else `DOTENV_PRIVATE_KEY` (`$_SERVER`, `$_ENV` or `getenv()`); no key is ever generated. Each decrypted value is written so the parser reads it back byte-for-byte: single quotes (literal) unless it contains `'` or a line break, otherwise double quotes with `\\`, `\"`, `\$`, `\n` and `\r` escaped.

```
✓ Decrypted 5 value(s) in secrets.env
```

Errors (STDERR, exit 1, file not written):

```
✗ No decryption key provided. Use --key=<hex> or set DOTENV_PRIVATE_KEY.
✗ [ENV_2752] Decryption failed — wrong key or corrupted payload.
```

### 4.5 `cache:dump`

```bash
vendor/bin/kariricode-dotenv cache:dump [--dir] [--env] [--mode] [--output=.env.cache.php]
```

Loads the project with `bootEnv()` and writes the cache (ADR-005). When `DOTENV_PRIVATE_KEY` is available, every cached value is stored encrypted.

```
✓ Cache written to /app/.env.cache.php (4 variable(s), environment: staging)
```

An application reuses this cache only when it boots the same way: it calls `bootEnv()`, requests the same environment (argument, `environmentName`, or process `APP_ENV`), and can decrypt it if it was written encrypted. In practice, run `cache:dump` with the same `APP_ENV` in the process — or the same `--env` — that the application will see. Any mismatch, or any change to a cascade file, makes the application parse the files instead (never serve wrong values).

### 4.6 `cache:clear`

```bash
vendor/bin/kariricode-dotenv cache:clear [--dir] [--path=.env.cache.php]
```

```
✓ Cache cleared: /app/.env.cache.php
```

A missing file is not an error.

### 4.7 `diff`

```bash
vendor/bin/kariricode-dotenv diff <file1> <file2>
```

Compares the **parsed** values (so `A="x"` and `A=x` are equal). Encrypted values are printed as `[encrypted]`; with `DOTENV_PRIVATE_KEY` available they are compared after decryption, otherwise two encryptions of the same plaintext differ (random nonce). Plaintext values are printed as they are.

```
Only in .env:
  + APP_ENV
  + APP_DEBUG
  + DB_PORT
Different values:
  ~ DB_HOST
    .env: localhost
    .env.staging: staging-db.internal
```

Identical files print `✓ Files are identical.`. The exit code is 0 whether or not differences exist; fewer than two paths prints a usage line and exits 1.

### 4.8 `example:generate`

```bash
vendor/bin/kariricode-dotenv example:generate [file=.env] [--output=.env.example]
```

Keeps comments and blank lines, and reduces every assignment to `NAME=` (with `export` if present). Multiline values are dropped entirely, so no part of a PEM key reaches the example file.

```
✓ Generated .env.example from .env
```

```ini
APP_ENV=
APP_DEBUG=
DB_HOST=
DB_PORT=
```

### 4.9 `keygen`

```bash
vendor/bin/kariricode-dotenv keygen
```

Prints the key to STDOUT — do not run it where output is logged.

```
Key ID:              <8 hex chars>
DOTENV_PRIVATE_KEY=  <64 hex chars>

Add to your server environment (NOT to .env files):
  export DOTENV_PRIVATE_KEY=<64 hex chars>
```

## 5. Exit Codes

| Code | Meaning |
|---|---|
| `0` | Success, including `help` and a `diff` that found differences. |
| `1` | Validation failure, missing file or key, wrong key, invalid option value, unknown command, or any other `DotenvException`. |

## 6. Error Output

Errors go to STDERR prefixed with `✗`; normal output goes to STDOUT, so it can be piped:

```bash
vendor/bin/kariricode-dotenv debug 2>/dev/null | grep DB_
```

Every `DotenvException` is caught at the entry point and reported as `✗ <message>` with exit 1 (no stack trace); the message starts with the exception's code, as in `✗ [ENV_2710] Environment file not found or not readable: "/app/.env"` (ADR-010).

## 7. Autoload Resolution

The CLI loads the first existing file of:

1. `__DIR__ . '/../vendor/autoload.php'` — package installed as the root project.
2. `__DIR__ . '/../../../autoload.php'` — package installed as a dependency.

If neither exists, it prints `Cannot find vendor/autoload.php. Run: composer install` to STDERR and exits 1.

## 8. Argument Parsing

No external parser (ADR-001). `parseArguments()` splits `$argv` after the command into positional arguments and options:

- `--name=value` → option with a string value;
- `--flag` → option set to `true` (e.g. `--reveal`);
- anything else → positional argument, in order.

Commands read options through `option()`, which returns only non-empty strings, so a bare `--key` is treated as absent rather than passed on as a boolean.
