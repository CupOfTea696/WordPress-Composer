<?php namespace CupOfTea\WordPress\Composer;

use Composer\Script\ScriptEvents;
use Composer\EventDispatcher\Event;
use Composer\Installer\PackageEvent;
use Composer\Plugin\PluginInterface;
use Composer\Installer\PackageEvents;
use Composer\EventDispatcher\EventSubscriberInterface;

class EventSubscriber implements EventSubscriberInterface
{
    /**
     * @var EventSubscriber
     */
    protected static $instance;
    
    /**
     * @var \Composer\Plugin\PluginInterface
     */
    protected static $plugin;
    
    /**
     * @var array
     */
    protected $instances = [];
    
    /**
     * Create a new EventSubscriber instance.
     * 
     * @return void
     */
    protected function __construct()
    {
        //
    }
    
    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::PRE_INSTALL_CMD => 'configureComposerJson',
            ScriptEvents::PRE_UPDATE_CMD => 'configureComposerJson',
            PackageEvents::PRE_PACKAGE_INSTALL => 'setWordPressInstallDirectory',
            PackageEvents::POST_PACKAGE_INSTALL => 'cleanWordPressInstallation',
            PackageEvents::POST_PACKAGE_UPDATE => 'cleanWordPressInstallation',
        ];
    }
    
    /**
     * Get the EventSubscriber instance.
     * 
     * @return EventSubscriber
     */
    public static function getInstance()
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        
        return static::$instance;
    }
    
    /**
     * Set the \Composer\Plugin\PluginInterface instance.
     * 
     * @param  \Composer\Plugin\PluginInterface $plugin
     * @return void
     */
    public static function setPlugin(PluginInterface $plugin)
    {
        static::$plugin = $plugin;
    }
    
    /**
     * Configure the composer sonfiguration file.
     * 
     * @param  \Composer\EventDispatcher\Event $event
     * @return void
     */
    public function configureComposerJson(Event $event)
    {
        if (! isset($this->instances[ComposerConfigurator::class])) {
            $this->instances[ComposerConfigurator::class] = new ComposerConfigurator(static::$plugin);
        }
        
        $this->instances[ComposerConfigurator::class]->configure($event->getComposer(), $event->getIO());
    }
    
    /**
     * Set the WordPress installation directory.
     * 
     * @param  \Composer\EventDispatcher\Event $event
     * @return void
     */
    public function setWordPressInstallDirectory(PackageEvent $event)
    {
        if ($this->getPackageName($event) != 'johnpbloch/wordpress') {
            return;
        }
        
        $composer = $event->getComposer();
        $rootPkg = $composer->getPackage();
        
        if (! $rootPkg) {
            return;
        }
        
        $extra = $rootPkg->getExtra();
        
        if (isset($extra['wordpress-install-dir']) && $extra['wordpress-install-dir']) {
            return;
        }
        
        $extra['wordpress-install-dir'] = static::$plugin->getPublicDirectory() . '/wp';
        
        $rootPkg->setExtra($extra);
        $composer->setPackage($rootPkg);
    }
    
    /**
     * Clean the WordPress installation.
     * 
     * @param  \Composer\Installer\PackageEvent $event
     * @return void
     */
    public function cleanWordPressInstallation(PackageEvent $event)
    {
        if ($this->getPackageName($event) != 'johnpbloch/wordpress') {
            return;
        }
        
        if (! isset($this->instances[WordPressInstallationCleaner::class])) {
            $this->instances[WordPressInstallationCleaner::class] = new WordPressInstallationCleaner(static::$plugin);
        }
        
        $this->instances[WordPressInstallationCleaner::class]->clean($event->getComposer(), $event->getIO());
    }
    
    /**
     * Get package name from a PackageEvent
     * @param  \Composer\Installer\PackageEvent $event
     * @return string
     */
    protected function getPackageName(PackageEvent $event)
    {
        $operation = $event->getOperation();
        
        if (method_exists($operation, 'getPackage')) {
            return $operation->getPackage()->getName();
        }
        
        return $operation->getTargetPackage()->getName();
    }
}
