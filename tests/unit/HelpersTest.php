<?php

namespace app\tests\unit;

use Codeception\Test\Unit;
use Smoren\EncryptionTools\Exceptions\SymmetricEncryptionException;
use Smoren\EncryptionTools\Helpers\AsymmetricEncryptionHelper;
use Smoren\EncryptionTools\Exceptions\AsymmetricEncryptionException;
use Smoren\EncryptionTools\Helpers\AsymmetricLargeDataEncryptionHelper;
use Smoren\EncryptionTools\Helpers\SymmetricEncryptionHelper;

class HelpersTest extends Unit
{
    /**
     * @throws SymmetricEncryptionException
     */
    public function testSymmetricEncryption()
    {
        $data = [1, 2, 3, "asd", "test" => "фыв"];
        $secretKey = uniqid();

        $dataEncrypted = SymmetricEncryptionHelper::encrypt($data, $secretKey);
        $dataDecrypted = SymmetricEncryptionHelper::decrypt($dataEncrypted, $secretKey);
        $this->assertEquals($data, $dataDecrypted);

        $dataEncrypted = SymmetricEncryptionHelper::encrypt($data, $secretKey, 'aes-128-cbc');
        $dataDecrypted = SymmetricEncryptionHelper::decrypt($dataEncrypted, $secretKey, 'aes-128-cbc');
        $this->assertEquals($data, $dataDecrypted);

        $dataEncrypted = SymmetricEncryptionHelper::encrypt($data, $secretKey, 'camellia-256-ofb');
        $dataDecrypted = SymmetricEncryptionHelper::decrypt($dataEncrypted, $secretKey, 'camellia-256-ofb');
        $this->assertEquals($data, $dataDecrypted);

        try {
            SymmetricEncryptionHelper::decrypt($dataEncrypted, uniqid());
        } catch(SymmetricEncryptionException $e) {
            $this->assertEquals(SymmetricEncryptionException::CANNOT_DECRYPT, $e->getCode());
        }

        try {
            SymmetricEncryptionHelper::encrypt($dataEncrypted, $secretKey, 'unknown-method');
        } catch(SymmetricEncryptionException $e) {
            $this->assertEquals(SymmetricEncryptionException::UNKNOWN_METHOD, $e->getCode());
        }

        try {
            SymmetricEncryptionHelper::decrypt($dataEncrypted, $secretKey, 'unknown-method');
        } catch(SymmetricEncryptionException $e) {
            $this->assertEquals(SymmetricEncryptionException::UNKNOWN_METHOD, $e->getCode());
        }
    }

    /**
     * @throws AsymmetricEncryptionException
     */
    public function testAsymmetricEncryption()
    {
        $data = [1, 2, 3, "asd", "test" => "фыв"];

        [$privateKey, $publicKey] = AsymmetricEncryptionHelper::generateKeyPair();
        [$anotherPrivateKey, $anotherPublicKey] = AsymmetricEncryptionHelper::generateKeyPair();

        $dataEncrypted = AsymmetricEncryptionHelper::encryptByPrivateKey($data, $privateKey);
        $dataDecrypted = AsymmetricEncryptionHelper::decryptByPublicKey($dataEncrypted, $publicKey);
        $this->assertEquals($data, $dataDecrypted);

        try {
            AsymmetricEncryptionHelper::decryptByPublicKey($dataEncrypted, $anotherPublicKey);
            $this->fail();
        } catch(AsymmetricEncryptionException $e) {
            $this->assertEquals(AsymmetricEncryptionException::CANNOT_DECRYPT, $e->getCode());
        }

        try {
            AsymmetricEncryptionHelper::decryptByPublicKey($dataEncrypted, 'invalid_key_format_value');
            $this->fail();
        } catch(AsymmetricEncryptionException $e) {
            $this->assertEquals(AsymmetricEncryptionException::INVALID_KEY_FORMAT, $e->getCode());
        }

        $dataEncrypted = AsymmetricEncryptionHelper::encryptByPublicKey($data, $publicKey);
        $dataDecrypted = AsymmetricEncryptionHelper::decryptByPrivateKey($dataEncrypted, $privateKey);
        $this->assertEquals($data, $dataDecrypted);

        try {
            AsymmetricEncryptionHelper::decryptByPrivateKey($dataEncrypted, $anotherPrivateKey);
            $this->fail();
        } catch(AsymmetricEncryptionException $e) {
            $this->assertEquals(AsymmetricEncryptionException::CANNOT_DECRYPT, $e->getCode());
        }

        try {
            AsymmetricEncryptionHelper::decryptByPrivateKey($dataEncrypted, 'invalid_key_format_value');
            $this->fail();
        } catch(AsymmetricEncryptionException $e) {
            $this->assertEquals(AsymmetricEncryptionException::INVALID_KEY_FORMAT, $e->getCode());
        }
    }

    /**
     * @throws AsymmetricEncryptionException
     */
    public function testAsymmetricSinging()
    {
        $data = [1, 2, 3, "asd", "test" => "фыв"];
        $anotherData = [1, 2, 3];

        [$privateKey, $publicKey] = AsymmetricEncryptionHelper::generateKeyPair();
        [$anotherPrivateKey, $anotherPublicKey] = AsymmetricEncryptionHelper::generateKeyPair();

        $signature = AsymmetricEncryptionHelper::sign($data, $privateKey);
        AsymmetricEncryptionHelper::verify($data, $signature, $publicKey);

        try {
            AsymmetricEncryptionHelper::verify($data, $signature, $anotherPublicKey);
            $this->fail();
        } catch(AsymmetricEncryptionException $e) {
            $this->assertEquals(AsymmetricEncryptionException::CANNOT_VERIFY, $e->getCode());
        }

        try {
            AsymmetricEncryptionHelper::verify($data, $signature.'2', $publicKey);
            $this->fail();
        } catch(AsymmetricEncryptionException $e) {
            $this->assertEquals(AsymmetricEncryptionException::CANNOT_VERIFY, $e->getCode());
        }

        try {
            AsymmetricEncryptionHelper::verify($anotherData, $signature, $publicKey);
            $this->fail();
        } catch(AsymmetricEncryptionException $e) {
            $this->assertEquals(AsymmetricEncryptionException::CANNOT_VERIFY, $e->getCode());
        }

        try {
            AsymmetricEncryptionHelper::verify($data, $signature, 'invalid_public_key');
            $this->fail();
        } catch(AsymmetricEncryptionException $e) {
            $this->assertEquals(AsymmetricEncryptionException::INVALID_KEY_FORMAT, $e->getCode());
        }

        try {
            AsymmetricEncryptionHelper::sign($data, 'invalid_public_key');
            $this->fail();
        } catch(AsymmetricEncryptionException $e) {
            $this->assertEquals(AsymmetricEncryptionException::INVALID_KEY_FORMAT, $e->getCode());
        }
    }

    /**
     * @throws AsymmetricEncryptionException
     * @throws SymmetricEncryptionException
     */
    public function testTogether()
    {
        $data = "some secret string";
        $passphrase = uniqid();

        [$privateKey, $publicKey] = AsymmetricEncryptionHelper::generateKeyPair();
        $privateKeyEncrypted = SymmetricEncryptionHelper::encrypt($privateKey, $passphrase);
        $dataEncrypted = AsymmetricEncryptionHelper::encryptByPublicKey($data, $publicKey);

        $privateKeyDecrypted = SymmetricEncryptionHelper::decrypt($privateKeyEncrypted, $passphrase);
        $dataDecrypted = AsymmetricEncryptionHelper::decryptByPrivateKey($dataEncrypted, $privateKeyDecrypted);

        $this->assertEquals($data, $dataDecrypted);
    }

    public function testLargeData()
    {
        [$privateKey, $publicKey] = AsymmetricLargeDataEncryptionHelper::generateKeyPair();

        $data = $this->generateRandomString(100000);
        $dataEncrypted = AsymmetricLargeDataEncryptionHelper::encryptByPrivateKey($data, $privateKey);
        $dataDecrypted = AsymmetricLargeDataEncryptionHelper::decryptByPublicKey($dataEncrypted, $publicKey);
        $this->assertEquals($data, $dataDecrypted);

        $data = $this->generateRandomString(100000);
        $dataEncrypted = AsymmetricLargeDataEncryptionHelper::encryptByPublicKey($data, $publicKey);
        $dataDecrypted = AsymmetricLargeDataEncryptionHelper::decryptByPrivateKey($dataEncrypted, $privateKey);
        $this->assertEquals($data, $dataDecrypted);
    }

    public function testMultilevelSymmetric()
    {
        $data = ['1', '2', '3'];

        $key1 = uniqid().'1';
        $dataEncrypted1 = SymmetricEncryptionHelper::encrypt($data, $key1);

        $key2 = uniqid().'2';
        $dataEncrypted2 = SymmetricEncryptionHelper::encrypt($dataEncrypted1, $key2);

        $key3 = uniqid().'3';
        $dataEncrypted3 = SymmetricEncryptionHelper::encrypt($dataEncrypted2, $key3);

        $dataDecrypted2 = SymmetricEncryptionHelper::decrypt($dataEncrypted3, $key3);
        $dataDecrypted1 = SymmetricEncryptionHelper::decrypt($dataDecrypted2, $key2);
        $dataDecrypted = SymmetricEncryptionHelper::decrypt($dataDecrypted1, $key1);

        $this->assertEquals($data, $dataDecrypted);
    }

    public function testMultilevelAsymmetric()
    {
        $data = ['1', '2', '3'];

        [$privateKey1, $publicKey1] = AsymmetricLargeDataEncryptionHelper::generateKeyPair();
        $dataEncrypted1 = AsymmetricLargeDataEncryptionHelper::encryptByPublicKey($data, $publicKey1);

        [$privateKey2, $publicKey2] = AsymmetricLargeDataEncryptionHelper::generateKeyPair();
        $dataEncrypted2 = AsymmetricLargeDataEncryptionHelper::encryptByPublicKey($dataEncrypted1, $publicKey2);

        [$privateKey3, $publicKey3] = AsymmetricLargeDataEncryptionHelper::generateKeyPair();
        $dataEncrypted3 = AsymmetricLargeDataEncryptionHelper::encryptByPublicKey($dataEncrypted2, $publicKey3);

        $dataDecrypted2 = AsymmetricLargeDataEncryptionHelper::decryptByPrivateKey($dataEncrypted3, $privateKey3);
        $dataDecrypted1 = AsymmetricLargeDataEncryptionHelper::decryptByPrivateKey($dataDecrypted2, $privateKey2);
        $dataDecrypted = AsymmetricLargeDataEncryptionHelper::decryptByPrivateKey($dataDecrypted1, $privateKey1);

        $this->assertEquals($data, $dataDecrypted);
    }

    public function testTooLargeData()
    {
        [$privateKey, $publicKey] = AsymmetricEncryptionHelper::generateKeyPair();

        $input = $this->generateRandomString(243);
        AsymmetricEncryptionHelper::encryptByPrivateKey($input, $privateKey);
        AsymmetricEncryptionHelper::encryptByPublicKey($input, $publicKey);

        $input = $this->generateRandomString(250);
        try {
            AsymmetricEncryptionHelper::encryptByPrivateKey($input, $privateKey);
            $this->assertTrue(false);
        } catch(AsymmetricEncryptionException $e) {
            $this->assertEquals(AsymmetricEncryptionException::CANNOT_ENCRYPT, $e->getCode());
        }
        try {
            AsymmetricEncryptionHelper::encryptByPublicKey($input, $publicKey);
            $this->assertTrue(false);
        } catch(AsymmetricEncryptionException $e) {
            $this->assertEquals(AsymmetricEncryptionException::CANNOT_ENCRYPT, $e->getCode());
        }
    }

    protected function generateRandomString(int $length): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';

        for($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }
}
