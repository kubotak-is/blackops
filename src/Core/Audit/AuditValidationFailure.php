<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
enum AuditValidationFailure: string
{
    case ShapeInvalid = 'shape_invalid';
    case Utf8Invalid = 'utf8_invalid';
    case ControlInvalid = 'control_invalid';
    case NfcInvalid = 'nfc_invalid';
    case ByteBoundInvalid = 'byte_bound_invalid';
    case GrammarInvalid = 'grammar_invalid';
    case OrderOrUniquenessInvalid = 'order_or_uniqueness_invalid';
    case SemanticInvalid = 'semantic_invalid';
    case JcsBoundInvalid = 'jcs_bound_invalid';
    case HashMismatch = 'hash_mismatch';
}
