<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Domain\Identity\PasswordHasher;
use PHPUnit\Framework\TestCase;

final class PasswordHasherTest extends TestCase
{
    public function testHashAndCredentialVerificationDoNotReflectPlaintext(): void
    {
        $passwords = new PasswordHasher();
        $plaintext = 'correct horse battery staple';
        $hash = $passwords->hash($plaintext);

        self::assertTrue($passwords->verifyCredential($plaintext, $hash));
        self::assertFalse($passwords->verifyCredential('wrong password value', $hash));
        self::assertFalse($passwords->verifyCredential('wrong password value', null));
        self::assertStringNotContainsString($plaintext, $hash);
    }
}
