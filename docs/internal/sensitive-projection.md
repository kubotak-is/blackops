# Sensitive Projection

Sensitive projection is the framework-owned filtering boundary used before journal or logging data is sent to observers.

The canonical journal remains the durable source of truth. Observer and logging adapters must receive only projected data.

Canonical operation metadata keeps actor IDs and types for audit. Before an operation reaches an observer, the projector builds separate operation metadata and replaces every non-null origin, authorization, and execution actor ID with `[masked]`. Actor types and null relationships remain available for diagnostics.

## Metadata

Applications can mark public properties with `#[Sensitive]`.

The default behavior omits the field from projected output.

```php
use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\Attribute\SensitiveMode;

final readonly class LoginValue
{
    public function __construct(
        public string $username,
        #[Sensitive]
        public string $password,
        #[Sensitive(SensitiveMode::Mask)]
        public string $email,
        #[Sensitive(SensitiveMode::Hash)]
        public string $customerId,
    ) {}
}
```

Supported modes:

| Mode | Projection behavior |
| --- | --- |
| `Omit` | Field is removed. |
| `Mask` | Field is replaced with a fixed mask token. |
| `Hash` | Field is replaced with an HMAC-SHA-256 value. |

Hash mode requires an HMAC key. Plain hashes are not used.

## Defensive fallback

Array projection also omits reserved key patterns such as password, token, and secret. This is a defensive fallback for logger contexts and external error details.

Typed property metadata remains the primary signal when projecting objects.

## Current boundary

The framework projects typed journal data and actor IDs before observer delivery. JSONL encodes only the projected operation and data. Canonical storage remains a separate port and does not apply observer masking.
