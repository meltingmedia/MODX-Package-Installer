<?php namespace meltingmedia\package;

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

    public function addMessage($msg)
    {
        $this->installer->addMessage($msg);
    }

    public function getService($class)
    {
        return $this->installer->getService($class);
    }
}
