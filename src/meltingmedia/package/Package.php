<?php namespace meltingmedia\package;

/**
 * Service class to search packages across providers (and locally) as well as install packages
 */
class Package extends Service
{
    /**
     * Wrapper method to launch a search on a provider and install the package if found
     *
     * @return bool Whether or not the operation went well
     */
    public function searchAndInstall()
    {
        $response = $this->search();
        if ($response && !empty($response)) {
            return $this->iterate($response);
        }

        return false;
    }

    /**
     * Query a provider for the current dependency
     *
     * @return bool|\modRestResponse False on query failure, or the provider response
     */
    public function search()
    {
        $packageName = $this->getDependency('package_name');
        $this->modx->log(\modX::LOG_LEVEL_INFO, 'Searching for package '. $packageName);

        $providerUrl = 'http://rest.modx.com/extras/';
        $specifiedProvider = $this->getDependency('provider');
        if ($specifiedProvider && !empty($specifiedProvider)) {
            $providerUrl = $specifiedProvider;
        }

        // Get the provider
        /** @var Provider $providerService */
        $providerService = $this->getService('Provider');
        /** @var \modTransportProvider $provider */
        $provider = $providerService->get($providerUrl);
        if (!$provider instanceof \modTransportProvider) {
            return false;
        }

        /** @var \modRestResponse $response */
        return $providerService->query($packageName);
    }

    /**
     * Iterate results of the provider response
     * @param \modRestResponse $response
     *
     * @return bool
     */
    public function iterate(\modRestResponse $response)
    {
        $packageName = $this->getDependency('package_name');
        $requiredVersion = $this->getDependency('package_version');
        $options = $this->getDependency('options');

        /** @var Provider $providerService */
        $providerService = $this->getService('Provider');
        $provider = $providerService->getCurrent();

        $packages = simplexml_load_string($response->response);
        $this->modx->log(\modX::LOG_LEVEL_INFO, '//---', $this->config['log_target']);
        $this->modx->log(\modX::LOG_LEVEL_INFO, count($packages) . ' package(s) found on the Provider', $this->config['log_target']);

        foreach ($packages as $package) {
            if (strtolower($package->name) == strtolower($packageName)) {

                // Make sure the result indeed satisfies the requirements
                $signature = (string) $package->signature;
                $current = (string) $package->version;
                if (!$this->installer->satisfies($current, $requiredVersion)) {
                    // Not a correct version, keep iterating
                    continue;
                }

                // Download file
                $this->modx->log(\modX::LOG_LEVEL_INFO, 'Downloading '. $signature, $this->config['log_target']);
                file_put_contents(
                    $this->modx->getOption('core_path') .'packages/'. $signature.'.transport.zip',
                    file_get_contents($package->location)
                );

                // Create the transport package
                $data = array(
                    'signature' => $signature,
                    'provider' => $provider->get('id'),
                    'package_name' => $package->name,
                    'version_major' => $package->version_major,
                    'version_minor' => $package->version_minor,
                    'version_patch' => $package->version_patch,
                    'release' => $package->vrelease,
                    'release_index' => $package->vrelease_index,
                );

                $transport = $this->createTransport($data);
                if ($transport) {
                    // Install it
                    $this->modx->log(\modX::LOG_LEVEL_INFO, 'Installing '. $package->name);
                    return $this->install($transport, $options);
                }

                $msg = 'Could not save package '. $package->name;
                $this->modx->log(\modX::LOG_LEVEL_ERROR, $msg, $this->config['log_target']);
                $this->addMessage($msg);

                return false;
            }
        }

        $msg = $packageName .' not found in the given provider';
        $this->modx->log(\modX::LOG_LEVEL_ERROR, $msg);
        $this->addMessage($msg);

        return false;
    }

    /**
     * Wrapper method to create the modTransportPackage record
     *
     * @param array $data An array of data to be used to create the modTransportPackage object
     *
     * @return bool|\modTransportPackage Whether the modTransportPackage object on success or false on failure
     */
    public function createTransport(array $data = array())
    {
        $data = array_merge(array(
            'created' => date('Y-m-d h:i:s'),
            'updated' => null,
            'state' => 1,
            'workspace' => 1,
            'source' => $data['signature'].'.transport.zip',
        ), $data);

        /** @var \modTransportPackage $package */
        $package = $this->modx->newObject('transport.modTransportPackage');
        $package->set('signature', $data['signature']);
        $package->fromArray($data);
//        $package->fromArray(array(
//            'created' => date('Y-m-d h:i:s'),
//            'updated' => null,
//            'state' => 1,
//            'workspace' => 1,
//            'provider' => array_key_exists('provider_id', $data) ? $data['provider_id'] : 0,
//            'source' => $data['signature'].'.transport.zip',
//            'package_name' => $data['name'],
//            'version_major' => $data['version_major'],
//            'version_minor' => $data['version_minor'],
//            'version_patch' => $data['version_patch'],
//            'release' => $data['release'],
//            'release_index' => $data['release_index'],
//        ));

        if  ($package->save()) {
            return $package;
        }

        return false;
    }

    /**
     * Install the given transport package
     *
     * @param \modTransportPackage $package
     * @param array $options
     *
     * @return bool
     */
    public function install(\modTransportPackage &$package, $options = array())
    {
        if ($package->getTransport()) {
//            $attributes = is_array($package->get('attributes')) ? $package->get('attributes') : array();
//            $depOptions = is_array($package->get('requires_options')) ? $package->get('requires_options') : array();
//
//            if (isset($attributes['requires']) && !empty($attributes['requires'])) {
//                $this->modx->log(\modX::LOG_LEVEL_INFO, 'Managing dependy dependencies (inception!');
//                $this->manageDependencies($attributes['requires'], $depOptions);
//            }

            $installed = $package->install($options);
            if ($installed) {
                $msg = $package->get('package_name') . '  successfully installed';
                $this->modx->log(\modX::LOG_LEVEL_INFO, $msg, $this->config['log_target']);
                $this->modx->log(\modX::LOG_LEVEL_INFO, '//---', $this->config['log_target']);
                $this->modx->log(\modX::LOG_LEVEL_INFO, '&nbsp;', $this->config['log_target']);
                $this->addMessage($msg);

                return true;
            }

            $msg = 'Something went wrong while trying to install the package';
            $this->modx->log(\modX::LOG_LEVEL_ERROR, $msg, $this->config['log_target']);
            $this->addMessage($msg);

            return false;
        }

        $msg = 'Unable to get the transport package';
        $this->modx->log(\modX::LOG_LEVEL_ERROR, $msg, $this->config['log_target']);
        $this->addMessage($msg);

        return false;
    }





    // @TODO
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
            return $this->install($package, $options);
        } else {
            $msg = 'Could not save package '. $data['package_name'];
            $this->modx->log(\modX::LOG_LEVEL_ERROR, $msg, $this->config['log_target']);
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
            $this->modx->log(\modX::LOG_LEVEL_ERROR, 'File not found : '. $path, $this->config['log_target']);
            return array();
        }
        $signature = basename($path, '.transport.zip');
        // Define version
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
