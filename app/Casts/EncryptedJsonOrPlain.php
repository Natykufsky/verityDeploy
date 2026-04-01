<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use JsonException;

class EncryptedJsonOrPlain implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $decrypted = Crypt::decryptString($value);

            return $this->decode($decrypted) ?? $decrypted;
        } catch (DecryptException) {
            return $this->decode((string) $value) ?? $value;
        }
    }

    public function set($model, string $key, $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '') {
                return null;
            }

            return Crypt::encryptString($trimmed);
        }

        return Crypt::encryptString(json_encode($value, JSON_THROW_ON_ERROR));
    }

    protected function decode(string $value): mixed
    {
        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }
}
