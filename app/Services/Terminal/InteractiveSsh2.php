<?php

namespace App\Services\Terminal;

use phpseclib3\Common\Functions\Strings;
use phpseclib3\Net\SSH2;

class InteractiveSsh2 extends SSH2
{
    public function resizePTY(int $columns, int $rows, int $widthPixels = 0, int $heightPixels = 0): void
    {
        if (! $this->isShellOpen()) {
            $this->setWindowSize($columns, $rows);

            return;
        }

        $packet = Strings::packSSH2(
            'CNsbNNNN',
            NET_SSH2_MSG_CHANNEL_REQUEST,
            $this->getInteractiveChannelId(),
            'window-change',
            false,
            $columns,
            $rows,
            $widthPixels,
            $heightPixels,
        );

        $this->send_binary_packet($packet);
        $this->setWindowSize($columns, $rows);
    }
}
