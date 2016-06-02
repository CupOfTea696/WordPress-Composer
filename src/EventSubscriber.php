<?php namespace CupOfTea\WordPress\Composer;

use Composer\Script\ScriptEvents;
use Composer\EventDispatcher\Event;
use Composer\Installer\PackageEvents;
use Composer\EventDispatcher\EventSubscriberInterface;

class EventSubscriber implements EventSubscriberInterface
{
    protected static $instance;
    
    protected function __construct()
    {
        //
    }
    
    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::PRE_INSTALL_CMD => 'configureComposerJson',
            ScriptEvents::PRE_UPDATE_CMD => 'configureComposerJson',
//            PackageEvents::POST_PACKAGE_INSTALL => 'handle',
//            PackageEvents::PRE_PACKAGE_UPDATE => 'handle',
//            PackageEvents::POST_PACKAGE_UPDATE => 'handle',
        ];
    }
    
    public static function getInstance()
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        
        return static::$instance;
    }
    
    public function configureComposerJson(Event $event)
    {
        
    }
}
