# SPEC-002: .env File Syntax

| Field | Value |
|---|---|
| **Version** | 4.5.0 |
| **Introduced** | 4.0.0 |
| **ARFA 1.3** | P4 (Protocol Agnosticism) |
| **Errata** | — |
| **Date** | 2026-02-28 · revised 2026-09-29 (4.5.0) |

## 1. Overview

This specification defines the `.env` file syntax supported by KaririCode\Dotenv. The syntax is a superset of POSIX shell variable assignment, compatible with Docker `.env` files and Bash variable declarations.

## 2. File Encoding

Files must be UTF-8 encoded. BOM (Byte Order Mark) is not supported. Line endings are normalized: CRLF (`\r\n`) and CR (`\r`) are converted to LF (`\n`) before parsing.

## 3. Line Types

### 3.1 Empty Lines

Empty lines and lines containing only whitespace are ignored.

### 3.2 Comments

Lines where the first non-whitespace character is `#` are comments and are ignored entirely.

```ini
# This is a comment
  # This is also a comment (leading whitespace allowed)
```

### 3.3 Variable Assignments

```
NAME=VALUE
```

The `=` separator is required. Whitespace around `=` is stripped from the name (right side) and value (left side):

```ini
FOO = bar        # name="FOO", value="bar"
FOO =bar         # name="FOO", value="bar"
FOO= bar         # name="FOO", value="bar"
```

### 3.4 Bare Names

A line with a valid name but no `=` is treated as an empty string assignment:

```ini
FOO              # name="FOO", value=""
```

### 3.5 Export Prefix

The `export` keyword is silently stripped:

```ini
export FOO=bar   # equivalent to FOO=bar
```

## 4. Variable Names

### 4.1 Default Mode (strictNames: false)

Pattern: `[A-Za-z_][A-Za-z0-9_.]*`

Valid: `FOO`, `foo_bar`, `App.Config`, `_PRIVATE`, `DB_HOST_1`
Invalid: `1FOO` (starts with digit), `FOO-BAR` (contains hyphen), `FOO BAR` (contains space)

### 4.2 Strict Mode (strictNames: true)

Pattern: `[A-Z][A-Z0-9_]*`

Valid: `FOO`, `DB_HOST`, `APP_ENV_1`
Invalid: `foo` (lowercase), `_FOO` (starts with underscore), `App.Config` (dots and lowercase)

Strict mode enforces the POSIX convention for environment variable names.

## 5. Values

### 5.1 Unquoted Values

Everything after `=` until the end of line, with:
- Trailing inline comments stripped: `FOO=bar # comment` → `"bar"`
- Leading and trailing whitespace trimmed
- Variable interpolation applied (§6)

```ini
FOO=hello world           # "hello world"
FOO=hello world # note    # "hello world"
```

### 5.2 Double-Quoted Values

Delimited by `"..."`. Support:
- Escape sequences (§5.4)
- Variable interpolation (§6)
- Multiline values (§5.5)

```ini
FOO="hello world"         # "hello world"
FOO="hello # world"       # "hello # world" (# is not a comment inside quotes)
```

### 5.3 Single-Quoted Values

Delimited by `'...'`. Contents are literal — no escape processing, no interpolation.

```ini
FOO='hello $WORLD'        # "hello $WORLD" (literal dollar sign)
FOO='hello\nworld'        # "hello\nworld" (literal backslash-n)
```

### 5.4 Escape Sequences (Double-Quoted Only)

| Sequence | Result |
|---|---|
| `\n` | Newline (LF) |
| `\r` | Carriage return (CR) |
| `\t` | Tab |
| `\"` | Literal double quote |
| `\\` | Literal backslash |
| `\$` | Literal dollar sign, never interpolated (see §6.6) |
| `\x` (other) | Literal `\x` (unknown escapes pass through) |

### 5.5 Multiline Values

Double-quoted values can span multiple lines. The newlines are preserved literally:

```ini
RSA_KEY="-----BEGIN RSA PRIVATE KEY-----
MIIEpAIBAAKCAQEA...
-----END RSA PRIVATE KEY-----"
```

Single-quoted values cannot span multiple lines — an unterminated single quote on the same line is a parse error.

### 5.6 Empty Values

```ini
FOO=                      # "" (empty string)
FOO=""                    # "" (empty string)
FOO=''                    # "" (empty string)
```

## 6. Variable Interpolation

Interpolation is applied in **unquoted** and **double-quoted** values. Single-quoted values are never interpolated.

### 6.1 Resolution Order

A reference `NAME` resolves to the first match of:

1. **Locked values** — under `LoadMode::SkipExisting`, a variable that already existed in the process: its effective value wins even over a definition in the same file (ADR-006).
2. **Earlier lines of the same file** (top-to-bottom; later lines are not visible yet).
3. **Earlier files of the same load** — e.g. `.env` when parsing `.env.local`. Works even with `populateEnv`/`populateServer` disabled.
4. `$_ENV`, then `$_SERVER`.
5. Unset — expands to `""` (or triggers `:-` / `:+`).

A same-file reference to an `encrypted:` value receives the decrypted plaintext when a key is configured (SPEC-004 §7). These inputs are carried by `Core\InterpolationContext`; `DotenvParser::parse()` used on its own resolves only 2 and 4.

### 6.2 Brace Syntax

```ini
FOO=${BAR}                # Value of BAR
FOO=${BAR:-default}       # BAR if set and non-empty, else "default"
FOO=${BAR:+alternate}     # "alternate" if BAR is set and non-empty, else ""
```

### 6.3 Bare Syntax

```ini
FOO=$BAR                  # Value of BAR (terminated by non-alphanumeric/non-underscore)
FOO=$BAR_BAZ              # Value of BAR_BAZ (underscores are part of the name)
```

### 6.4 Operator Semantics

| Operator | BAR is set and non-empty | BAR is unset or empty |
|---|---|---|
| `${BAR}` | Value of BAR | `""` |
| `${BAR:-default}` | Value of BAR | `"default"` |
| `${BAR:+alternate}` | `"alternate"` | `""` |

### 6.5 Operands Are Literal

An operand is plain text: neither `${...}` nor `$NAME` inside it is expanded.

```ini
BAZ=fallback
FOO=${BAR:-${BAZ}}        # "${BAZ}"  (BAR unset) — the operand ends at the first "}"
FOO2=${BAR:-$BAZ}         # "$BAZ"
```

For a default that comes from another variable, repeat its value: `FOO=${BAR:-fallback}`.

### 6.6 Single-Pass Expansion

Each value is expanded in one pass. Text produced by a substitution is never scanned again, so a value containing `$` is inserted verbatim:

```ini
PASSWORD='pa$word'
DSN="user:${PASSWORD}@host"   # "user:pa$word@host"
PRICE="costs \$5 or $5"       # "costs $5 or $5" — "\$" is literal; "$5" is not a reference
TRAIL="\$HOME"                # "$HOME" — never expanded
```

A `$` not followed by `{NAME` or a letter/underscore is kept as-is. **Change in 4.5.0:** 4.4 expanded `${...}` and `$NAME` in two passes, so `DSN` above became `user:pa@host`, and `\$` was expanded anyway.

## 7. Inline Comments

Comments starting with `#` preceded by whitespace are stripped from **unquoted values only**:

```ini
FOO=bar # comment         # "bar"
FOO="bar # not comment"   # "bar # not comment"
FOO='bar # not comment'   # "bar # not comment"
FOO=bar#notacomment       # "bar#notacomment" (no preceding whitespace)
```

## 8. Error Handling

| Condition | Exception |
|---|---|
| Invalid variable name | `ParseException::invalidVariableName()` |
| Unterminated double quote | `ParseException::unterminatedQuote()` |
| Unterminated single quote | `ParseException::unterminatedQuote()` |

All exceptions include the line number and file path for diagnostics.

## 9. Grammar (Informal BNF)

```
file        = { line LF }
line        = empty | comment | assignment
empty       = [ WS ]
comment     = [ WS ] "#" TEXT
assignment  = [ "export" WS ] NAME [ WS ] "=" [ WS ] value
NAME        = [A-Za-z_] [A-Za-z0-9_.]*
value       = double_quoted | single_quoted | unquoted
double_quoted = '"' { DQ_CHAR | escape | interpolation } '"'
single_quoted = "'" { SQ_CHAR } "'"
unquoted    = { UQ_CHAR | interpolation } [ WS "#" TEXT ]
escape      = "\" ( "n" | "r" | "t" | '"' | "\" | "$" | ANY )
interpolation = "$" REF_NAME | "${" REF_NAME [ ( ":-" | ":+" ) OPERAND ] "}"
REF_NAME    = [A-Za-z_] [A-Za-z0-9_]*          (no dots: "$A.B" is "$A" followed by ".B")
OPERAND     = { any character except "}" }     (literal text, §6.5)
```
