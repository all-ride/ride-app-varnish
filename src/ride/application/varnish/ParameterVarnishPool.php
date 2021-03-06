<?php

namespace ride\application\varnish;

use ride\library\config\Config;
use ride\library\log\Log;
use ride\library\varnish\VarnishAdmin;
use ride\library\varnish\VarnishPool;
use ride\library\varnish\VarnishServer;

/**
 * Varnish pool with the Ride parameters as backend
 */
class ParameterVarnishPool extends VarnishPool {

    /**
     * Instance of the configuration
     * @var \ride\library\config\Config
     */
    protected $config;

    /**
     * Prefix for the parameter keys
     * @var string
     */
    protected $prefix;

    /**
     * Constructs a new instance
     * @param \ride\library\config\Config $config Instance of the config
     * @param \ride\library\log\Log $log Instance of the log
     * @param string $prefix Prefix for the parameter keys
     * @return null
     */
    public function __construct(Config $config, Log $log, $prefix = 'varnish.pool') {
        $this->config = $config;
        $this->prefix = $prefix;

        $this->readServers($log);
    }

    /**
     * Reads the servers from the parameters
     * @param \ride\library\log\Log $log Instance of the log
     * @return null
     */
    protected function readServers(Log $log) {
        $pool = $this->config->get($this->prefix);
        if (!$pool) {
            return;
        }

        foreach ($pool as $name => $struct) {
            if (!isset($struct['host'])) {
                continue;
            }

            $host = $struct['host'];
            $port = isset($struct['port']) ? $struct['port'] : 6082;
            $secret = isset($struct['secret']) ? $struct['secret'] : null;

            $server = new VarnishAdmin($host, $port, $secret);
            $server->setLog($log);

            parent::addServer($server);
        }
    }

    /**
     * Adds a server to the pool
     * @param VarnishServer $server Instance of the server
     * @return null
     */
    public function addServer(VarnishServer $server) {
        parent::addServer($server);

        if (!$server instanceof VarnishAdmin) {
            return;
        }

        $name = $this->getParameterName($server);

        $pool = $this->config->get($this->prefix);
        $pool[$name] = array(
            'host' => $server->getHost(),
            'port' => $server->getPort(),
            'secret' => $server->getSecret(),
        );
        $this->config->set($this->prefix, $pool);
    }

    /**
     * Removes a single server from the pool
     * @param string $server String representation of the server
     * @return boolean True if the server was removed, false if it did not exist
     */
    public function removeServer($server) {
        parent::removeServer($server);

        $name = $this->getParameterName($server);

        $this->config->set($this->prefix . Config::TOKEN_SEPARATOR . $name, null);
    }

    /**
     * Gets the name for the server in the parameters
     * @param string|\ride\library\varnish\VarnishAdmin $server
     * @return string
     */
    protected function getParameterName($server) {
        return str_replace(array('.', ':'), '-', (string) $server);
    }

}
