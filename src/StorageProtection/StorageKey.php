<?php

declare(strict_types=1);

namespace BlackOps\StorageProtection;

use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;

#[PublicApi]
final readonly class StorageKey
{
    private const int MATERIAL_LENGTH = 32;

    private string $id;

    private \SensitiveParameterValue $material;

    /**
     * @param string $material A raw 32-byte key. The value is never included in
     *                         string, JSON, exception, or debug representations.
     */
    public function __construct(string $id, #[\SensitiveParameter] string $material)
    {
        if (!preg_match('/^[A-Za-z0-9]+(?:[._:\/-][A-Za-z0-9]+)*$/D', $id) || strlen($id) > 128) {
            throw new InvalidArgumentException('Storage key identifier is invalid.');
        }

        if (strlen($material) !== self::MATERIAL_LENGTH) {
            throw new InvalidArgumentException('Storage key material must be 32 bytes.');
        }

        $this->id = $id;
        $this->material = new \SensitiveParameterValue($material);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function material(): string
    {
        return (string) $this->material->getValue();
    }

    /** @return array{id: string} */
    public function __debugInfo(): array
    {
        return ['id' => $this->id];
    }
}
