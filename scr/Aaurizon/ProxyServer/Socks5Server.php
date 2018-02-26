<?php

namespace Aaurizon\ProxyServer;

use Aaurizon\Network\AbstractTcpListener as Listener;
use Aaurizon\Network\Selector;

class Socks5Server extends Listener
{
    /**
     * @var Selector
     */
    protected $selector;

    /**
     * SocksServer constructor.
     *
     * @param int $port 1080 (default port)
     */
    public function __construct($port = 1080)
    {
        parent::__construct($port);

        $this->selector = new Selector();
    }

    /**
     * @param resource $socket
     */
    protected function acceptSocket($socket)
    {
        $this->selector->attach(new SocksSocket($socket, $this->selector));
    }

    /**
     */
    protected function loop()
    {
        parent::loop();

        $this->selector->loop();
    }

    /**
     * @todo Destruct Selector (all socket in selector)
     */
    public function __destruct()
    {
        if ($this->selector)
        {
            // ...
        }

        parent::__destruct();
    }
}
