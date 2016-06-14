<?php namespace CupOfTea\WordPress\Composer;

use Composer\Script\ScriptEvents;
use Composer\EventDispatcher\Event;
use Composer\Installer\PackageEvent;
use Composer\Plugin\PluginInterface;
use Composer\Installer\PackageEvents;
use Composer\Package\PackageInterface;
use Composer\EventDispatcher\EventSubscriberInterface;

class EventSubscriber implements EventSubscriberInterface
{
    /**
     * The EventSubscriber instance.
     * 
     * @var \CupOfTea\WordPress\Composer\EventSubscriber
     */
    protected static $instance;
    
    /**
     * The Plugin instance.
     * 
     * @var \Composer\Plugin\PluginInterface
     */
    protected static $plugin;
    
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
            PackageEvents::POST_PACKAGE_INSTALL => ['cleanWordPressInstallation'], //, 'activateWordPressPlugin'],
            PackageEvents::POST_PACKAGE_UPDATE => 'cleanWordPressInstallation',
            // PackageEvents::PRE_PACKAGE_UNINSTALL => ['deactivateWordPressPlugin', 'uninstallWordPressPlugin'],
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
        static::$plugin->getInstanceOf(ComposerConfigurator::class)->configure($event->getComposer(), $event->getIO());
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
        
        static::$plugin->getInstanceOf(WordPressInstallationCleaner::class)->clean($event->getComposer(), $event->getIO());
    }
    
    /**
     * Activate a WordPress plugin.
     * 
     * @param  \Composer\Installer\PackageEvent  $event
     * @return void
     */
    public function activateWordPressPlugin(PackageEvent $event)
    {
        if (! $this->isWordPressPlugin($this->getPackage($event))) {
            return;
        }
        
        static::$plugin->getInstanceOf(PluginInteractor::class)->activate(
            $event->getComposer(),
            $event->getIO(),
            preg_replace('/^wpackagist-plugin\//', '', $this->getPackageName($event))
        );
    }
    
    /**
     * Deactivate a WordPress plugin.
     * 
     * @param  \Composer\Installer\PackageEvent  $event
     * @return void
     */
    public function deactivateWordPressPlugin(PackageEvent $event)
    {
        if (! $this->isWordPressPlugin($this->getPackage($event))) {
            return;
        }
        
        static::$plugin->getInstanceOf(PluginInteractor::class)->deactivate(
            $event->getComposer(),
            $event->getIO(),
            preg_replace('/^wpackagist-plugin\//', '', $this->getPackageName($event))
        );
    }
    
    /**
     * Uninstall a WordPress plugin.
     * 
     * @param  \Composer\Installer\PackageEvent  $event
     * @return void
     */
    public function uninstallWordPressPlugin(PackageEvent $event)
    {
        if (! $this->isWordPressPlugin($this->getPackage($event))) {
            return;
        }
        
        static::$plugin->getInstanceOf(PluginInteractor::class)->uninstall(
            $event->getComposer(),
            $event->getIO(),
            preg_replace('/^wpackagist-plugin\//', '', $this->getPackageName($event))
        );
    }
    
    /**
     * Get the PackageInterface from a PackageEvent.
     * 
     * @param  \Composer\Installer\PackageEvent  $event
     * @return \Composer\Package\PackageInterface
     */
    protected function getPackage(PackageEvent $event)
    {
        $operation = $event->getOperation();
        
        if (method_exists($operation, 'getPackage')) {
            return $operation->getPackage();
        }
        
        return $operation->getTargetPackage();
    }
    
    /**
     * Get package name from a PackageEvent.
     * 
     * @param  \Composer\Installer\PackageEvent  $event
     * @return string
     */
    protected function getPackageName(PackageEvent $event)
    {
        return $this->getPackage($event)->getName();
    }
    
    /**
     * Check if the package is a WordPress Plugin.
     * 
     * @param  \Composer\Package\PackageInterface  $package
     * @return bool
     */
    protected function isWordPressPlugin(PackageInterface $package)
    {
        return $package->getType() == 'wordpress-plugin';
    }
}
