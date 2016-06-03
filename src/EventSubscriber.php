<?php namespace CupOfTea\WordPress\Composer;

use Composer\Script\ScriptEvents;
use Composer\EventDispatcher\Event;
use Composer\Installer\PackageEvent;
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
            PackageEvents::POST_PACKAGE_INSTALL => 'cleanWordPressInstallation',
            PackageEvents::POST_PACKAGE_UPDATE => 'cleanWordPressInstallation',
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
        var_dump('Configuring Composer');
        
        if (! isset($this->instances[ComposerConfigurator::class])) {
            $this->instances[ComposerConfigurator::class] = new ComposerConfigurator(static::$plugin);
        }
        
        $this->instances[ComposerConfigurator::class]->configure($event->getComposer(), $event->getIO());
    }
    
    public function cleanWordPressInstallation(PackageEvent $event)
    {
        var_dump('Cleaning WordPress');
        
        if ($event->getOperation()->getPackage()->getName() != 'johnpbloch/wordpress') {
            return;
        }
        
        if (! isset($this->instances[WordPressInstallationCleaner::class])) {
            $this->instances[WordPressInstallationCleaner::class] = new WordPressInstallationCleaner(static::$plugin);
        }
        
        $this->instances[WordPressInstallationCleaner::class]->clean($event->getComposer(), $event->getIO());
    }
}
