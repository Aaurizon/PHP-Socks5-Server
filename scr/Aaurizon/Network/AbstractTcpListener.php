<?php

namespace Aaurizon\Network;

abstract class AbstractTcpListener extends AbstractListener
{
    /**
     * @var int
     */
    protected $port;

    /**
     * AbstractTcpListener constructor.
     * @param int $port
     */
    public function __construct(int $port)
    {
        $this->port = $port;
    }

    /**
     * @throws \RuntimeException
     */
    public function run()
    {
        if (!$this->listener = socket_create_listen($this->port))
        {
            throw new \RuntimeException(socket_strerror($errno = socket_last_error($this->listener)), $errno);
        }

        if (!socket_set_nonblock($this->listener))
        {
            throw new \RuntimeException(socket_strerror($errno = socket_last_error($this->listener)), $errno);
        }

        echo "Listening on port $this->port... (Type Control-C twice to stop)".PHP_EOL;

        parent::run();
    }

    /**
     * @todo Destruct Listener
     */
    public function __destruct()
    {
        if ($this->listener)
        {
            // socket_shutdown($this->listener);
            // socket_close   ($this->listener);
        }
    }
}
