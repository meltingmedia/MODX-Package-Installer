<?php namespace meltingmedia\modx\package;

/**
 * Service class to instantiate and search providers
 */
class Provider extends Service
{
    /**
     * Store of already resolved/instantiated providers
     *
     * @var array An array of instantiated modTransportProvider
     */
    protected $providers = array();

    /**
     * Currently processed provider
     *
     * @var \modTransportProvider|null
     */
    protected $current;

    /**
     * Find a modTransportProvider with the given service_url
     *
     * @param string $url
     *
     * @return bool|\modTransportProvider
     */
    protected function find($url)
    {
        if (!array_key_exists($url, $this->providers)) {
            $loaded = $this->init($url);
            if (!$loaded) {
                $this->providers[$url] = null;

                $msg = 'Error while trying to load the provider ' . $url;
                //$this->modx->log(\modX::LOG_LEVEL_ERROR, $msg, $this->config['log_target']);
                $this->addMessage($msg);

                return false;
            }
        }

        $this->current = $this->providers[$url];
        //$this->current->getClient();

        return $this->current;
    }

    /**
     * Get the modTransportProvider using the given service_url
     *
     * @param string $url
     *
     * @return \modTransportProvider|null
     */
    public function get($url)
    {
        if (!($this->current instanceof \modTransportProvider) || $this->current->get('service_url') != $url) {
            $this->find($url);
        }

        return $this->current;
    }

    /**
     * @return \modTransportProvider|null
     */
    public function getCurrent()
    {
        return $this->current;
    }

    /**
     * Query the current provider for the given package name
     *
     * @param string $query The package name to look for
     *
     * @return bool|\modRestResponse
     */
    public function query($query)
    {
        if ($this->current instanceof \modTransportProvider) {
            /** @var \modRestResponse  $response */
            $response = $this->current->request('package', 'GET', array(
                'query' => $query,
                'php' => XPDO_PHP_VERSION,
            ));

            if ($response->isError()) {
                $msg = 'Bad response from the provider, let\'s break everything!!';
                $this->modx->log(\modX::LOG_LEVEL_ERROR, $msg, $this->config['log_target']);
                $this->addMessage($msg);

                return false;
            }

            return $response;
        }

        $this->modx->log(\modX::LOG_LEVEL_ERROR, 'Trying to query a not instantiated provider', $this->config['log_target']);

        return false;
    }

    /**
     * @param $packageName
     *
     * @return bool|\modRestResponse
     */
    public function getVersions($packageName)
    {
        if ($this->current instanceof \modTransportProvider) {
            /** @var \modRestResponse  $response */
            $response = $this->current->request('package/versions', 'GET', array(
                'package' => $packageName,
            ));

            if ($response->isError()) {
                $msg = 'Bad response from the provider, let\'s break everything!!';
                $this->modx->log(\modX::LOG_LEVEL_ERROR, $msg, $this->config['log_target']);
                $this->addMessage($msg);

                return false;
            }

            return $response;
        }

        $this->modx->log(\modX::LOG_LEVEL_ERROR, 'Trying to query a not instantiated provider', $this->config['log_target']);

        return false;
    }

    /**
     * Initialize the given Package Provider
     *
     * @param string $url The provider URL
     *
     * @return bool Either if the initialization succeed of failed
     */
    protected function init($url)
    {
        if ($this->config['debug']) {
            $this->modx->log(\modX::LOG_LEVEL_INFO, 'Looking for provider '. $url, $this->config['log_target']);
        }
        if ('local' === $url) {
            $this->modx->log(\modX::LOG_LEVEL_INFO, 'looking for local package(s)', $this->config['log_target']);
            return true;
        }
        if (!$this->providers[$url] || !$this->providers[$url] instanceof \modTransportProvider) {
            if ($this->config['debug']) {
                $this->modx->log(\modX::LOG_LEVEL_INFO, 'Instantiating provider '. $url, $this->config['log_target']);
            }
            /** @var \modTransportProvider $provider */
            $provider = $this->modx->getObject('transport.modTransportProvider', array(
                'service_url' => $url
            ));
            if ($provider) {
                if ($this->config['debug']) {
                    $this->modx->log(\modX::LOG_LEVEL_INFO, 'Provider '. $url . ' instantiated', $this->config['log_target']);
                }
                $this->providers[$url] =& $provider;

                return true;
            }
            $this->addMessage("modTransportProvider object with service_url {$url} not found");

            return false;
        }
        $this->modx->log(\modX::LOG_LEVEL_INFO, 'Getting Provider '. $url . ' instance', $this->config['log_target']);

        return true;
    }
}
