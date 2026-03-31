<?php

declare(strict_types=1);

namespace App\Tests\Unit\Channel;

use App\Channel\ChannelCredentialVault;
use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;

final class ChannelCredentialVaultTest extends Unit
{
    private Connection&MockObject $connection;
    private ChannelCredentialVault $vault;
    private string $encryptionKey = 'test-encryption-key-for-unit-tests';

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->vault = new ChannelCredentialVault($this->connection, $this->encryptionKey);
    }

    public function testEncryptAndDecryptRoundtrip(): void
    {
        $plaintext = 'my-secret-bot-token-12345';

        $encrypted = $this->vault->encrypt($plaintext);

        $this->assertNotSame($plaintext, $encrypted);
        $this->assertNotEmpty($encrypted);

        $decrypted = $this->vault->decryptRaw($encrypted);

        $this->assertSame($plaintext, $decrypted);
    }

    public function testEncryptProducesDifferentOutputEachTime(): void
    {
        $plaintext = 'my-secret-token';

        $encrypted1 = $this->vault->encrypt($plaintext);
        $encrypted2 = $this->vault->encrypt($plaintext);

        // Each encryption uses a random nonce, so output differs
        $this->assertNotSame($encrypted1, $encrypted2);

        // But both decrypt to the same plaintext
        $this->assertSame($plaintext, $this->vault->decryptRaw($encrypted1));
        $this->assertSame($plaintext, $this->vault->decryptRaw($encrypted2));
    }

    public function testDecryptRawThrowsOnInvalidBase64(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to decode credential: invalid base64');

        $this->vault->decryptRaw('not-valid-base64!!!');
    }

    public function testDecryptRawThrowsOnTamperedData(): void
    {
        $encrypted = $this->vault->encrypt('original-token');

        // Tamper with the encrypted data
        $decoded = base64_decode($encrypted, true);
        $tampered = base64_encode($decoded.'tampered');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to decrypt credential');

        $this->vault->decryptRaw($tampered);
    }

    public function testDecryptFetchesFromDatabaseAndDecrypts(): void
    {
        $plaintext = 'bot-token-from-db';
        $encrypted = $this->vault->encrypt($plaintext);

        $this->connection->expects($this->once())
            ->method('fetchAssociative')
            ->with(
                $this->stringContains('channel_instances'),
                ['id' => 'instance-123'],
            )
            ->willReturn(['credential_encrypted' => $encrypted]);

        $result = $this->vault->decrypt('instance-123');

        $this->assertSame($plaintext, $result);
    }

    public function testDecryptThrowsWhenInstanceNotFound(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No credential found for channel instance "missing-id"');

        $this->vault->decrypt('missing-id');
    }

    public function testDecryptThrowsWhenCredentialEmpty(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['credential_encrypted' => '']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No credential found for channel instance "instance-456"');

        $this->vault->decrypt('instance-456');
    }

    public function testGetCredentialRefReturnsDecryptedToken(): void
    {
        $plaintext = 'my-channel-token';
        $encrypted = $this->vault->encrypt($plaintext);

        $this->connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['credential_encrypted' => $encrypted]);

        $result = $this->vault->getCredentialRef('instance-789');

        $this->assertSame($plaintext, $result);
    }
}
