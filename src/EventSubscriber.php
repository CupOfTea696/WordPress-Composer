<?php namespace CupOfTea\WordPress\Composer;

use Composer\Script\ScriptEvents;
use Composer\EventDispatcher\Event;
use Composer\Plugin\PluginInterface;
use Composer\Installer\PackageEvents;
use Composer\EventDispatcher\EventSubscriberInterface;

class EventSubscriber implements EventSubscriberInterface
{
    protected static $instance;
    
    protected static $plugin;
    
    protected $instances = [];
    
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
    
    public static function setPlugin(PluginInterface $plugin)
    {
        static::$plugin = $plugin;
    }
    
    public function configureComposerJson(Event $event)
    {
        if (! isset($this->instances[ComposerConfigurator::class])) {
            $this->instances[ComposerConfigurator::class] = new ComposerConfigurator(static::$plugin);
        }
        
        $this->instances[ComposerConfigurator::class]->configure($event->getComposer(), $event->getIO());
    }
}
