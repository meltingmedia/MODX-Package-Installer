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

    /** @var array */
    public $currentDependency = array();

    public $messages = array();

    public function __construct(\modX &$modx, array $options = array())
    {
        $this->modx =& $modx;
        $this->config = array_merge(array(
            'debug' => false,
            'local_path' => null,
            'log_target' => 'ECHO',
        ), $options);

        $this->modx->addPackage('modx.transport', $this->modx->getOption('core_path') . 'model/');
    }

    /**
     * @param string $class
     *
     * @return Service
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
        /** @var Validator $validatorService */
        $validatorService = $this->getService('Validator');

        foreach ($dependencies as $package => $version) {
            $this->setCurrentDependency($package, $version, $options);

            // Validate "system" dependencies if any
            // @todo: check for PHP extensions
            if (in_array($package, array('modx', 'php'))) {
                if (!$validatorService->checkSystem()) {
                    $msg = 'System requirement : '. $package .' version '. $version .' not satisfied';
                    $this->modx->log(\modX::LOG_LEVEL_ERROR, $msg, $this->config['log_target']);
                    $this->addMessage($msg);

                    return $this->getMessages();
                }
                continue;
            }

            // Search for already present components
            if ($validatorService->haveLocalPackage()) {
                if ($validatorService->isLocalMatch()) {
                    $msg = $package .' version '. $version .' already found in the system';
                    $this->modx->log(\modX::LOG_LEVEL_INFO, $msg, $this->config['log_target']);
                    $this->addMessage($msg);
                    continue;
                }
            }

            // Search within the provider
            if (!$packageService->searchAndInstall()) {
                // No result found or install failure, let's stop
                return $this->getMessages();
            }
        }

        return $this->getMessages();
    }

    /**
     * @param string $name
     * @param string $version
     * @param array $options
     */
    private function setCurrentDependency($name, $version, array $options = array())
    {
        $packageOptions = array(
            'package_name' => $name,
            'package_version' => $version,
            'options' => array(),
            //'provider' => '',
        );
        if (array_key_exists($name, $options)) {
            $packageOptions = array_merge(
                $packageOptions,
                $options[$name]
            );
        }

        $this->currentDependency = $packageOptions;
    }

    /**
     * @param string $key
     *
     * @return null|mixed
     */
    public function getDependency($key)
    {
        $value = null;
        if (array_key_exists($key, $this->currentDependency)) {
            $value = $this->currentDependency[$key];
        }

        return $value;
    }

    /**
     * @param string $msg
     */
    public function addMessage($msg)
    {
        $this->messages[] = $msg;
    }

    /**
     * Get the result of the operations
     *
     * @return string
     */
    public function getMessages()
    {
        return implode("\n", $this->messages);
    }

    /**
     * Wrapper method to test a given version for a given criteria
     *
     * @param string $toTest The version to test
     * @param string $required The required version criteria
     *
     * @return bool
     */
    public static function satisfies($toTest, $required)
    {
        return \meltingmedia\package\Validator::satisfies($toTest, $required);
    }

}
