<?php

namespace Aaurizon\ProxyServer;

use Aaurizon\Network\Selector;

class ProxySocket extends AbstractSocketHandler
{
    /**
 * @var AbstractSocketHandler
 */
    protected $bond;

    /**
     * ProxySocket constructor.
     * 
     * @param resource $socket
     * @param Selector $selector
     * @param ProxySocket $bond
     */
    public function __construct($socket, Selector $selector, ProxySocket $bond)
    {
        parent::__construct($socket, $selector);

        $this->selector->attach($this);

        $this->bond = $bond;
    }

    /**
     * @param string $data
     */
    public function update(string $data)
    {
        parent::update($data);

        if ($this->bond)
        {
            $this->bond->send( $this->dataClean() );
        }
    }

    /**
     */
    public function disconnect()
    {
        parent::disconnect();

        if ($this->bond)
        {
            $this->bond->close();

            $this->bond = null;
        }
    }
}
