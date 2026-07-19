<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Identity\PasswordHasher;
use PHPUnit\Framework\TestCase;

final class PasswordHasherTest extends TestCase
{
    public function testItUsesArgon2idAndVerifiesWithoutReflectingPlaintext(): void
    {
        $passwords = new PasswordHasher();
        $plaintext = 'correct horse battery staple';
        $hash = $passwords->hash($plaintext);

        self::assertTrue($passwords->verify($plaintext, $hash));
        self::assertFalse($passwords->verify('wrong password value', $hash));
        self::assertSame('argon2id', password_get_info($hash)['algoName']);
        self::assertStringNotContainsString($plaintext, $hash);
    }

    public function testCredentialVerificationUsesTheSameSafeFailureForUnknownAndWrongPassword(): void
    {
        $passwords = new PasswordHasher();
        $hash = $passwords->hash('the known account password');

        self::assertFalse($passwords->verifyCredential('an invalid password value', $hash));
        self::assertFalse($passwords->verifyCredential('an invalid password value', null));
        self::assertTrue($passwords->verifyCredential('the known account password', $hash));
    }
}
