<?php namespace Melting\MODX\Package;

use modX;
use modTransportProvider;

/**
 * A wrapper for modTransportProvider
 */
class Provider
{
    /**
     * @var modX
     */
    public $modx;
    /**
     * @var modTransportProvider
     */
    protected $provider;
    /**
     * Whether or not the provider supports listing package version
     *
     * @see Provider::supportsVersions()
     *
     * @var null|bool
     */
    protected $versions = null;

    public function __construct(modX $modx, array $data = array())
    {
        $this->modx = $modx;
    }

    /**
     * Set the modTransportProvider for this Provider
     *
     * @param modTransportProvider $provider
     *
     * @return bool Whether or not the provider is usable/verified
     */
    public function fromProvider(modTransportProvider $provider)
    {
        $this->provider = $provider;

        return $this->verify();
    }

    /**
     * Retrieve/create a modTransportProvider from the given data
     *
     * @param array $data
     *
     * @return bool Whether or not the provider is usable/verified
     */
    public function fromArray(array $data)
    {
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Populating provider from array with data '. print_r($data, true));
        // First try to grab an existing provider
        $this->provider = $this->modx->getObject('transport.modTransportProvider', $data);
        if (!$this->provider instanceof modTransportProvider) {
            $this->modx->log(modX::LOG_LEVEL_INFO, 'Creating a new provider...');
            // Then create it
            $this->provider = $this->modx->newObject('transport.modTransportProvider');
            $this->provider->fromArray($data, '', true);
        } else {
            $this->modx->log(modX::LOG_LEVEL_INFO, 'Existing provider found');
        }

        return $this->verify();
    }

    /**
     * Search the given package from the provider
     *
     * @param string $name
     * @param array $options
     *
     * @return Package|void
     */
    public function search($name, array $options = array())
    {
        $this->modx->log(modX::LOG_LEVEL_INFO, __METHOD__ . " Searching provider with regular query {$name} ". print_r($options, true));
        $response = $this->request('package', array(
            'query' => $name,
        ));
        if (!$response) {
            $this->modx->log(modX::LOG_LEVEL_INFO, __METHOD__ . ' no response');
            return;
        }

        if ($response->isError()) {
            // Return/log error
            $this->modx->log(modX::LOG_LEVEL_INFO, __METHOD__ . ' response with error');
            return;
        }
        //$response->modx = null;
        //$response->client = null;
        //$this->modx->log(modX::LOG_LEVEL_INFO, __METHOD__ . ' loading response '. print_r($response, true));

        $packages = simplexml_load_string($response->response);
        /** @var \simpleXMLElement $xml */
        foreach ($packages as $xml) {
            $package = new Package($this->modx);
            $package->setProvider($this)->fromXml($xml);

            if ($package->matches($name, $options)) {
                $this->modx->log(modX::LOG_LEVEL_INFO, 'Found');
                return $package;
            }
        }
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Nada ? '. print_r($packages, true));
    }

    /**
     * Search available versions for the provider (if supported by the provider)
     *
     * @param $package
     *
     * @return \SimpleXMLElement[]
     * @throws \Exception
     */
    public function searchVersions($package)
    {
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Searching for versions...');
        $versions = array();
        if (!$this->supportsVersions()) {
            $this->modx->log(modX::LOG_LEVEL_INFO, 'Provider appears not to support versions listing...');
            return $versions;
        }
        $response = $this->request('package/versions', array(
            'package' => $package
        ));
        if ($response && $response->response) {
            try {
                $packages = simplexml_load_string($response->response);
            } catch (\Exception $e) {
                $this->modx->log(modX::LOG_LEVEL_INFO, 'Provider appears not to support versions');
                $packages = array();
            }

            $this->modx->log(modX::LOG_LEVEL_INFO, 'Found versions : '. count($packages));
            foreach ($packages as $version) {
                $this->modx->log(modX::LOG_LEVEL_INFO, (string) $version->display_name);
                $versions[] = $version;
            }
        }

        return $versions;
    }

    /**
     * Method to "guess" if the provider supports listing package versions
     *
     * @return bool
     */
    protected function supportsVersions()
    {
        if (is_null($this->versions)) {
            $url = $this->provider->get('service_url') . 'package/versions';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_exec($ch);

            $info = curl_getinfo($ch);
            //$this->modx->log(modX::LOG_LEVEL_INFO, 'curl info : '. print_r($info, true));

            if (isset($info['http_code']) && $info['http_code'] !== 404) {
                // We expect response to say "method not allowed" (error 400);
                $this->versions = true;
            } else {
                // Assume provider supports versions. Some providers return a 200 status while not supporting versions listing, should be harmless since we will never have results from those providers
                $this->versions = false;
            }
        }

        return $this->versions;
    }

    /**
     * Convenient method to retrieve an attribute from the modTransportProvider object
     *
     * @param string $key
     *
     * @return mixed
     */
    public function get($key)
    {
        if ($this->provider) {
            return $this->provider->get($key);
        }
    }

    /**
     * Check if the provider is a valid provider & is usable/up. If the modTransportProvider object is new & validates, it will be saved
     *
     * @param array $params
     *
     * @return bool
     * @throws \Exception
     */
    protected function verify(array $params = array())
    {
        $response = $this->request('verify', $params);
        if ($response->isError()) {
            $this->modx->log(modX::LOG_LEVEL_INFO, __METHOD__ . ' error verifying provider'.' '. $this->provider->service_url);
            return false;
        }

        $response = simplexml_load_string($response->response);
        $verified = (bool) $response->verified;
        if ($verified && $this->provider->isNew()) {
            $this->provider->save();
            $this->modx->log(modX::LOG_LEVEL_INFO, 'Saved provider data');
        }

        return $verified;
    }

    /**
     * Perform a request against the modTransportProvider
     *
     * @param string $path
     * @param array $params
     *
     * @return mixed
     * @throws \Exception
     */
    protected function request($path, array $params = array())
    {
        if (!$this->provider instanceof modTransportProvider) {
            throw new \Exception('No modTransportProvider object is attached...');
        }

        return $this->provider->request($path, 'GET', $params);
    }
}
