<?php

namespace Debug;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\ModuleManager\ModuleManager;
use Zend\EventManager\Event;
use Zend\Mvc\MvcEvent;
use Zend\View\Model\ViewModel;
use Zend\ModuleManager\ModuleEvent;

class Module implements AutoloaderProviderInterface
{
    // I do not know how to do pass the duration of time so that I can pass it
    // to the sidebar

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\ClassMapAutoloader' => array(
                __DIR__ . '/autoload_classmap.php',
            ),
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
            // if we're in a namespace deeper than one level we need to fix the \ in the path
                    __NAMESPACE__ => __DIR__ . '/src/' . str_replace('\\', '/' , __NAMESPACE__),
                ),
            ),
        );
    }

     public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }


    public function init(ModuleManager $moduleManager)
    {
        $eventManager = $moduleManager->getEventManager();
        $eventManager->attach(ModuleEvent::EVENT_LOAD_MODULES_POST,
                              array($this, 'loadedModulesInfo'));
    }

    public function loadedModulesInfo(Event $event)
    {
        $moduleManager = $event->getTarget();
        $loadedModules = $moduleManager->getLoadedModules();
        error_log(var_export($loadedModules, true));
    }

    public function onBootstrap(MvcEvent $e)
    {
        $debug_stop = "";
        $eventManager = $e->getApplication()->getEventManager();

        $eventManager->attach(MvcEvent::EVENT_DISPATCH_ERROR, array($this, 'handleError'));

        // Access the ServiceManager
        $serviceManager = $e->getApplication()->getServiceManager();
        // Here we start the timer
        $timer = $serviceManager->get('timer');
        $timer->start('mvc-execution');

        // attach listener to the finish event that has to be executed with priority 2
        // The priority here is 2 because listeners with the priority will be executed just before the
        // actual finish event is triggered.
        $eventManager->attach(MvcEvent::EVENT_FINISH, array($this, 'getMvcDuration'), 2);

        // Chapter 3 addition - let's display some sort of debug layout with the main layout.
        $eventManager->attach(MvcEvent::EVENT_RENDER, array($this, 'addDebugOverlay'), 100);
    }


    public function handleError(MvcEvent $e)
    {
        $controller = $e->getController();
        $error      = $e->getParam('error');
        $exception  = $e->getParam('exception');
        $message    = sprintf('Error dispatching controller "%s". Error was "%s"', $controller, $error);
        if ($exception instanceof \Exception) {
            $exception->getTraceAsString();
        }

        error_log($message);
    }

    public function getMvcDuration(MvcEvent $event)
    {
        // Here we get the ServiceManager.
        $serviceManager = $event->getApplication()->getServiceManager();
        // Get the already created instance of our timer service.
        $timer = $serviceManager->get('timer');
        $this->duration = $timer->stop('mvc-execution');
        // finally print the duration
        error_log('MVC Duration - BLAH: ' . $this->duration . ' seconds');
    }

    public function addDebugOverlay(MvcEvent $event)
    {
        // Get the duration.
        $serviceManager = $event->getApplication()->getServiceManager();
        $timer = $serviceManager->get('timer');
        $duration = $timer->getTime('mvc-execution');

        $debug = "";
        $viewModel = $event->getViewModel();
        $sidebarView = new ViewModel();
        $sidebarView->setTemplate('debug/layout/sidebar');

        $sidebarView->huh = $duration;

        $sidebarView->addChild($viewModel, 'content');

        $event->setViewModel($sidebarView);
    }
}