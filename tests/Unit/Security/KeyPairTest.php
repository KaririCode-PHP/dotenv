<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Tests\Unit\Security;

use KaririCode\Dotenv\Exception\ConfigurationException;
use KaririCode\Dotenv\Security\KeyPair;
use PHPUnit\Framework\TestCase;

final class KeyPairTest extends TestCase
{
    public function testGenerateProducesValidKeyPair(): void
    {
        $kp = KeyPair::generate();

        self::assertSame(64, strlen($kp->privateKey));
        self::assertTrue(ctype_xdigit($kp->privateKey));
        self::assertSame(8, strlen($kp->publicId));
    }

    public function testGenerateProducesUniqueKeys(): void
    {
        $kp1 = KeyPair::generate();
        $kp2 = KeyPair::generate();

        self::assertNotSame($kp1->privateKey, $kp2->privateKey);
    }

    public function testFromPrivateKeyReconstitutes(): void
    {
        $original = KeyPair::generate();
        $restored = KeyPair::fromPrivateKey($original->privateKey);

        self::assertSame($original->privateKey, $restored->privateKey);
        self::assertSame($original->publicId, $restored->publicId);
    }

    public function testFromPrivateKeyRejectsInvalidLength(): void
    {
        $this->expectException(ConfigurationException::class);

        KeyPair::fromPrivateKey('abcdef');
    }

    public function testFromPrivateKeyRejectsNonHex(): void
    {
        $this->expectException(ConfigurationException::class);

        KeyPair::fromPrivateKey(str_repeat('g', 64));
    }

    public function testGeneratedKeyWorksWithEncryptor(): void
    {
        $kp = KeyPair::generate();
        $encryptor = new \KaririCode\Dotenv\Security\Encryptor($kp->privateKey);

        $encrypted = $encryptor->encrypt('test-value');
        self::assertSame('test-value', $encryptor->decrypt($encrypted));
    }
}
