<?php namespace meltingmedia\package;
/**
 * Service class to help install packages from your MODX component setup
 */
class Installer
{
    public $modx;
    public $config = array();
    public $providers = array();

    public function __construct(\modX &$modx, array $options = array())
    {
        $this->modx =& $modx;
        $this->config = array_merge(array(
            'debug' => false,
        ), $options);
    }

    /**
     *
     * @param string $packageName The package name you want to install
     * @param array $options An optional array of options to be used during package installation
     * @param string $providerURL The HTTP URL of the provider to install from (defaults to MODX one)
     *
     * @return bool Whether or not the installation went well
     */
    public function installPackage($providerURL, $packageName, array $options = array(), $providerURL = 'http://rest.modx.com/extras/')
    {
        $this->modx->log(\modX::LOG_LEVEL_INFO, 'Trying to install package '. $packageName .' from '. $providerURL);

        // Instantiate the provider if needed
        if (!array_key_exists($providerURL, $this->providers)) {
            $this->initProvider($providerURL);
        }

        /** @var $provider \modTransportProvider */
        $provider =& $this->providers[$providerURL];
        $provider->getClient();

        /** @var \modRestResponse $response */
        $response = $provider->request('package', 'GET', array(
            'query' => $packageName
        ));
        if ($response->isError()) {
            $this->modx->log(\modX::LOG_LEVEL_ERROR, 'Bad response from the provider, let\'s break everything!!');
            return false;
        }

        if (!empty($response)) {
            $packages = simplexml_load_string($response->response);
            $this->modx->log(\modX::LOG_LEVEL_INFO, count($packages) . ' package(s) found on the Provider');

            foreach($packages as $package) {
                $signature = (string) $package->signature;
                if ($package->name == $packageName) {
                    // Check if the package is already installed
                    $exists = $this->modx->getObject('transport.modTransportPackage', array(
                        'signature' => $signature,
                        'installed:!=' => null,
                    ));
                    if ($exists) {
                        $this->modx->log(\modX::LOG_LEVEL_INFO, 'Package '. $exists->get('signature') .' already installed, skipping it');
                        continue;
                    }

                    // Download file
                    $this->modx->log(\modX::LOG_LEVEL_INFO, 'Downloading '. $package->signature);
                    file_put_contents(
                        $this->modx->getOption('core_path') .'packages/'. $package->signature.'.transport.zip',
                        file_get_contents($package->location)
                    );

                    /** @var \modTransportPackage $tmpPackage */
                    $tmpPackage = $this->modx->newObject('transport.modTransportPackage');
                    $tmpPackage->set('signature', $package->signature);
                    $tmpPackage->fromArray(array(
                        'created' => date('Y-m-d h:i:s'),
                        'updated' => null,
                        'state' => 1,
                        'workspace' => 1,
                        'provider' => $provider->get('id'),
                        'source' => $package->signature.'.transport.zip',
                        'package_name' => $package->name,
                        'version_major' => $package->version_major,
                        'version_minor' => $package->version_minor,
                        'version_patch' => $package->version_patch,
                        'release' => $package->vrelease,
                        'release_index' => $package->vrelease_index,
                    ));

                    $success = $tmpPackage->save();
                    if ($success) {
                        $installed = $tmpPackage->install($options);
                        if ($installed) {
                            $this->modx->log(\modX::LOG_LEVEL_INFO, 'Installation successful');
                        } else {
                            $this->modx->log(\modX::LOG_LEVEL_INFO, 'Something went wrong while trying to install the package');
                        }
                    } else {
                        $this->modx->log(\modX::LOG_LEVEL_ERROR, 'Could not save package '. $package->name);
                    }

                    break;
                }
            }
        }

        return false;
    }

    /**
     * Initialize the given Package Provider
     *
     * @param string $url The provider URL
     *
     * @return bool Either if the initialization succeed of failed
     */
    public function initProvider($url)
    {
        $this->modx->addPackage('modx.transport', $this->modx->getOption('core_path') . 'model/');
        if ($this->config['debug']) {
            $this->modx->log(\modX::LOG_LEVEL_INFO, 'Looking for provider '. $url);
        }
        if (!$this->providers[$url] || !$this->providers[$url] instanceof \modTransportProvider) {
            if ($this->config['debug']) $this->modx->log(\modX::LOG_LEVEL_INFO, 'Instantiating provider '. $url);
            /** @var \modTransportProvider $provider */
            $provider = $this->modx->getObject('transport.modTransportProvider', array('name' => $url));
            if ($provider) {
                if ($this->config['debug']) {
                    $this->modx->log(\modX::LOG_LEVEL_INFO, 'Provider '. $url . ' instantiated');
                }
                $this->providers[$url] =& $provider;

                return true;
            }

            return false;
        }

        return true;
    }
}
