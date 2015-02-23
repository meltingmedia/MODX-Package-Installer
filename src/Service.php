<?php namespace meltingmedia\modx\package;

abstract class Service
{
    /** @var Installer */
    protected $installer;
    /** @var  \modX */
    protected $modx;
    protected $config = array();


    public function __construct(Installer &$installer)
    {
        $this->installer =& $installer;
        $this->modx =& $installer->modx;
        $this->config = array_merge(array(

        ), $installer->config);
    }

    /**
     * @param string $msg
     */
    public function addMessage($msg)
    {
        $this->installer->addMessage($msg);
    }

    /**
     * @param string $class
     *
     * @return Service
     */
    public function getService($class)
    {
        return $this->installer->getService($class);
    }

    public function getDependency($key)
    {
        return $this->installer->getDependency($key);
    }
}
