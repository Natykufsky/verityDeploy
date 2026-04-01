<?php

namespace App\Services\ServerMetrics;

use App\Models\Server;
use RuntimeException;
use Throwable;

class ServerMetricsWebSocketBridge
{
    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $clients = [];

    public function __construct(
        protected ServerMetricsBridgeAuth $auth,
    ) {
    }

    public function serve(string $host, int $port): void
    {
        $server = stream_socket_server(sprintf('tcp://%s:%d', $host, $port), $errno, $errstr);

        if (! is_resource($server)) {
            throw new RuntimeException(sprintf('Unable to start server metrics bridge on %s:%d: %s (%d)', $host, $port, $errstr, $errno));
        }

        stream_set_blocking($server, false);

        echo sprintf("server metrics bridge listening on ws://%s:%d\n", $host, $port);

        $lastSweep = microtime(true);

        while (true) {
            $read = [$server];
            foreach ($this->clients as $client) {
                $read[] = $client['socket'];
            }

            $write = [];
            $except = [];

            $changed = @stream_select($read, $write, $except, 1);
            if ($changed === false) {
                continue;
            }

            if (in_array($server, $read, true)) {
                $socket = @stream_socket_accept($server, 0);

                if (is_resource($socket)) {
                    stream_set_blocking($socket, false);
                    $this->clients[(int) $socket] = [
                        'socket' => $socket,
                        'buffer' => '',
                        'handshake' => false,
                        'authenticated' => false,
                        'server_id' => null,
                        'token' => null,
                        'last_checksum' => null,
                        'last_snapshot' => null,
                    ];
                }
            }

            foreach ($this->clients as $id => $client) {
                if (! in_array($client['socket'], $read, true)) {
                    continue;
                }

                $this->readFromClient($id);
            }

            if (microtime(true) - $lastSweep >= (int) config('server_metrics.bridge.poll_interval', 5)) {
                $lastSweep = microtime(true);
                $this->broadcastSnapshots();
            }
        }
    }

    protected function readFromClient(int $id): void
    {
        if (! isset($this->clients[$id])) {
            return;
        }

        $chunk = @fread($this->clients[$id]['socket'], 8192);

        if ($chunk === '' && feof($this->clients[$id]['socket'])) {
            $this->closeClient($id, 1000, 'Client disconnected.');

            return;
        }

        if ($chunk !== false && $chunk !== '') {
            $this->clients[$id]['buffer'] .= $chunk;
        }

        if (! $this->clients[$id]['handshake']) {
            $this->processHandshake($id);

            return;
        }

        while (true) {
            $frame = $this->decodeFrame($this->clients[$id]['buffer']);

            if (! $frame) {
                break;
            }

            $this->handleFrame($id, $frame);

            if (! isset($this->clients[$id])) {
                return;
            }
        }
    }

    protected function processHandshake(int $id): void
    {
        $buffer = $this->clients[$id]['buffer'];
        $position = strpos($buffer, "\r\n\r\n");

        if ($position === false) {
            return;
        }

        $request = substr($buffer, 0, $position);
        $this->clients[$id]['buffer'] = substr($buffer, $position + 4);

        $headerLines = preg_split('/\R/', $request) ?: [];
        $requestLine = array_shift($headerLines) ?: '';
        [$method, $path] = array_pad(explode(' ', trim((string) $requestLine), 3), 3, null);

        if (strtoupper((string) $method) !== 'GET') {
            $this->sendHttpError($id, 405, 'Method not allowed');

            return;
        }

        $headers = [];
        foreach ($headerLines as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = array_map('trim', explode(':', $line, 2));
            $headers[strtolower($name)] = $value;
        }

        $query = [];
        if (is_string($path)) {
            $pathParts = parse_url($path);
            parse_str($pathParts['query'] ?? '', $query);
        }

        $serverId = (int) ($query['server_id'] ?? 0);
        $token = (string) ($query['token'] ?? '');

        if ($serverId <= 0 || $token === '') {
            $this->sendHttpError($id, 400, 'Missing server metrics credentials.');

            return;
        }

        $server = Server::query()->find($serverId);
        if (! $server || ! $this->auth->validate($server, $token)) {
            $this->sendHttpError($id, 403, 'Server metrics authentication failed.');

            return;
        }

        $key = trim((string) ($headers['sec-websocket-key'] ?? ''));
        if ($key === '') {
            $this->sendHttpError($id, 400, 'Missing websocket key.');

            return;
        }

        $accept = base64_encode(hash('sha1', $key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        $response = implode("\r\n", [
            'HTTP/1.1 101 Switching Protocols',
            'Upgrade: websocket',
            'Connection: Upgrade',
            'Sec-WebSocket-Accept: '.$accept,
            '',
            '',
        ]);

        @fwrite($this->clients[$id]['socket'], $response);

        $this->clients[$id]['handshake'] = true;
        $this->clients[$id]['authenticated'] = true;
        $this->clients[$id]['server_id'] = $server->id;
        $this->clients[$id]['token'] = $token;

        $this->sendJson($id, [
            'type' => 'ready',
            'snapshot' => $this->snapshotFor($server),
        ]);
    }

    protected function handleFrame(int $id, array $frame): void
    {
        $opcode = (int) ($frame['opcode'] ?? 0);
        $payload = (string) ($frame['payload'] ?? '');

        if ($opcode === 0x8) {
            $this->closeClient($id, 1000, 'Client closed the session.');

            return;
        }

        if ($opcode === 0x9) {
            $this->sendFrame($id, $payload, 0xA);

            return;
        }

        if ($opcode !== 0x1) {
            return;
        }

        $message = json_decode($payload, true);
        if (! is_array($message)) {
            return;
        }

        $type = (string) ($message['type'] ?? '');

        if ($type === 'ping') {
            $this->sendJson($id, ['type' => 'pong']);
        }
    }

    protected function broadcastSnapshots(): void
    {
        foreach (array_keys($this->clients) as $id) {
            if (! isset($this->clients[$id]) || ! ($this->clients[$id]['authenticated'] ?? false)) {
                continue;
            }

            $serverId = (int) ($this->clients[$id]['server_id'] ?? 0);
            if ($serverId <= 0) {
                continue;
            }

            $server = Server::query()->find($serverId);
            if (! $server) {
                $this->sendJson($id, [
                    'type' => 'error',
                    'message' => 'Server no longer exists.',
                ]);
                $this->closeClient($id, 1000, 'Server missing.');

                continue;
            }

            $snapshot = $this->snapshotFor($server);
            $checksum = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');

            if ($checksum === ($this->clients[$id]['last_checksum'] ?? null)) {
                continue;
            }

            $this->clients[$id]['last_checksum'] = $checksum;
            $this->clients[$id]['last_snapshot'] = $snapshot;

            $this->sendJson($id, [
                'type' => 'snapshot',
                'snapshot' => $snapshot,
            ]);
        }
    }

    protected function snapshotFor(Server $server): array
    {
        $metrics = $server->fresh()->metrics ?? [];

        return [
            'summary' => [
                'Status' => $server->status,
                'Connection' => $server->connection_type,
                'SSH port' => $server->ssh_port ?? 'n/a',
                'Last connected' => $server->last_connected_at?->format('Y-m-d H:i:s') ?? 'Never',
            ],
            'metrics' => $metrics,
            'updated_at' => $server->updated_at?->toIso8601String(),
        ];
    }

    protected function sendJson(int $id, array $payload): void
    {
        $this->sendFrame($id, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');
    }

    protected function sendFrame(int $id, string $payload, int $opcode = 0x1): void
    {
        if (! isset($this->clients[$id]['socket'])) {
            return;
        }

        $length = strlen($payload);
        $frame = chr(0x80 | ($opcode & 0x0F));

        if ($length <= 125) {
            $frame .= chr($length);
        } elseif ($length <= 0xFFFF) {
            $frame .= chr(126).pack('n', $length);
        } else {
            $high = intdiv($length, 4294967296);
            $low = $length % 4294967296;
            $frame .= chr(127).pack('N2', $high, $low);
        }

        $frame .= $payload;

        @fwrite($this->clients[$id]['socket'], $frame);
    }

    protected function decodeFrame(string &$buffer): ?array
    {
        if (strlen($buffer) < 2) {
            return null;
        }

        $first = ord($buffer[0]);
        $second = ord($buffer[1]);
        $opcode = $first & 0x0F;
        $masked = (bool) ($second & 0x80);
        $length = $second & 0x7F;
        $offset = 2;

        if ($length === 126) {
            if (strlen($buffer) < 4) {
                return null;
            }

            $length = unpack('n', substr($buffer, 2, 2))[1];
            $offset = 4;
        } elseif ($length === 127) {
            if (strlen($buffer) < 10) {
                return null;
            }

            $parts = unpack('N2', substr($buffer, 2, 8));
            $length = ((int) $parts[1] << 32) | (int) $parts[2];
            $offset = 10;
        }

        if ($masked) {
            if (strlen($buffer) < $offset + 4 + $length) {
                return null;
            }

            $mask = substr($buffer, $offset, 4);
            $offset += 4;
        } else {
            if (strlen($buffer) < $offset + $length) {
                return null;
            }

            $mask = '';
        }

        $payload = substr($buffer, $offset, $length);
        $buffer = (string) substr($buffer, $offset + $length);

        if ($masked && $mask !== '') {
            $unmasked = '';
            for ($i = 0, $max = strlen($payload); $i < $max; $i++) {
                $unmasked .= $payload[$i] ^ $mask[$i % 4];
            }
            $payload = $unmasked;
        }

        return [
            'opcode' => $opcode,
            'payload' => $payload,
        ];
    }

    protected function sendHttpError(int $id, int $status, string $message): void
    {
        if (! isset($this->clients[$id]['socket'])) {
            return;
        }

        $response = implode("\r\n", [
            sprintf('HTTP/1.1 %d %s', $status, $message),
            'Content-Type: text/plain; charset=utf-8',
            'Connection: close',
            '',
            $message,
        ]);

        @fwrite($this->clients[$id]['socket'], $response);
        $this->closeClient($id, $status, $message);
    }

    protected function closeClient(int $id, int $code = 1000, ?string $message = null): void
    {
        if (! isset($this->clients[$id])) {
            return;
        }

        if (isset($this->clients[$id]['socket']) && is_resource($this->clients[$id]['socket'])) {
            @fwrite($this->clients[$id]['socket'], $this->closeFrame($code, $message ?? ''));
            @fclose($this->clients[$id]['socket']);
        }

        unset($this->clients[$id]);
    }

    protected function closeFrame(int $code, string $message): string
    {
        $payload = pack('n', $code).$message;
        $length = strlen($payload);
        $frame = chr(0x88);

        if ($length <= 125) {
            $frame .= chr($length);
        } elseif ($length <= 0xFFFF) {
            $frame .= chr(126).pack('n', $length);
        } else {
            $high = intdiv($length, 4294967296);
            $low = $length % 4294967296;
            $frame .= chr(127).pack('N2', $high, $low);
        }

        return $frame.$payload;
    }
}
