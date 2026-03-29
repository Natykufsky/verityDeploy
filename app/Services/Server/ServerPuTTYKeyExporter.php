<?php

namespace App\Services\Server;

use App\Models\Server;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class ServerPuTTYKeyExporter
{
    /**
     * @return array{putty_private_key: string}
     */
    public function export(Server $server): array
    {
        $privateKey = trim((string) ($server->ssh_key ?: $server->private_key));

        if ($privateKey === '') {
            throw new RuntimeException('The server does not have an SSH private key configured.');
        }

        if (! $this->shouldUsePuTTY()) {
            throw new RuntimeException('PuTTY is required to export a .ppk key on this machine.');
        }

        $workingDirectory = storage_path('app/ssh-keys');

        if (! File::exists($workingDirectory)) {
            File::makeDirectory($workingDirectory, 0755, true);
        }

        $inputPath = $workingDirectory.'/'.Str::uuid().'.key';
        $outputPath = $workingDirectory.'/'.Str::uuid().'.ppk';

        File::put($inputPath, $privateKey);

        try {
            $process = Process::timeout(60)->run([
                $this->puttyExecutable('puttygen.exe'),
                '-batch',
                $inputPath,
                '-O',
                'private',
                '-o',
                $outputPath,
            ]);

            if ($process->failed()) {
                throw new RuntimeException(trim($process->errorOutput() ?: $process->output()) ?: 'Unable to export the PuTTY private key.');
            }

            if (! File::exists($outputPath)) {
                throw new RuntimeException('PuTTY did not produce a .ppk export.');
            }

            $ppkContents = trim((string) File::get($outputPath));

            if ($ppkContents === '') {
                throw new RuntimeException('The generated PuTTY key was empty.');
            }

            return [
                'putty_private_key' => $ppkContents,
            ];
        } finally {
            if (File::exists($inputPath)) {
                File::delete($inputPath);
            }

            if (File::exists($outputPath)) {
                File::delete($outputPath);
            }
        }
    }

    protected function shouldUsePuTTY(): bool
    {
        return PHP_OS_FAMILY === 'Windows' && File::exists('C:\\Program Files\\PuTTY\\puttygen.exe');
    }

    protected function puttyExecutable(string $name): string
    {
        return 'C:\\Program Files\\PuTTY\\'.$name;
    }
}
