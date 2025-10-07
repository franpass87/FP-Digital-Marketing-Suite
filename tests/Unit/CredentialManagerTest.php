<?php

declare(strict_types=1);

use FP\DMS\Infra\CredentialManager;
use PHPUnit\Framework\TestCase;

final class CredentialManagerTest extends TestCase
{
    public function testEncryptDecryptRoundtrip(): void
    {
        $_ENV['FPDMS_CREDENTIAL_KEY'] = str_repeat('k', 32);
        $cm = new CredentialManager();
        $aad = ['client_id' => 42];

        $cipher = $cm->encrypt('secret', $aad);
        $plain = $cm->decrypt($cipher, $aad);

        $this->assertSame('secret', $plain);
    }

    public function testDecryptWithWrongAadFails(): void
    {
        $_ENV['FPDMS_CREDENTIAL_KEY'] = str_repeat('k', 32);
        $cm = new CredentialManager();
        $cipher = $cm->encrypt('secret', ['client_id' => 1]);

        $this->expectException(RuntimeException::class);
        $cm->decrypt($cipher, ['client_id' => 2]);
    }
}


