<?php

namespace App\Services\Terminal;

use App\Models\Server;
use App\Models\ServerTerminalSession;
use phpseclib3\Crypt\PublicKeyLoader;
use RuntimeException;

class SshPtyShell
{
    protected ?InteractiveSsh2 $ssh = null;

    public function __construct(
        protected Server $server,
        protected ServerTerminalSession $session,
    ) {}

    public function connect(int $columns = 120, int $rows = 32): void
    {
        $host = (string) ($this->server->ip_address ?: $this->server->host);
        $port = (int) ($this->session->port ?: $this->server->effectiveSshPort() ?: $this->server->port ?: 22);

        $username = $this->server->connection_type === 'cpanel'
            ? $this->server->effectiveCpanelUsername()
            : $this->server->effectiveSshUser();

        $username = (string) ($username ?: $this->server->username);

        if ($host === '' || $username === '') {
            throw new RuntimeException('The server is missing SSH host or username information.');
        }

        $ssh = new InteractiveSsh2($host, $port);
        $ssh->disableStrictHostKeyChecking();
        $ssh->setTimeout(0);
        $ssh->setTerminal('xterm-256color');
        $ssh->setWindowSize($columns, $rows);

        $sshKey = $this->server->effectiveSshKey();
        $password = $this->server->effectiveSudoPassword();

        if (filled($sshKey)) {
            $passphrase = filled($this->server->passphrase) ? (string) $this->server->passphrase : null;
            $key = PublicKeyLoader::load((string) $sshKey, $passphrase);

            if (! $ssh->login($username, $key)) {
                throw new RuntimeException('SSH key login failed.');
            }
        } elseif (filled($password)) {
            if (! $ssh->login($username, (string) $password)) {
                throw new RuntimeException('SSH password login failed.');
            }
        } else {
            throw new RuntimeException('No SSH key or SSH password is configured for this server. Check its Credential Profile.');
        }

        if (! $ssh->openShell()) {
            throw new RuntimeException('Unable to open a PTY shell on the remote server.');
        }

        $this->ssh = $ssh;
    }

    public function write(string $input): void
    {
        if (! $this->ssh) {
            throw new RuntimeException('The SSH PTY shell is not connected.');
        }

        $this->ssh->write($input);
    }

    public function resize(int $columns, int $rows): void
    {
        if (! $this->ssh) {
            return;
        }

        $this->ssh->resizePTY($columns, $rows);
    }

    public function drain(): string
    {
        if (! $this->ssh) {
            return '';
        }

        $buffer = '';
        $attempts = 0;

        while ($attempts < 8) {
            $chunk = $this->ssh->read('', InteractiveSsh2::READ_SIMPLE);

            if (! is_string($chunk) || $chunk === '') {
                break;
            }

            $buffer .= $chunk;
            $attempts++;
        }

        return $buffer;
    }

    public function close(): void
    {
        if (! $this->ssh) {
            return;
        }

        try {
            $this->ssh->disconnect();
        } finally {
            $this->ssh = null;
        }
    }
}
