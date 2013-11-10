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
    protected $services = array();

    public $currentDependency;

    public $present = array();
    public $installed = array();
    public $failure = array();

    public $messages = array();

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
     * @param string $class
     *
     * @return mixed
     */
    public function getService($class)
    {
        if (!array_key_exists($class, $this->services)) {
            $className = '\meltingmedia\package\\' . $class;
            $this->services[$class] = new $className($this);
        }

        return $this->services[$class];
    }

    /**
     * Perform whatever it takes with the given dependencies
     *
     * @param array $dependencies
     * @param array $options
     *
     * @return string The result of the operations
     */
    public function manageDependencies($dependencies = array(), array $options = array())
    {
        /** @var Package $packageService */
        $packageService = $this->getService('Package');
        foreach ($dependencies as $package => $version) {
            if ($this->haveLocalPackage($package)) {
                if ($this->isLocalMatch($package, $version)) {
                    //$this->present[$package] = $version;
                    $msg = $package .' '. $version .' already found in the system';
                    $this->modx->log(\modX::LOG_LEVEL_INFO, $msg, $this->config['log_target']);
                    $this->addMessage($msg);
                    continue;
                }
            }

            $packageOptions = array();
            if (array_key_exists($package, $options)) {
                $packageOptions = $options[$package];
            }
            $packageOptions['version'] = $version;

            $packageService->installPackage($package, $packageOptions);
        }

        return $this->getResults();
    }

    public function addMessage($msg)
    {
        $this->messages[] = $msg;
    }

    /**
     * Get the result of the operations
     *
     * @return string
     */
    public function getResults()
    {
        return implode("\n", $this->messages);
    }

    /**
     * Check if we have a matching version already in place
     *
     * @param string $package Package name to look after
     * @param string $required Required version criteria
     *
     * @return bool
     */
    public function isLocalMatch($package, $required)
    {
        $c = $this->modx->newQuery('modTransportPackage');
        $c->where(array(
            'package_name' => strtolower($package),
            'OR:package_name:=' => $package,
        ));
        $c->sortby('installed', 'DESC');
        //$c->limit(1);

        $collection = $this->modx->getCollection('modTransportPackage', $c);

        /** @var \modTransportPackage $candidate */
        foreach ($collection as $candidate) {
            $version = $candidate->getComparableVersion();
            if ($this->satisfies($version, $required)) {
                // We got a match
                // @todo check if installed, if not, install it
                return true;
            }
        }

        return false;
    }

    /**
     * Checks whether or not a local package is present
     *
     * @param string $name The package name
     *
     * @return bool
     */
    public function haveLocalPackage($name)
    {
        $c = $this->modx->newQuery('modTransportPackage');
        $c->where(array(
            'package_name' => strtolower($name),
            'OR:package_name:=' => $name,
        ));
        $c->limit(1);

        /** @var \modTransportPackage $object */
        $object = $this->modx->getObject('modTransportPackage', $c);

        return $object instanceof \modTransportPackage;
    }

    /**
     * Wrapper method to test a given version for a given criteria
     *
     * @param string $toTest The version to test
     * @param string $required The required version criteria
     *
     * @return bool
     */
    public function satisfies($toTest, $required)
    {
        return \meltingmedia\package\Validator::satisfies($toTest, $required);
    }

}
