<?php

namespace App\Services\Security;

use phpseclib3\Crypt\Common\PrivateKey;
use phpseclib3\Crypt\EC;
use phpseclib3\Crypt\RSA;
use Symfony\Component\Finder\Finder;

class SshKeyService
{
    /**
     * @return array{private_key: string, public_key: string}
     */
    public function generateKeyPair(string $type = 'rsa', string $passphrase = ''): array
    {
        /** @var PrivateKey $private */
        if ($type === 'ed25519') {
            $private = EC::createKey('Ed25519');
        } else {
            $private = RSA::createKey(2048);
        }

        if (filled($passphrase)) {
            $private = $private->withPassword($passphrase);
        }

        return [
            'private_key' => $private->toString('OpenSSH'),
            'public_key' => $private->getPublicKey()->toString('OpenSSH'),
        ];
    }

    public function derivePublicKey(string $privateKey, string $passphrase = ''): ?string
    {
        try {
            /** @var PrivateKey $private */
            $private = RSA::load($privateKey, $passphrase);
        } catch (\Throwable) {
            try {
                $private = EC::load($privateKey, $passphrase);
            } catch (\Throwable) {
                return null;
            }
        }

        return $private->getPublicKey()->toString('OpenSSH');
    }

    /**
     * @return array<string, string>
     */
    public function discoverLocalKeys(?string $path = null): array
    {
        $path ??= storage_path('app/ssh_keys');
        $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? null;

        $paths = [$path];
        if ($home) {
            $paths[] = $home.DIRECTORY_SEPARATOR.'.ssh';
        }

        $keys = [];
        foreach ($paths as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $finder = (new Finder)->files()->in($dir)->ignoreDotFiles(true)->depth(0);

            foreach ($finder as $file) {
                $content = $file->getContents();
                if (str_contains($content, 'BEGIN OPENSSH PRIVATE KEY') || str_contains($content, 'BEGIN RSA PRIVATE KEY')) {
                    $keys[$file->getRealPath()] = $file->getFilename().' ('.$file->getRelativePathname().')';
                }
            }
        }

        return $keys;
    }
}
