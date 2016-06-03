<?php namespace CupOfTea\WordPress\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use Composer\Plugin\PluginInterface;

class WordPressInstallationCleaner
{
    protected $plugin;
    
    /**
     * Create a new WordPressInstallationCleaner instance.
     * 
     * @param  \Composer\Plugin\PluginInterface  $plugin
     * @return void
     */
    public function __construct(PluginInterface $plugin)
    {
        $this->plugin = $plugin;
    }
    
    /**
     * Clean up the WordPress installation directory.
     * 
     * @return void
     */
    public function clean(Composer $composer)
    {
        $rootPkg = $composer->getPackage();
        $extra = $rootPkg->getExtra();
        
        if (! $extra['wordpress-install-dir']) {
            return;
        }
        
        $filesystem = new Filesystem();
        $filesystem->remove($extra['wordpress-install-dir'] . '/wp-content');
    }
}