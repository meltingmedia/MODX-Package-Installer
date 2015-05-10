<?php namespace Melting\MODX\Package;

use modX;

/**
 * A class to find packages either locally or within providers
 */
class Finder
{
    /**
     * @var modX
     */
    public $modx;
    /**
     * A "cache" for instantiated providers
     *
     * @var Provider[]
     */
    protected $providers = array();

    public function __construct(modX $modx)
    {
        $this->modx = $modx;

        // Make sure we can manipulate transport related objects
        $this->modx->addPackage('modx.transport', $this->modx->getOption('core_path') . 'model/');
        $this->modx->lexicon->load('workspace');

        // Instantiate/cache available providers
        $this->loadAvailableProviders();
    }

    /**
     * Convenient method to search for a package either locally (already downloaded) or on providers
     *
     * @param string $packageName
     * @param array $options
     *
     * @return Package|void
     */
    public function search($packageName, array $options = array())
    {
        // First search locally
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Searching locally');
        $found = $this->searchLocally($packageName, $options);
        if ($found) {
            return $found;
        }

        $this->modx->log(modX::LOG_LEVEL_INFO, 'Package not found locally, searching on providers');
        // If not found, search within providers, unless a dedicated provider is given in the options
        $found = $this->searchProviders($packageName, $options);
        if ($found) {
            return $found;
        }
    }

    /**
     * Search for the given package locally
     *
     * @param string $packageName
     * @param array $options
     *
     * @return void|Package
     */
    public function searchLocally($packageName, array $options = array())
    {
        $cleaned = str_replace(' ', '', $packageName);
        $c = $this->modx->newQuery('transport.modTransportPackage');
        $c->where(array(
            "LCASE(package_name) = LCASE('{$packageName}') OR LCASE(package_name) = LCASE('{$cleaned}')"
        ));
        $c->sortby('installed', 'DESC');

        if (!$this->modx->getCount('transport.modTransportPackage', $c) > 0) {
            // No result found
            return;
        }

        $results = $this->modx->getCollection('transport.modTransportPackage', $c);
        /** @var \modTransportPackage $package */
        foreach ($results as $package) {
            if (!isset($options['package_version']) || $options['package_version'] === '*') {
                // No specific version given, let's return the first result (latest installed package)
                return $this->createPackage($package);
            }

            $version = $package->getComparableVersion();

            $this->modx->log(modX::LOG_LEVEL_INFO, "Testing version {$version} against {$options['package_version']}");

            // First check for perfect match
            if (Checker::satisfies($version, $options['package_version'])) {
                $this->modx->log(modX::LOG_LEVEL_INFO, "{$version} is a match!");
                // Installed package matches the requirements
                return $this->createPackage($package);
            }
        }

        // @TODO: No perfect match found, handle mutliple cases
//        foreach ($results as $package) {
//            $version = $package->getComparableVersion();
//            // We got some versions installed, let's check if the required version is the latest installed
//
//            // If a lower version is installed, we should try to upgrade from provider
//
//            // If a higher version is installed, we should consider it as a "match" but warn/inform the user
//        }
    }

    /**
     * Search for the given package on provider(s). If a provider URL is given within the option, the search will occur only against that provider
     *
     * @param string $packageName
     * @param array $options
     *
     * @return Package|void
     */
    public function searchProviders($packageName, array $options = array())
    {
        if (isset($options['provider'])) {
            $this->modx->log(modX::LOG_LEVEL_INFO, 'A provider has been provided, let\'s search it only');
            // A provider has been given, let's just search that provider
            if (!isset($this->providers[$options['provider']])) {
                // We don't know the provider (not in the DB yet), let's create it
                $provider = $this->createProvider($options);
                if ($provider) {
                    $this->providers[$options['provider']] = $provider;
                } else {
                    // We were unable to create/verify the provider
                    return;
                }
            }

            return $this->providers[$options['provider']]->search($packageName, $options);
        } else {
            $this->modx->log(modX::LOG_LEVEL_INFO, 'No particular provider given, iterating over all existing ones');
            // No particular provider given, let's iterate/search against all available providers
            foreach ($this->providers as $provider) {
                $found = $provider->search($packageName, $options);
                if ($found) {
                    return $found;
                }
            }
        }

        return;
    }

    /**
     * Convenient method to instantiate all registered providers
     *
     * @return void
     */
    protected function loadAvailableProviders()
    {
        $collection = $this->modx->getCollection('transport.modTransportProvider');
        /** @var \modTransportProvider $data */
        foreach ($collection as $data) {
            $this->modx->log(modX::LOG_LEVEL_INFO, __METHOD__ . ' instantiating provider '. $data->get('service_url'));
            $provider = new Provider($this->modx);
            if ($provider->fromProvider($data)) {
                // Provider has been verified, let's "cache" it
                // @todo: handle same URL with different users/keys
                $this->providers[$data->get('service_url')] = $provider;
            }
        }
    }

    /**
     * Convenient method to create a Package object from a modTransportPackage
     *
     * @param \modTransportPackage $package
     *
     * @return Package
     */
    protected function createPackage(\modTransportPackage $package)
    {
        $p = new Package($this->modx);
        $p->fromPackage($package);

        return $p;
    }

    /**
     * Convenient method to create a Provider object from an array
     *
     * @param array $options
     *
     * @return Provider|void
     */
    protected function createProvider(array $options)
    {
        $provider = new Provider($this->modx);
        $params = array();
        if (isset($options['provider_data'])) {
            $params = $options['provider_data'];
        }
        if (!isset($params['name'])) {
            // No name found, let's use the URL
            $params['name'] = $options['provider'];
        }

        if ($provider->fromArray(
            array_merge(
                array('service_url' => $options['provider']),
                $params
            )
        )) {

            return $provider;
        }
    }
}
