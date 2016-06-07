<?php namespace CupOfTea\WordPress\Composer;

use PDO;
use PDOException;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class PluginInteractor
{
    /**
     * The Plugin instance.
     * 
     * @var \Composer\Plugin\PluginInterface
     */
    protected $plugin;
    
    /**
     * The composer instance.
     * 
     * @var \Composer\Composer
     */
    protected $composer;
    
    /**
     * The IOInterface instance.
     * 
     * @var \Composer\IO\IOInterface
     */
    protected $io;
    
    /**
     * Create a new PluginInteractor instance.
     * 
     * @param  \Composer\Plugin\PluginInterface  $plugin
     * @return void
     */
    public function __construct(PluginInterface $plugin)
    {
        $this->plugin = $plugin;
    }
    
    /**
     * Activate a plugin.
     * 
     * @param  \Composer\Composer  $composer
     * @param  \Composer\IO\IOInterface  $io
     * @param  string  $plugin
     * @return void
     */
    public function activate(Composer $composer, IOInterface $io, $pluginName)
    {
        $this->init($composer, $io);
        
        $plugin = $this->getPlugin($pluginName);
        $errorMsg = '<warning>The plugin ' . $pluginName . ' could not be activated.</warning>';
        
        if (! $plugin) {
            return $io->write($errorMsg);
        }
        
        $this->plugin->getInstanceOf(WordPressLoader::class)->load(function() use ($plugin, $pluginName, $errorMsg) {
            $this->io->write('  - Activating plugin <info>' . $pluginName . '</info>');
            $this->io->write('');
            $r = activate_plugin($plugin);
            
            if ($r !== null) {
                $this->io->write($errorMsg);
            }
        });
    }
    
    /**
     * Deactivate a plugin.
     * 
     * @param  \Composer\Composer  $composer
     * @param  \Composer\IO\IOInterface  $io
     * @param  string  $plugin
     * @return void
     */
    public function deactivate(Composer $composer, IOInterface $io, $pluginName)
    {
        $this->init($composer, $io);
        
        $plugin = $this->getPlugin($pluginName);
        $errorMsg = '<warning>The plugin ' . $pluginName . ' could not be deactivated.</warning>';
        
        if (! $plugin) {
            return $io->write($errorMsg);
        }
        
        $this->plugin->getInstanceOf(WordPressLoader::class)->load(function() use ($plugin, $pluginName, $errorMsg) {
            $this->io->write('  - Deactivating plugin <info>' . $pluginName . '</info>');
            $r = deactivate_plugins($plugin);
            
            if ($r !== null) {
                $this->io->write($errorMsg);
            }
        });
    }
    
    /**
     * Uninstall a plugin.
     * 
     * @param  \Composer\Composer  $composer
     * @param  \Composer\IO\IOInterface  $io
     * @param  string  $plugin
     * @return void
     */
    public function uninstall(Composer $composer, IOInterface $io, $pluginName)
    {
        $this->init($composer, $io);
        
        $plugin = $this->getPlugin($pluginName);
        $errorMsg = '<warning>The plugin ' . $pluginName . ' could not be uninstalled.</warning>';
        
        if (! $plugin) {
            return $io->write($errorMsg);
        }
        
        $this->plugin->getInstanceOf(WordPressLoader::class)->load(function() use ($plugin, $pluginName, $errorMsg) {
            $this->io->write('  - Uninstalling plugin <info>' . $pluginName . '</info>');
            $r = uninstall_plugin($plugin);
            
            if ($r !== true) {
                $this->io->write($errorMsg);
            }
        });
    }
    
    /**
     * Initialise the PluginInteractor.
     * 
     * @param  \Composer\Composer  $composer
     * @param  \Composer\IO\IOInterface  $io
     * @return void
     */
    protected function init(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }
    
    /**
     * Get the main plugin file for a plugin.
     * 
     * @param  string  $plugin
     * @return string|false
     */
    protected function getPlugin($plugin)
    {
        $path = $this->plugin->getPublicDirectory() . '/plugins/' . $plugin;
        
        if (file_exists($path) && is_dir($path)) {
            $files = scandir($path);
            
            foreach ($files as $file) {
                $pattern = defined('HHVM_VERSION') ? '/\.(php|hh)$/' : '/\.php$/';
                
                if (preg_match($pattern, $file)) {
                    $content = file_get_contents($path . '/' . $file);
                    
                    if (preg_match('/\/\*(?!.*\*\/.*Plugin Name).*Plugin Name/si', $content)) {
                        return $plugin . '/' . $file;
                    }
                }
            }
        }
        
        if (file_exists($path . '.php')) {
            return $plugin . '.php';
        }
        
        if (defined('HHVM_VERSION') && file_exists($path . '.hh')) {
            return $plugin . '.hh';
        }
        
        return false;
    }
}
