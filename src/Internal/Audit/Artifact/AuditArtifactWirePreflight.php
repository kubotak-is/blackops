<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Artifact;

final class AuditArtifactWirePreflight
{
    public static function assertFlatObject(#[\SensitiveParameter] string $bytes): void
    {
        $length = strlen($bytes);
        if ($length < 2 || $length > 1_048_575 || $bytes[0] !== '{' || $bytes[$length - 1] !== '}') {
            self::invalid();
        }

        $inString = false;
        $escaped = false;
        $members = 1;
        for ($index = 1; $index < ($length - 1); ++$index) {
            $character = $bytes[$index];
            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                switch ($character) {
                    case '\\':
                        $escaped = true;
                        break;
                    case '"':
                        $inString = false;
                        break;
                }
                continue;
            }
            switch ($character) {
                case '"':
                    $inString = true;
                    break;
                case '{':
                case '}':
                case '[':
                case ']':
                    self::invalid();
                case ',':
                    ++$members;
                    if ($members > 18) {
                        self::invalid();
                    }
                    break;
            }
        }
        if ($inString || $escaped) {
            self::invalid();
        }
    }

    private static function invalid(): never
    {
        throw new AuditArtifactException(AuditArtifactFailure::ArtifactInvalid);
    }
}
