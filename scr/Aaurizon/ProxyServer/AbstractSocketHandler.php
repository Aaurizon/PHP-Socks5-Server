<?php

namespace Aaurizon\ProxyServer;

use Aaurizon\Network\SelectableInterface;
use Aaurizon\Network\Selector;

abstract class AbstractSocketHandler implements SelectableInterface
{
    /**
     * @var resource
     */
    protected $socket;

    /**
     * @var Selector
     */
    protected $selector;

    /**
     * @var string binary string
     */
    protected $data = '';

    /**
     * ProxySocket constructor.
     *
     * @param resource $socket
     * @param Selector $selector
     */
    public function __construct($socket, Selector $selector)
    {
        $this->socket = $socket;
        $this->selector = $selector;
    }

    /**
     * @return resource
     */
    public function getSocket()
    {
        return $this->socket;
    }

    /**
     * @param string $data
     */
    public function update(string $data)
    {
        $this->data.= $data;
    }

    /**
     * @return string
     */
    public function data() : string
    {
        return $this->data;
    }

    /**
     * @return string
     */
    public function dataClean() : string
    {
        $data = $this->data;

        $this->clean();

        return $data;
    }

    /**
     */
    public function clean()
    {
        $this->data = '';
    }

    /**
     * @param string $data
     * @return bool TRUE on success else FALSE
     */
    public function send(string $data)
    {
        $size = strlen($data);

        $sent = socket_write($this->socket, $data, $size);

        // socket_write return FALSE on failure
        if ($sent === false)
        {
            $this->selector->error($this);
            return false;
        }

        // Check if the entire message has been sented
        if ($sent < $size)
        {
            // If not sent the entire message.
            // Send the part of the message that has not yet been sented
            return $this->send(substr($data, $sent));
        }

        return true;
    }

    /**
     * @todo destruct socket
     */
    public function disconnect()
    {
        $this->selector = null;
    }

    /**
     */
    public function close()
    {
        if ($this->selector)
        {
            $this->selector->detach($this);
        }
    }

    /**
     */
    public function __destruct()
    {
        $this->close();
    }
}
