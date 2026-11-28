# SPEC-004: Encryption

| Field | Value |
|---|---|
| **Version** | 4.5.0 |
| **Introduced** | 4.3.0 |
| **ARFA 1.3** | P1 (Immutable State), P5 (Observability) |
| **Errata** | E-011 |
| **Date** | 2026-02-28 · revised 2026-09-29 (4.5.0) |

## 1. Overview

KaririCode\Dotenv supports per-value AES-256-GCM encryption, allowing secrets to be committed to version control while remaining confidential. Decryption is transparent during `load()` — no application code changes are required.

## 2. Prerequisites

- PHP extension: ext-openssl (bundled in standard PHP distributions)
- A 256-bit encryption key (generated via `KeyPair::generate()` or the `keygen` CLI command)

## 3. Key Management

### 3.1 Key Generation

```php
use KaririCode\Dotenv\Security\KeyPair;

$keyPair = KeyPair::generate();
echo $keyPair->privateKey; // 64-char hex string (256-bit key)
echo $keyPair->publicId;   // 8-char identifier (first 8 chars of SHA-256 hash)
```

CLI equivalent:

```bash
vendor/bin/kariricode-dotenv keygen
```

### 3.2 Key Format

| Representation | Length | Example |
|---|---|---|
| Hex string | 64 characters | `a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6a7b8c9d0e1f2a3b4c5d6a7b8c9d0e1f2` |
| Raw binary | 32 bytes | *(binary)* |

The `Encryptor` constructor accepts both formats. Hex strings are auto-detected (64 chars, all hex digits) and decoded to binary internally.

### 3.3 Key Storage

The private key must **never** be committed to version control. Recommended storage:

1. **Environment variable:** `DOTENV_PRIVATE_KEY` (set by the orchestrator)
2. **CI/CD secret:** Injected during deployment
3. **Secret manager:** Retrieved at application bootstrap

### 3.4 Key Reconstitution

```php
$keyPair = KeyPair::fromPrivateKey($hexKey);
// Derives the same publicId from the key — useful for verification
```

### 3.5 Public ID

The 8-character public ID is a non-secret identifier derived from `sha256(raw_key)[0:8]`. It allows referencing keys in multi-environment setups without exposing the key material:

```
production: key_id=f7e8d9c0
staging:    key_id=a1b2c3d4
```

## 4. Wire Format

### 4.1 Encrypted Value

```
encrypted:<base64(nonce ‖ ciphertext ‖ tag)>
```

### 4.2 Binary Layout

```
Offset    Length    Field
0         12        Nonce (random IV)
12        N         Ciphertext (AES-256-GCM output, N ≥ 0)
12+N      16        Authentication Tag (GCM MAC)
```

Total binary size: `28 + plaintext_length` bytes (before base64 encoding).

### 4.3 Detection

A value is considered encrypted if and only if it starts with the ASCII prefix `encrypted:`:

```php
Encryptor::isEncrypted($value); // str_starts_with($value, 'encrypted:')
```

## 5. Encryption Process

```
Input:   plaintext string
Output:  "encrypted:" + base64(nonce + ciphertext + tag)

1. Generate 12 random bytes (nonce) via random_bytes(12)
2. Encrypt using openssl_encrypt():
   - Algorithm: aes-256-gcm
   - Key: 32-byte binary key
   - IV: nonce (12 bytes)
   - Options: OPENSSL_RAW_DATA
   - Tag output: 16 bytes
3. Concatenate: nonce + ciphertext + tag
4. Base64-encode the concatenation
5. Prepend "encrypted:" prefix
```

## 6. Decryption Process

```
Input:   "encrypted:" + base64_payload
Output:  plaintext string

1. Verify prefix "encrypted:" — if absent, return value unchanged (passthrough)
2. Strip prefix, base64_decode the remainder
3. Validate minimum length: ≥ 28 bytes (12 nonce + 0 ciphertext + 16 tag)
4. Extract:
   - nonce:      bytes[0..11]
   - tag:        bytes[-16..]
   - ciphertext: bytes[12..-16]
5. Decrypt using openssl_decrypt():
   - Algorithm: aes-256-gcm
   - Key: 32-byte binary key
   - IV: nonce
   - Options: OPENSSL_RAW_DATA
   - Tag: 16-byte tag
6. If openssl_decrypt returns false → throw `EncryptionException::decryptionFailed()`
7. Return plaintext
```

## 7. Integration with Load Pipeline

Decryption happens in `Dotenv::setVariable()` after filtering and the load-mode check, before type detection:

```
Value from the parser (or cache)
  → allow/deny check
  → load-mode check (ADR-006)
  → starts with "encrypted:"?  yes + key → decrypt   (no key → kept as the literal string)
  → type detection → casting → processors → EnvironmentVariable
```

- `EnvironmentVariable::$rawValue` holds the decrypted string, which is also what `$_ENV`/`$_SERVER` receive; `$encrypted` is `true` when the source stored the value encrypted. `debug()` reports it, and the CLI masks those values (SPEC-008).
- **References to encrypted values receive the plaintext.** A same-file reference is decrypted through `InterpolationContext::$valueFilter`; a reference to an earlier file uses the already-decrypted value. `DATABASE_URL="pgsql://app:${DB_PASSWORD}@db/app"` therefore works when `DB_PASSWORD` is encrypted. <!-- secret-scan:allow -->
- **Cache:** with a key configured, `dumpCache()` stores every value encrypted, including plaintext derived from secrets (ADR-005).

## 8. Configuration

### 8.1 Via DotenvConfiguration

```php
$config = new DotenvConfiguration(
    encryptionKey: 'a1b2c3d4...',  // 64-char hex
);
```

### 8.2 Via Environment Variable

If `DotenvConfiguration::$encryptionKey` is null, the Dotenv constructor checks, in order:
1. `$_SERVER['DOTENV_PRIVATE_KEY']`
2. `$_ENV['DOTENV_PRIVATE_KEY']`
3. `getenv('DOTENV_PRIVATE_KEY')` — for runtimes that expose it only to the process environment (e.g. PHP-FPM with `clear_env = no`)

An empty string counts as no key.

This allows setting the key once in the container orchestrator.

### 8.3 No Key Configured

If no encryption key is available, encrypted values remain as-is (the literal `encrypted:...` string). No error is thrown — this allows committed `.env` files with encrypted values to be loaded in environments where decryption is not needed (e.g., build systems that only need non-secret variables).

## 9. Security Properties

| Property | Guarantee |
|---|---|
| Confidentiality | AES-256 encryption (256-bit key space) |
| Integrity | GCM authentication tag (128-bit MAC) |
| Nonce uniqueness | 96-bit random nonce per encryption (2⁹⁶ space) |
| No padding oracle | GCM is a streaming mode — no padding |
| Key validation | Rejects keys ≠ 32 bytes at construction time |
| Timing safety | `openssl_decrypt()` uses constant-time tag comparison internally |

## 10. Error Conditions

All errors are `KaririCode\Dotenv\Exception\EncryptionException` (extends `DotenvException`) created by named factories (E-011). Messages never contain key material or plaintext.

| Condition | Factory | Message |
|---|---|---|
| Key is not 32 raw bytes or 64 hex chars | `invalidKey()` | "Encryption key must be 32 raw bytes or a 64-character hex string (256-bit)." |
| `KeyPair::fromPrivateKey()` input is not 64 hex chars | `invalidPrivateKey()` | "Private key must be a 64-character hex string (256-bit)." |
| `openssl_encrypt()` fails | `encryptionFailed($reason)` | "Encryption failed: {openssl_error_string}" |
| Payload is not base64 or shorter than 28 bytes | `malformedPayload()` | "Invalid encrypted payload: malformed base64 or too short." |
| Wrong key, tampering or corruption (tag mismatch) | `decryptionFailed()` | "Decryption failed — wrong key or corrupted payload." |

**Change in 4.5.0:** 4.4 threw `\InvalidArgumentException` / `\RuntimeException`. Code catching those must catch `EncryptionException` (or `DotenvException`) instead.

## 11. CLI Commands

Full behavior and real output: SPEC-008 §4.3, §4.4 and §4.9.

- `keygen` — prints a new key and its 8-character ID.
- `encrypt [file] [--key] [--output]` — encrypts each value exactly as the parser reads it, so the application loads identical values afterwards; values with `${VAR}`/`$VAR` references are skipped (encrypting them would freeze the reference); layout, comments and multiline values are preserved. Without `--key`, a new key is generated and printed.
- `decrypt [file] [--key] [--output]` — writes each plaintext in a quoting the parser reads back byte-for-byte; a wrong key leaves the file untouched and exits 1.
