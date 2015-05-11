<?php namespace Melting\MODX\Package;

use modX;

/**
 * A wrapper for modTransportPackage
 */
class Package
{
    /**
     * @var modX
     */
    public $modx;
    /**
     * @var Provider
     */
    protected $provider;
    /**
     * @var \modTransportPackage
     */
    protected $package;
    /**
     * Whether or not this package could be installed
     *
     * @var bool
     */
    protected $installable = true;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Link a provider object to this package
     *
     * @param Provider $provider
     *
     * @return $this
     */
    public function setProvider(Provider $provider)
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * Either retrieve an existing or create a modTransportPackage to link to this package, from the given
     * SimpleXMLElement (most likely retrieved from a provider response)
     *
     * @see Package::fromArray
     *
     * @param \SimpleXMLElement $data
     *
     * @return $this
     */
    public function fromXml(\SimpleXMLElement $data)
    {
        //$this->modx->log(modX::LOG_LEVEL_INFO, 'XML : ' . print_r($data, true));

        $data = array(
            'package_name' => (string) $data->name,
            'signature' => (string) $data->signature,
            'provider' => $this->provider ? $this->provider->get('id') : 0,
            'version_major' => (int) $data->version_major,
            'version_minor' => (int) $data->version_minor,
            'version_patch' => (int) $data->version_patch,
            'release' => (string) $data->vrelease,
            'release_index' => (string) $data->vrelease_index,
            'location' => (string) $data->location,
        );

        return $this->fromArray($data);
    }

    /**
     * Either retrieve an existing or create a modTransportPackage to link to this package, from the given array data
     *
     * @param array $data
     *
     * @return $this
     */
    public function fromArray(array $data)
    {
        // Try to find an existing package
        $this->package = $this->modx->getObject('transport.modTransportPackage', array(
            'signature' => $data['signature']
        ));
        if ($this->package instanceof \modTransportPackage) {
            return $this;
        }

        $data = array_merge(array(
            'created' => date('Y-m-d h:i:s'),
            'updated' => null,
            'state' => 1,
            'workspace' => 1,
            'source' => $data['signature'].'.transport.zip',
        ), $data);

        // Create a new modTransportPackage record
        $this->package = $this->modx->newObject('transport.modTransportPackage');
        $this->package->fromArray($data, '', true);

        return $this;
    }

    /**
     * Link the given modTransportPackage to this package
     *
     * @param \modTransportPackage $package
     *
     * @return $this
     */
    public function fromPackage(\modTransportPackage $package)
    {
        $this->package = $package;

        return $this;
    }

    /**
     * Convenient method to check if this package matches the given requirements
     *
     * @param string $name - The package name
     * @param array $options - Optional options. Important one being $options[package_version]
     *
     * @return bool
     */
    public function matches($name, array $options = array())
    {
        // Some packages name contains white spaces, let's remove them
        $nameCleaned = strtolower(str_replace(' ', '', $name));
        $compare = strtolower(str_replace(' ', '', $this->package->get('package_name')));
        if ($nameCleaned !== $compare) {
            return false;
        }

        // A version requirement/constraint has been given, let's take care of it
        if (isset($options['package_version'])) {
            if ($options['package_version'] === '*') {
                // We do not require a particular version, so this one fits!
                return true;
            }
            $current = $this->package->getComparableVersion();

            $matches = Checker::satisfies($current, $options['package_version']);
            if ($matches) {
                // Version is a match
                return true;
            }
            $this->modx->log(modX::LOG_LEVEL_INFO, 'Package seems to be a match, but not the appropriate version');

            // Seems like we want another version, check if provider supports versions listing
            if (!$this->provider) {
                return false;
            }
            $versions = $this->provider->searchVersions($name);
            foreach ($versions as $xml) {
                $this->fromXml($xml);

                $current = $this->package->getComparableVersion();

                $matches = Checker::satisfies($current, $options['package_version']);
                if ($matches) {
                    return true;
                }
            }
            return false;
        }
        // Perfect name match, and no given version, consider as a match

        return true;
    }

    /**
     * Download the given package from the given URL, and store in within Revolution core/packages directory
     *
     * @param string $signature
     * @param string $url
     *
     * @return bool|int
     */
    protected function download($signature, $url)
    {
        if ($this->isDownloaded($signature)) {
            return true;
        }

        return file_put_contents(
            $this->modx->getOption('core_path') .'packages/'. $signature.'.transport.zip',
            file_get_contents($url)
        );
    }

    /**
     * Check whether or not the package matching the given signature has already been downloaded
     *
     * @param string $signature
     *
     * @return bool
     */
    protected function isDownloaded($signature)
    {
        return file_exists($this->modx->getOption('core_path') .'packages/'. $signature.'.transport.zip');
    }

    /**
     * Convenient method to install this package, if needed
     *
     * @param array $options
     *
     * @return bool
     */
    public function install(array $options = array())
    {
        if ($this->isInstalled()) {
            return true;
        }
        $setup = array();
        if (isset($options['setup_options'])) {
            $setup = $options['setup_options'];
        }

        // First try to download
        if (!$this->isDownloaded($this->package->signature)) {
            $this->modx->log(modX::LOG_LEVEL_INFO, 'Package not yet downloaded...');
            if (empty($this->package->location)) {
                $this->modx->log(modX::LOG_LEVEL_INFO, 'No package URL found');
                return false;
            }
            $downloaded = $this->download($this->package->signature, $this->package->location);
            if ($downloaded) {
                $this->modx->log(modX::LOG_LEVEL_INFO, 'Package downloaded');
                if ($this->package->isNew()) {
                    // Package is new, let's save it
                    $saved = $this->package->save();
                    if (!$saved) {
                        $this->modx->log(modX::LOG_LEVEL_INFO, 'Unable to save modTransportPackage');
                        return false;
                    }
                }
            } else {
                $this->modx->log(modX::LOG_LEVEL_INFO, 'Error while trying to download the package');
                return false;
            }
        } else {
            $this->modx->log(modX::LOG_LEVEL_INFO, 'Package is already downloaded');
        }

        //return false;
        return $this->package->install($setup);
    }

    /**
     * Mark this package as not installable, mostly to prevent installing a lower version than currently installed
     *
     * @return void
     */
    public function setNotInstallable()
    {
        $this->installable = false;
    }

    /**
     * Check whether or not this package is already installed
     *
     * @return bool
     */
    protected function isInstalled()
    {
        if ($this->installable) {
            return !empty($this->package->installed);
        }
        // Package is marked as not "installable", let's consider it as installed to prevent issues

        return true;
    }
}
