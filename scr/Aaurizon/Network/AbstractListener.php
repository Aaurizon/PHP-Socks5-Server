<?php

namespace Aaurizon\Network;

abstract class AbstractListener
{
    /**
     * @var resource socket
     */
    protected $listener;

    /**
     * @var bool
     */
    protected $listening;

    /**
     * @throws \RuntimeException
     */
    public function run()
    {
        $this->listening = true;

        while ($this->listener AND $this->listening === true)
        {
            $this->loop();
        }
    }

    /**
     */
    protected function loop()
    {
        while ($socket = @socket_accept($this->listener))
        {
            if (socket_set_nonblock($socket))
            {
                $this->acceptSocket($socket);
            }
        }
    }

    /**
     * @param resource $socket
     */
    abstract protected function acceptSocket($socket);
}
