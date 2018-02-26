<?php

namespace Aaurizon\ProxyServer;

use Aaurizon\Network\Selector;
use Aaurizon\ProxyServer\AbstractSocketHandler as grandparent;

/**
 * Class SocksSocket
 *
 * @link https://www.ietf.org/rfc/rfc1928
 * @link https://www.ietf.org/rfc/rfc1929
 */
class SocksSocket extends ProxySocket
{
    const VERSION = 0x05;

    const METHOD_ANONYMOUS = 0x00; // NO AUTHENTICATION REQUIRED
    const METHOD_GSSAPI    = 0x01; // GSSAPI
    const METHOD_USERPWD   = 0x02; // USERNAME/PASSWORD
    const METHOD_DENY      = 0xFF; // NO ACCEPTABLE METHODS

    const COMMAND_CONNECT       = 0x01;
    const COMMAND_BIND          = 0x02;
    const COMMAND_UDP_ASSOCIATE = 0x03;

    const ATYP_IP_V4      = 0x01;
    const ATYP_DOMAINNAME = 0x03;
    const ATYP_IP_V6      = 0x04;

    /**
     * @var bool|null
     *
     * null = nothing
     * false = waiting auth
     * true = authentified
     */
    protected $authentication = null;

    /**
     * @var int
     *
     * COMMAND_CONNECT or COMMAND_BIND or COMMAND_UDP_ASSOCIATE
     */
    protected $command = 0;

    /**
     * SocksSocket constructor.
     *
     * @param resource $socket
     * @param Selector $selector
     */
    public function __construct($socket, Selector $selector)
    {
        grandparent::__construct($socket, $selector);

        //socket_getpeername($socket, $addr, $port);
        //$this->log("New user $addr:$port");
    }

    /**
     * @param string $data
     */
    public function update(string $data)
    {
        grandparent::update($data);

        if ($this->bond)
        {
            $this->forward();
        }
        else if ($this->authentication === true)
        {
            $this->request();
        }
        else if ($this->authentication === false)
        {
            // AUTHENTIFICATE BY METHOD 0x??
        }
        else //if ($this->auth === null)
        {
            $this->authenticate();
        }
    }

    /**
     */
    protected function authenticate()
    {
        // Note: array key starting from 1
        $bytes = unpack('C*', $this->data());

        if (count($bytes) < 2)
        {
            return;
        }

        $version  = $bytes[1];
        $methods  = [];
        $nmethods = $bytes[2];

        if ($version != static::VERSION)
        {
            $this->close();
            return;
        }

        if (count($bytes) < 2+$nmethods)
        {
            return;
        }

        if (count($bytes) != 2+$nmethods)
        {
            $this->close();
            return;
        }

        for ($i = 0 ; $i < $nmethods ; $i++)
        {
            $methods[$bytes[3+$i]] = $bytes[3+$i];
        }

        //trigger_error("Methods #$nmethods ".implode(', ', $methods), E_USER_NOTICE);

        if (!$this->send(pack('C*', static::VERSION, static::METHOD_ANONYMOUS)))
        {
            $this->close();
            return;
        }

        $this->authentication = true;

        $this->clean();
    }

    /**
     */
    protected function request()
    {
        // Note: array key starting from 1
        $bytes = unpack('C*', $this->data());

        if (count($bytes) < 4)
        {
            return;
        }

        $version = $bytes[1]; // VERSION x05
        $command = $bytes[2]; // COMMAND TCP/IP
        $reserve = $bytes[3]; // RESERVE x00

        if ($version != static::VERSION)
        {
            $this->close();
            return;
        }

        if ($reserve != 0x00)
        {
            $this->close();
            return;
        }

        switch ($this->command = $command)
        {
            case static::COMMAND_CONNECT: break;

            default:
                trigger_error("Command #$this->command Not Implemented", E_USER_WARNING);
                $this->close();
                return;
        }

        $addrtyp = $bytes[4];

        switch ($addrtyp)
        {
            case static::ATYP_IP_V4:
                if (count($bytes) < 10)
                {
                    return;
                }
                if (count($bytes) != 10)
                {
                    $this->close();
                    return;
                }

                $ip = '';
                for ($i=0 ; $i < 4 ; $i++)
                {
                    $ip.= chr($bytes[5+$i]);
                }

                $byte1 = $bytes[5+$i+0];
                $byte2 = $bytes[5+$i+1];
                $port  = (int) unpack('n', chr($byte1).chr($byte2))[1]; // offset start at 1

                $this->requestServer(inet_ntop($ip), $port);

                break;

            case static::ATYP_DOMAINNAME:
                if (count($bytes) < 7)
                {
                    return;
                }
                $domainsize = $bytes[5];
                if (count($bytes) < 7+$domainsize)
                {
                    return;
                }
                if (count($bytes) != 7+$domainsize OR $domainsize == 0)
                {
                    $this->close();
                    return;
                }

                $domainname = '';
                for ($i=0 ; $i < $domainsize ; $i++)
                {
                    $domainname.= chr($bytes[6+$i]);
                }

                $byte1 = $bytes[6+$i+0];
                $byte2 = $bytes[6+$i+1];

                $domainport = (int) unpack('n', chr($byte1).chr($byte2))[1]; // offset start at 1

                $this->requestServerDomain($domainname, $domainport);

                break;

            case static::ATYP_IP_V6:
                if (count($bytes) < 22)
                {
                    return;
                }
                if (count($bytes) != 22)
                {
                    $this->close();
                    return;
                }

                $ip = '';
                for ($i=0 ; $i < 16 ; $i++)
                {
                    $ip.= chr($bytes[5+$i]);
                }

                $byte1 = $bytes[5+$i+0];
                $byte2 = $bytes[5+$i+1];
                $port  = (int) unpack('n', chr($byte1).chr($byte2))[1]; // start offset 1

                $this->requestServerIpV6(inet_ntop($ip), $port);

                break;

            default:
                trigger_error("Command #$this->command Not Implemented", E_USER_WARNING);
                $this->close();
                return;
        }

        if (!$this->bond)
        {
            $this->close();
            return;
        }
    }

    /**
     * @param string $ip
     * @param int $port
     * @todo return error response (check RFC)
     */
    protected function requestServer($ip, int $port)
    {
        $ipb = inet_pton($ip);

        if (strlen($ipb) != 4
            OR !$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)
            OR !socket_connect($socket, $ip, $port)
            OR !socket_set_nonblock($socket))
        {
            $this->close();
            return;
        }

        $this->bond = new ProxySocket($socket, $this->selector, $this);

        $this->clean();

        $this->send(pack('C*', ...[
            static::VERSION,
            0x00, // success
            0x00, // reserved
            static::ATYP_IP_V4,
            ord($ipb[0]),
            ord($ipb[1]),
            ord($ipb[2]),
            ord($ipb[3]),
            $port >> 8 & 0xff,
            $port >> 0 & 0xff,
        ]));
    }

    /**
     * @param string $ip
     * @param int $port
     * @todo return error response (check RFC)
     */
    protected function requestServerIpV6($ip, int $port)
    {
        $ipb = inet_pton($ip);

        if (strlen($ipb) != 16
            OR !$socket = socket_create(AF_INET6, SOCK_STREAM, SOL_TCP)
            OR !socket_connect($socket, $ip, $port)
            OR !socket_set_nonblock($socket))
        {
            $this->close();
            return;
        }

        $this->bond = new ProxySocket($socket, $this->selector, $this);

        $this->clean();

        $this->send(pack('C*', ...[
            static::VERSION,
            0x00, // success
            0x00, // reserved
            static::ATYP_IP_V6,
            ord($ipb[0]),
            ord($ipb[1]),
            ord($ipb[2]),
            ord($ipb[3]),
            ord($ipb[4]),
            ord($ipb[5]),
            ord($ipb[6]),
            ord($ipb[7]),
            ord($ipb[8]),
            ord($ipb[9]),
            ord($ipb[10]),
            ord($ipb[11]),
            ord($ipb[12]),
            ord($ipb[13]),
            ord($ipb[14]),
            ord($ipb[15]),
            $port >> 8 & 0xff,
            $port >> 0 & 0xff,
        ]));
    }

    /**
     * @param $domain
     * @param int $port
     */
    protected function requestServerDomain($domain, int $port)
    {
        $ip = gethostbyname($domain);

        $this->requestServer($ip, $port);
    }

    /**
     */
    protected function forward()
    {
        switch ($this->command)
        {
            case static::COMMAND_CONNECT:
                $result = $this->bond->send( $this->dataClean() );
                break;

            default:
                trigger_error("Command #$this->command Not Implemented (client side)", E_USER_WARNING);
                $this->close();
                break;
        }
    }
}
