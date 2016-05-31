<?php namespace CupOfTea\WordPress\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    protected $composer;
    
    protected $io;
    
    public function activate(Composer $composer, IOInterface $io)
    {
        $installer = new Installer($io, $composer);
        
        $composer->getInstallationManager()->addInstaller($installer);
        
        $this->composer = $composer;
        $this->io = $io;
    }
    
    public static function getSubscribedEvents()
    {
        return [
            'pre-install-cmd' => 'checkIfWordPressInstallDirSet',
            'pre-update-cmd' => 'checkIfWordPressInstallDirSet'
        ];
    }
    
    protected function checkIfWordPressInstallDirSet()
    {
        $cfg = $this->composer->getConfig();
        
        var_dump($cfg->get('extra'));
        die();
        
        if (! $cfg->get('extra')) {
            
        }
    }
}
