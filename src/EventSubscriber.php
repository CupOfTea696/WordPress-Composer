<?php namespace CupOfTea\WordPress\Composer;

use Composer\Script\ScriptEvents;
use Composer\EventDispatcher\Event;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\EventDispatcher\EventSubscriberInterface;

class EventSubscriber extends Singleton implements EventSubscriberInterface
{
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
    
    public function configureComposerJson(Event $event)
    {
        ComposerConfigurator::getInstance()->configure($event->getComposer(), $event->getIO());
    }
    
    public function setWordPressInstallDirectory(Event $event)
    {
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
    
    public function cleanWordPressInstallation(PackageEvent $event)
    {
        if ($event->getOperation()->getPackage()->getName() != 'johnpbloch/wordpress') {
            return;
        }
        
        WordPressInstallationCleaner::getInstance()->clean($event->getComposer(), $event->getIO());
    }
}
