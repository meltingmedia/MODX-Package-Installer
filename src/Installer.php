<?php namespace Melting\MODX\Package;

use modX;

/**
 * An utility class to find & install MODX Revolution packages
 */
class Installer
{
    /**
     * @var modX
     */
    public $modx;
    /**
     * @var Finder
     */
    protected $finder;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;

        $this->getFinder();
    }

    /**
     * Install a single package
     *
     * @param string $name - A package name
     * @param array $options
     *
     * @return bool
     */
    public function installPackage($name, array $options = array())
    {
        $installed = false;
        $package = $this->searchPackage($name, $options);
        if ($package) {
            $this->modx->log(modX::LOG_LEVEL_INFO, "Trying to install package {$name}");
            $installed = $package->install($options);
        }

        if (!$installed) {
            $this->modx->log(modX::LOG_LEVEL_INFO, "Package {$name} not installed");
        } else {
            $this->modx->log(modX::LOG_LEVEL_INFO, "Package {$name} installed");
        }

        return $installed;
    }

    /**
     * Convenient method to install multiple packages at once
     *
     * @param array $packages - An array of package name => version
     * @param array $options - An optional array to define additional package options, like the source provider, setup options...
     *
     * @return array
     */
    public function installPackages(array $packages, array $options = array())
    {
        $results = array();
        foreach ($packages as $package => $version) {
            $pOptions = array();
            if (isset($options[$package])) {
                $pOptions = $options[$package];
            }
            $pOptions['package_version'] = $version;
            $results[$package] = $this->installPackage($package, $pOptions);
        }

        return $results;
    }

    /**
     * @param string $name
     * @param array $options
     *
     * @return Package|void
     */
    public function searchPackage($name, array $options = array())
    {
        $this->modx->log(modX::LOG_LEVEL_INFO, "Searching for package {$name} with options ". print_r($options, true));
//        $options = array(
//            'provider' => 'https://domain.tld',
//            'provider_data' => array(
//                'username' => '',
//                'api_key' => '',
//                'revolution_version' => 'revolution-x.y.z-pl',
//                'database' => 'mysql||sqlsrv',
//                'http_host' => '',
//                'php_version' => '',
//                'language' => '',
//            ),
//
//            'package_version' => '<2.0.0-pl',
//            'setup_options' => array(),
//        );

        $finder = $this->getFinder();

        return $finder->search($name, $options);
    }

    /**
     * @return Finder
     */
    protected function getFinder()
    {
        if (!$this->finder) {
            $this->finder = new Finder($this->modx);
        }

        return $this->finder;
    }
}
