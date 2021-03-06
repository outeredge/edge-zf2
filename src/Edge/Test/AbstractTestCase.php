<?php

namespace Edge\Test;

use Zend\Mvc\Service\ServiceManagerConfig;
use Zend\ServiceManager\ServiceManager;
use PHPUnit_Framework_TestCase;

abstract class AbstractTestCase extends PHPUnit_Framework_TestCase
{
    /**
     * @var array
     */
    protected static $applicationConfig;

    /**
     * @var \Zend\Mvc\Application
     */
    private $application;

    /**
     * @var ServiceManager
     */
    private $serviceManager;


    public function setUp()
    {
        $this->reset();
    }

    protected function getApplication()
    {
        if (null === $this->application) {
            $this->application = $this->getServiceManager()->get('Application');
            $this->application->bootstrap();
        }
        return $this->application;
    }

    /**
     * @return ServiceManager
     */
    protected function getServiceManager()
    {
        if (null === $this->serviceManager) {
            $this->serviceManager = new ServiceManager();

            $configuration = self::$applicationConfig;

            $smConfig = isset($configuration['service_manager']) ? $configuration['service_manager'] : [];
            $smConfig = new ServiceManagerConfig($smConfig);
            $smConfig->configureServiceManager($this->serviceManager);

            $this->serviceManager->setAllowOverride(true);
            $this->serviceManager->setService('ApplicationConfig', $configuration);
            $this->serviceManager->get('ModuleManager')->loadModules();
        }

        return $this->serviceManager;
    }

    public static function setApplicationConfig($applicationConfig)
    {
        self::$applicationConfig = $applicationConfig;
    }

    /**
     * Reset the request
     *
     * @return AbstractTestCase
     */
    public function reset()
    {
        $_SESSION = array();
        $_GET     = array();
        $_POST    = array();
        $_COOKIE  = array();
        $_FILES   = array();

        return $this;
    }

    public function tearDown()
    {
        unset($this->application);
        unset($this->serviceManager);
    }
}