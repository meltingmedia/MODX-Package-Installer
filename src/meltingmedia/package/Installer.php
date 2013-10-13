<?php namespace meltingmedia\package;
/**
 * Service class to help install packages from your MODX component setup
 */
class Installer
{
    /** @var \modX */
    public $modx;
    /** @var array */
    public $config = array();
    /** @var array */
    public $providers = array();

    public $validOptions = array(
        'version_major','version_major:=','version_major:>=','version_major:>','version_major:<','version_major:<=','version_major:!=',
        'version_minor',
        'version_patch',
        'release',
        'release_index');

    public $present = array();
    public $installed = array();
    public $failure = array();

    public $productVersion;

    public function __construct(\modX &$modx, array $options = array())
    {
        $this->modx =& $modx;
        $this->config = array_merge(array(
            'debug' => false,
            'local_path' => null,
            'log_target' => '',
        ), $options);

        $this->modx->addPackage('modx.transport', $this->modx->getOption('core_path') . 'model/');
    }

    /**
     * Perform whatever it takes with the given dependencies
     *
     * @param array $dependencies
     *
     * @return string The result of the operations
     */
    public function manageDependencies($dependencies = array())
    {
        $this->modx->getVersionData();
        $this->productVersion = $this->modx->version['code_name'].'-'.$this->modx->version['full_version'];

        foreach ($dependencies as $url => $data) {
            foreach ($data as $package => $options) {
                if ($this->isInstalled($package, $options)) {
                    $this->present[$package] = $options;
                    continue;
                }
                $this->installPackage($package, $options, $url);
            }
        }

        return $this->displayResults();
    }

    public function displayResults()
    {
        $msg = '';
        $types = array(
            'failure', 'present', 'installed',
        );
        foreach ($types as $type) {
            if (!empty($this->$type)) {
                foreach ($this->$type as $name => $options) {
                    $msg .= $name . ' '.$type. "\n";
                }
            }
        }

        return $msg;
    }

    /**
     * Check whether or not the given package is already installed
     *
     * @param string $name The package name
     * @param array $options Options to be used to install the package
     *
     * @return bool
     */
    public function isInstalled($name, $options = array())
    {
        $criteria = array_merge(array(
            'package_name' => $name,
            'installed:!=' => null,
        ), $this->filterOptions($options));

        $this->modx->log(\modX::LOG_LEVEL_INFO, print_r($criteria, true));

        /** @var \modTransportPackage $object */
        $object = $this->modx->getObject('modTransportPackage', $criteria);

        return $object instanceof \modTransportPackage;
    }

    /**
     * Filters the options to only use useful data for installation
     *
     * @param array $options The whole options array
     *
     * @return array The filtered data
     */
    protected function filterOptions($options = array())
    {
        $result = array();
        foreach ($options as $k => $v) {
            if (in_array($k, $this->validOptions)) {
                $result[$k] = $v;
            }
        }

        return $result;
    }

    /**
     * Wrapper method to install a transport package
     *
     * @param string $packageName The package name you want to install
     * @param array $options An optional array of options to be used during package installation
     * @param string $providerURL The HTTP URL of the provider to install from (defaults to MODX one)
     *
     * @return bool Whether or not the installation went well
     */
    public function installPackage($packageName, array $options = array(), $providerURL = 'http://rest.modx.com/extras/')
    {
        $this->modx->log(\modX::LOG_LEVEL_INFO, 'Trying to install package '. $packageName .' from '. $providerURL ."\n", $this->config['log_target'], __METHOD__, __FILE__, __LINE__);
        if ('local' === $providerURL) {
            return $this->installLocal($packageName, $options);
        }

        // Instantiate the provider if needed
        if (!array_key_exists($providerURL, $this->providers)) {
            $loaded = $this->initProvider($providerURL);
            if (!$loaded) {
                $msg = 'Error while trying to load the provider ' . $providerURL . '. Skipping its packages';
                $this->modx->log(\modX::LOG_LEVEL_INFO, $msg ."\n", $this->config['log_target'], __METHOD__, __FILE__, __LINE__);
                $options['failure_msg'] = $msg;
                $this->failure[$packageName] = $options;

                return false;
            }
        }

        /** @var $provider \modTransportProvider */
        $provider =& $this->providers[$providerURL];
        $provider->getClient();

        /** @var \modRestResponse $response */
        $response = $provider->request('package', 'GET', array(
            'query' => $packageName,
//            'supports' => $this->productVersion,
//            'revolution_version' => $this->productVersion,
//            'database' => $this->modx->config['dbtype'],
            'php' => XPDO_PHP_VERSION,
        ));
        if ($response->isError()) {
            $this->modx->log(\modX::LOG_LEVEL_ERROR, 'Bad response from the provider, let\'s break everything!!' ."\n", $this->config['log_target'], __METHOD__, __FILE__, __LINE__);
            return false;
        }

        if (!empty($response)) {
            $packages = simplexml_load_string($response->response);
            $this->modx->log(\modX::LOG_LEVEL_INFO, count($packages) . ' package(s) found on the Provider' ."\n", $this->config['log_target'], __METHOD__, __FILE__, __LINE__);

            foreach($packages as $package) {
                $signature = (string) $package->signature;
                if ($package->name == $packageName) {
                    // Download file
                    $this->modx->log(\modX::LOG_LEVEL_INFO, 'Downloading '. $package->signature ."\n", $this->config['log_target'], __METHOD__, __FILE__, __LINE__);
                    file_put_contents(
                        $this->modx->getOption('core_path') .'packages/'. $package->signature.'.transport.zip',
                        file_get_contents($package->location)
                    );

                    $data = array(
                        'signature' => $signature,
                        'provider_id' => $provider->get('id'),
                        'package_name' => $package->name,
                        'version_major' => $package->version_major,
                        'version_minor' => $package->version_minor,
                        'version_patch' => $package->version_patch,
                        'release' => $package->vrelease,
                        'release_index' => $package->vrelease_index,
                    );

                    $tmpPackage = $this->createTransport($data);
                    if ($tmpPackage) {
                        $installed = $tmpPackage->install($options);
                        if ($installed) {
                            $this->modx->log(\modX::LOG_LEVEL_INFO, 'Installation successful' ."\n", $this->config['log_target'], __METHOD__, __FILE__, __LINE__);
                            $this->installed[$packageName] = $options;
                        } else {
                            $msg = 'Something went wrong while trying to install the package';
                            $this->modx->log(\modX::LOG_LEVEL_INFO, $msg ."\n", $this->config['log_target'], __METHOD__, __FILE__, __LINE__);
                            $options['failure_msg'] = $msg;
                            $this->failure[$packageName] = $options;
                        }
                    } else {
                        $msg = 'Could not save package '. $package->name;
                        $this->modx->log(\modX::LOG_LEVEL_ERROR, $msg ."\n", $this->config['log_target'], __METHOD__, __FILE__, __LINE__);
                        $options['failure_msg'] = $msg;
                        $this->failure[$packageName] = $options;
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
        if ($this->config['debug']) {
            $this->modx->log(\modX::LOG_LEVEL_INFO, 'Looking for provider '. $url ."\n", $this->config['log_target'], __METHOD__, __FILE__, __LINE__);
        }
        if ('local' === $url) {
            $this->modx->log(\modX::LOG_LEVEL_INFO, 'looking for local package(s)' ."\n", $this->config['log_target'], __METHOD__, __FILE__, __LINE__);
            return true;
        }
        if (!$this->providers[$url] || !$this->providers[$url] instanceof \modTransportProvider) {
            if ($this->config['debug']) {
                $this->modx->log(\modX::LOG_LEVEL_INFO, 'Instantiating provider '. $url ."\n", $this->config['log_target'], __METHOD__, __FILE__, __LINE__);
            }
            /** @var \modTransportProvider $provider */
            $provider = $this->modx->getObject('transport.modTransportProvider', array('service_url' => $url));
            if ($provider) {
                if ($this->config['debug']) {
                    $this->modx->log(\modX::LOG_LEVEL_INFO, 'Provider '. $url . ' instantiated' ."\n", $this->config['log_target'], __METHOD__, __FILE__, __LINE__);
                }
                $this->providers[$url] =& $provider;

                return true;
            }

            return false;
        }
        $this->modx->log(\modX::LOG_LEVEL_INFO, 'Getting Provider '. $url . ' instance' ."\n", $this->config['log_target'], __METHOD__, __FILE__, __LINE__);

        return true;
    }

    /**
     * Wrapper method to create the modTransportPackage record
     *
     * @param array $data An array of data to be used to create the modTransportPackage object
     *
     * @return bool|\modTransportPackage Whether the modTransportPackage object on success of false on failure
     */
    public function createTransport(array $data = array())
    {
        /** @var \modTransportPackage $package */
        $package = $this->modx->newObject('transport.modTransportPackage');
        $package->set('signature', $data['signature']);
        $package->fromArray(array(
            'created' => date('Y-m-d h:i:s'),
            'updated' => null,
            'state' => 1,
            'workspace' => 1,
            'provider' => array_key_exists('provider_id', $data) ? $data['provider_id'] : 0,
            'source' => $data['signature'].'.transport.zip',
            'package_name' => $data['name'],
            'version_major' => $data['version_major'],
            'version_minor' => $data['version_minor'],
            'version_patch' => $data['version_patch'],
            'release' => $data['release'],
            'release_index' => $data['release_index'],
        ));

        if  ($package->save()) {
            return $package;
        }

        return false;
    }

    /**
     * Install the given package file
     *
     * @param string $path Absolute path to the transport package zip file
     * @param array $options An optional array of options to be used during package installation
     *
     * @return boolean Whether or not the installation went fine
     */
    public function installLocal($path, array $options = array())
    {
        $data = $this->getDataFromFile($path);
        if (empty($data)) {
            return false;
        }

        $package = $this->createTransport($data);
        if ($package) {
            $installed = $package->install($options);
            if ($installed) {
                $this->modx->log(\modX::LOG_LEVEL_INFO, 'Installation successful' ."\n", $this->config['log_target'], __METHOD__, __FILE__, __LINE__);
                $this->installed[$data['package_name']] = $options;

                return true;
            } else {
                $msg = 'Something went wrong while trying to install the package';
                $this->modx->log(\modX::LOG_LEVEL_INFO, $msg ."\n", $this->config['log_target'], __METHOD__, __FILE__, __LINE__);
                $options['failure_msg'] = $msg;
                $this->failure[$data['package_name']] = $options;
            }
        } else {
            $msg = 'Could not save package '. $data['package_name'];
            $this->modx->log(\modX::LOG_LEVEL_ERROR, $msg ."\n", $this->config['log_target'], __METHOD__, __FILE__, __LINE__);
            $options['failure_msg'] = $msg;
            $this->failure[$data['package_name']] = $options;
        }

        return false;
    }

    /**
     * Generate some data from the given transport.zip file
     *
     * @param string $path The absolute data to the zip file
     *
     * @return array The generated data
     */
    public function getDataFromFile($path)
    {
        if (!file_exists($path)) {
            $this->modx->log(\modX::LOG_LEVEL_INFO, 'File not found : '. $path ."\n", $this->config['log_target'], __METHOD__, __FILE__, __LINE__);
            return array();
        }
        $signature = basename($path, '.transport.zip');
        // define version
        $sig = explode('-', $signature);
        $versionSignature = explode('.', $sig[1]);

        $data = array(
            'signature' => $signature,
            'package_name' => $sig[0],
            'version_major' => $versionSignature[0],
            'version_minor' => !empty($versionSignature[1]) ? $versionSignature[1] : 0,
            'version_patch' => !empty($versionSignature[2]) ? $versionSignature[2] : 0,
        );

        if (!empty($sig[2])) {
            $r = preg_split('/([0-9]+)/', $sig[2], -1, PREG_SPLIT_DELIM_CAPTURE);
            if (is_array($r) && !empty($r)) {
                $data['release'] = $r[0];
                $data['release_index'] = (isset($r[1]) ? $r[1] : '0');
            } else {
                $data['release'] = $sig[2];
                $data['release_index'] = '';
            }
        }

        return $data;
    }
}
