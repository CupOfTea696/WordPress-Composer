<?php namespace CupOfTea\WordPress\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use Composer\Plugin\PluginInterface;

class WordPressInstallationCleaner extends Singleton
{
    /**
     * Clean up the WordPress installation directory.
     * 
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
