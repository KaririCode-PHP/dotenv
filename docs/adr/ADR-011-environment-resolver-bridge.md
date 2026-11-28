# ADR-011: The EnvironmentResolver Bridge Lives in Dotenv

## Status

Accepted — V4.5.0

## Date

2026-11-28

## Context

kariricode/contract (since 2.9) defines `Environment\EnvironmentResolver`, "the
one contract through which components read the environment": the
configurator interpolates `${VAR}` through it, and the container and CLI
overrides read through it, so tests can supply a fixed environment and
sources can be composed. Its documentation names "Dotenv (the loaded .env
store)" as one implementation.

Up to 4.5.0 nothing implemented it for the .env store: a component that
wanted the loaded values read `$_ENV`, `$_SERVER` or `getenv()`, which
mixes the store with whatever the process defined, depends on
`populateEnv` and `variables_order`, and cannot be replaced in a test. The
inventory of 2026-11-27 left open where the bridge belongs (decision D14):
in this package, next to the store it reads, or in kariricode/configurator,
its first consumer.

### Prior Art

- kariricode/contract 2.9: `Environment\EnvironmentResolver` (`get()`,
  `has()`; one snapshot; `has()` true exactly when `get()` is not null).
- The configurator 4.2 integration draft, never released: a
  `Bridge\DotenvEnvironmentResolver` over an array of loaded values, with
  an optional fallback to the process environment through a
  `SystemFallbackResolver`, built by `fromLoaded()` from any object with
  an `all()` method.
- kariricode/event-dispatcher ADR-011 and kariricode/parser's
  `Integration` namespace: a component ships the bridges from its own
  types to the contract, and the consumer depends on the contract only.

## Decision

1. `KaririCode\Dotenv\Integration\DotenvEnvironmentResolver` implements
   the contract over a `Dotenv` instance. `get()` returns the variable's
   decrypted raw string (`EnvironmentVariable::$rawValue`), the form the
   contract speaks, before type casting and processors; `has()` is true
   exactly when `get()` is not null.
2. It answers only for variables the instance loaded, after the allow and
   deny lists: a value only the process defines is another resolver's to
   give, so an application composes this one with a resolver of the system
   environment when it wants both.
3. It wraps the instance rather than copying its store, so a container can
   register it before the application boots; it defines nothing until the
   instance has loaded, and the store does not change once loading is done,
   which is the snapshot the contract asks for.
4. The bridge lives here (D14): it needs no dependency the package lacks,
   and kariricode/configurator depends only on the contract.

## Consequences

### Positive

- The configurator, the container and the CLI read the .env store through
  the contract, with no dependency on this package and no reads of the
  process globals.
- Tests replace the environment with any implementation of the contract.

### Negative

- Reading a variable before `load()` returns null instead of failing;
  the precondition is documented on the class.
- An application that wants process variables too composes two resolvers;
  the package ships no system resolver, which belongs with the consumers
  of the contract.

## Alternatives Considered

| Alternative | Why not |
|---|---|
| The bridge in kariricode/configurator | the configurator would depend on this package for one class, and every other consumer of the contract would repeat it |
| Return the typed value | the contract returns `?string`; types are the caller's choice, as `Dotenv::get()` gives them |
| Fall back to `$_ENV`, `$_SERVER` and `getenv()`, as the configurator 4.2 integration draft's `fromLoaded(systemFallback: true)` did | the answer would depend on process settings this package does not control, the store could no longer be told apart from the process, and kariricode/configurator already composes resolvers (`CompositeEnvironmentResolver`, `SystemEnvironmentResolver`) |
| Build from any object with an `all()` method, as that draft did | duck typing the contract exists to replace; 4.5.0 exposes `variables()`, and the bridge takes the `Dotenv` type |
| Copy the store when built | a resolver built before boot would stay empty for good |

## References

- kariricode/contract 2.9 and later: `Environment\EnvironmentResolver`.
- kariricode/event-dispatcher ADR-011.
- ADR-002, ADR-004, ADR-007.
