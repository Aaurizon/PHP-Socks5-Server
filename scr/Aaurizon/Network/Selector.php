<?php

namespace Aaurizon\Network;

class Selector
{
    /**
     * @var \SplObjectStorage
     */
    protected $selectables;

    /**
     * Selector constructor.
     */
    public function __construct()
    {
        $this->selectables = new \SplObjectStorage();
    }

    /**
     * @param SelectableInterface $selectable
     */
    public function attach(SelectableInterface $selectable)
    {
        $this->selectables->attach($selectable);
    }

    /**
     * @param SelectableInterface $selectable
     */
    public function detach(SelectableInterface $selectable)
    {
        if ($this->selectables->contains($selectable))
        {
            $this->selectables->detach($selectable);

            $selectable->disconnect();
        }
    }

    /**
     * @todo socket_select()
     */
    public function loop()
    {
        /** @var SelectableInterface $selectable */
        foreach ($this->selectables as $selectable)
        {
            if ($data = @socket_read($selectable->getSocket(), 4096))
            {
                $selectable->update($data);
            }
            else
            {
                $this->error($selectable);
            }
        }
    }

    /**
     * @param SelectableInterface $selectable
     */
    public function error(SelectableInterface $selectable)
    {
        $errno = socket_last_error($selectable->getSocket());

        switch ($errno)
        {
            case 0: break;
            case 11: break; // EWOULDBLOCK
            case 115: break; // EINPROGRESS
            case 10035: break;

            // Disconnect
            case 10053:
            case 10054:
                $this->detach($selectable);
                break;

            // Unknown
            default:
                $errmsg = socket_strerror($errno);
                //file_put_contents('php://stderr', $errmsg.PHP_EOL);
                trigger_error($errmsg, E_USER_WARNING);

                $this->detach($selectable);
                break;
        }
    }
}
