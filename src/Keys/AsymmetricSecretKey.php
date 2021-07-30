<?php
declare(strict_types=1);
namespace ParagonIE\Paseto\Keys;

use ParagonIE\ConstantTime\{
    Base64UrlSafe,
    Binary
};
use ParagonIE\Paseto\Exception\ExceptionCode;
use ParagonIE\Paseto\{
    SendingKey,
    ProtocolInterface,
    Util
};
use ParagonIE\EasyECC\ECDSA\{
    PublicKey,
    SecretKey
};
use ParagonIE\Paseto\Protocol\{
    Version1,
    Version2,
    Version3,
    Version4
};

/**
 * Class AsymmetricSecretKey
 * @package ParagonIE\Paseto\Keys
 */
class AsymmetricSecretKey implements SendingKey
{
    /** @var string $key */
    protected $key;

    /** @var ProtocolInterface $protocol */
    protected $protocol;

    /**
     * AsymmetricSecretKey constructor.
     *
     * @param string $keyData
     * @param ProtocolInterface|null $protocol
     * @throws \Exception
     * @throws \TypeError
     */
    public function __construct(
        string $keyData,
        ProtocolInterface $protocol = null
    ) {
        $protocol = $protocol ?? new Version2;

        if (
            \hash_equals($protocol::header(), Version2::HEADER)
                ||
            \hash_equals($protocol::header(), Version4::HEADER)
        ) {
            $len = Binary::safeStrlen($keyData);
            if ($len === SODIUM_CRYPTO_SIGN_KEYPAIRBYTES) {
                $keyData = Binary::safeSubstr($keyData, 0, 64);
            } elseif ($len !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
                if ($len !== SODIUM_CRYPTO_SIGN_SEEDBYTES) {
                    throw new PasetoException(
                        'Secret keys must be 32 or 64 bytes long; ' . $len . ' given.',
                        ExceptionCode::UNSPECIFIED_CRYPTOGRAPHIC_ERROR
                    );
                }
                $keypair = \sodium_crypto_sign_seed_keypair($keyData);
                $keyData = Binary::safeSubstr($keypair, 0, 64);
            }
        }
        $this->key = $keyData;
        $this->protocol = $protocol;
    }

    /**
     * Initialize a v1 secret key.
     *
     * @param string $keyMaterial
     *
     * @return self
     * @throws \Exception
     * @throws \TypeError
     */
    public static function v1(string $keyMaterial): self
    {
        return new self($keyMaterial, new Version1());
    }

    /**
     * Initialize a v2 secret key.
     *
     * @param string $keyMaterial
     *
     * @return self
     * @throws \Exception
     * @throws \TypeError
     */
    public static function v2(string $keyMaterial): self
    {
        return new self($keyMaterial, new Version2());
    }

    /**
     * Initialize a v3 secret key.
     *
     * @param string $keyMaterial
     *
     * @return self
     * @throws \Exception
     * @throws \TypeError
     */
    public static function v3(string $keyMaterial): self
    {
        return new self($keyMaterial, new Version3());
    }

    /**
     * Initialize a v4 secret key.
     *
     * @param string $keyMaterial
     *
     * @return self
     * @throws \Exception
     * @throws \TypeError
     */
    public static function v4(string $keyMaterial): self
    {
        return new self($keyMaterial, new Version4());
    }

    /**
     * Generate a secret key.
     *
     * @param ProtocolInterface|null $protocol
     * @return self
     * @throws \Exception
     * @throws \TypeError
     */
    public static function generate(ProtocolInterface $protocol = null): self
    {
        $protocol = $protocol ?? new Version2;

        if (\hash_equals($protocol::header(), Version1::HEADER)) {
            $rsa = Version1::getRsa();
            /** @var array<string, string> $keypair */
            $keypair = $rsa->createKey(2048);
            return new self(Util::dos2unix($keypair['privatekey']), $protocol);
        } elseif (\hash_equals($protocol::header(), Version3::HEADER)) {
            return new self(
                Util::dos2unix(SecretKey::generate(Version3::CURVE)->exportPem()),
                $protocol
            );
        }
        return new self(
            \sodium_crypto_sign_secretkey(
                \sodium_crypto_sign_keypair()
            ),
            $protocol
        );
    }

    /**
     * Return a base64url-encoded representation of this secret key.
     *
     * @return string
     * @throws \TypeError
     */
    public function encode(): string
    {
        return Base64UrlSafe::encodeUnpadded($this->key);
    }

    /**
     * Initialize a secret key from a base64url-encoded string.
     *
     * @param string $encoded
     * @param ProtocolInterface|null $version
     * @return self
     * @throws \Exception
     * @throws \TypeError
     */
    public static function fromEncodedString(string $encoded, ProtocolInterface $version = null): self
    {
        $decoded = Base64UrlSafe::decode($encoded);
        return new static($decoded, $version);
    }

    /**
     * Get the version of PASETO that this key is intended for.
     *
     * @return ProtocolInterface
     */
    public function getProtocol(): ProtocolInterface
    {
        return $this->protocol;
    }

    /**
     * Get the public key that corresponds to this secret key.
     *
     * @return AsymmetricPublicKey
     * @throws \Exception
     * @throws \TypeError
     */
    public function getPublicKey(): AsymmetricPublicKey
    {
        switch ($this->protocol::header()) {
            case Version1::HEADER:
                return new AsymmetricPublicKey(
                    Version1::RsaGetPublicKey($this->key),
                    $this->protocol
                );
            case Version3::HEADER:
                /** @var PublicKey $pk */
                $pk = SecretKey::importPem($this->key)->getPublicKey();
                return new AsymmetricPublicKey(
                    $pk->toString(), // Compressed point
                    $this->protocol
                );
            default:
                return new AsymmetricPublicKey(
                    \sodium_crypto_sign_publickey_from_secretkey($this->key),
                    $this->protocol
                );
        }
    }

    /**
     * Get the raw key contents.
     *
     * @return string
     */
    public function raw()
    {
        return $this->key;
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return [];
    }
}
