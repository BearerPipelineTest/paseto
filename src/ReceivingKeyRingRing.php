<?php
declare(strict_types=1);
namespace ParagonIE\Paseto;

use ParagonIE\Paseto\Exception\InvalidKeyException;
use ParagonIE\Paseto\Exception\PasetoException;
use ParagonIE\Paseto\Traits\MultiKeyTrait;

class ReceivingKeyRingRing implements KeyRingInterface, ReceivingKey
{
    use MultiKeyTrait;

    const KEY_TYPE = ReceivingKey::class;

    /** @var array<string, ReceivingKey> */
    protected $keys = [];

    /**
     * Add a key to this KeyID.
     *
     * @param string $keyId
     * @param ReceivingKey $key
     * @return static
     *
     * @throws InvalidKeyException
     * @throws PasetoException
     */
    public function addKey(string $keyId, ReceivingKey $key): self
    {
        $this->typeCheckKey($key);
        $this->keys[$keyId] = $key;
        return $this;
    }
}
