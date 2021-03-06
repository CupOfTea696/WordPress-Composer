<?php

namespace CupOfTea\WordPress\Composer;

use Composer\Composer;
use Composer\Util\Filesystem;
use Composer\Plugin\PluginInterface;

class WordPressInstallationCleaner
{
    /**
     * The Plugin instance.
     *
     * @var \Composer\Plugin\PluginInterface
     */
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
     * @param  \Composer\Composer  $composer
     * @return void
     */
    public function clean(Composer $composer)
    {
        $rootPkg = $composer->getPackage();
        $extra = $rootPkg->getExtra();
        
        if (! isset($extra['wordpress-install-dir'])) {
            return;
        }
        
        $filesystem = new Filesystem();
        $filesystem->remove($extra['wordpress-install-dir'] . '/wp-content');
    }
}
