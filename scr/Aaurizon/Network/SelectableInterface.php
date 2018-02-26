<?php

namespace Aaurizon\Network;

interface SelectableInterface
{
    /**
     * Called by the Selector for get the socket
     *
     * @return resource
     */
    public function getSocket();

    /**
     * Called by the Selector when it read socket
     *
     * @param string $data
     */
    public function update(string $data);

    /**
     * Called by the Selector when the socket closed
     */
    public function disconnect();
}
