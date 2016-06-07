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
     * Plugins excluded from interactions. 
     * 
     * @var array
     */
    protected $exclude = [
        'activate' => [
            'wordfence',
        ],
        'deactivate' => [],
        'uninstall' => [],
    ];
    
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
        $this->interact($composer, $io, __FUNCTION__, $pluginName);
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
        $this->interact($composer, $io, __FUNCTION__, $pluginName);
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
        $this->interact($composer, $io, __FUNCTION__, $pluginName);
    }
    
    /**
     * Execute a plugin interaction.
     * 
     * @param  \Composer\Composer  $composer
     * @param  \Composer\IO\IOInterface  $io
     * @param  string  $action
     * @param  string  $pluginName
     * @return void
     */
    protected function interact(Composer $composer, IOInterface $io, $action, $pluginName)
    {
        $this->composer = $composer;
        $this->io = $io;
        
        $plugin = $this->getPlugin($pluginName);
        
        if (! $plugin) {
            return $this->failed($action, $pluginName);
        }
        
        if ($this->isPluginExcluded($action, $pluginName)) {
            return $this->excluded($action, $pluginName);
        }
        
        $this->plugin->getInstanceOf(WordPressLoader::class)->load(function() use ($plugin, $pluginName, $action) {
            $this->executing($action, $pluginName);
            
            if (! $this->execAction($action, $plugin)) {
                $this->failed($action, $pluginName);
            }
        });
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
    
    /**
     * Check if plugin is excluded from action.
     * 
     * @param  string  $action
     * @param  string  $plugin
     * @return bool
     */
    protected function isPluginExcluded($action, $plugin)
    {
        return in_array($plugin, $this->exclude[$action]);
    }
    
    /**
     * Execute an action and return success.
     * 
     * @param  string  $action
     * @param  string  $plugin
     * @return bool
     */
    protected function execAction($action, $plugin)
    {
        if ($action == 'activate') {
            wp_cache_set('plugins', [], 'plugins');
            
            return activate_plugin($plugin) === null;
        }
        
        if ($action == 'deactivate') {
            return deactivate_plugins($plugin) === null;
        }
        
        if ($action == 'uninstall') {
            if (defined('WP_UNINSTALL_PLUGIN')) {
                if (! function_exists('runkit_constant_remove')) {
                    return false;
                }
                
                runkit_constant_remove('WP_UNINSTALL_PLUGIN');
            }
            
            $r = uninstall_plugin($plugin);
            var_dump($r);
            
            return $r === true;
        }
    }
    
    /**
     * Get Present Continuous action.
     * 
     * @param  string  $action
     * @return string
     */
    protected function getActionExecuting($action)
    {
        switch ($action) {
            case 'activate':
            case 'deactivate':
                $action = rtrim($action, 'e') . 'ing';
                break;
            case 'uninstall':
                $action .= 'ing';
                break;
        }
        
        return $action;
    }
    
    /**
     * Get Present Perfect action.
     * 
     * @param  string  $action
     * @return string
     */
    protected function getActionPast($action)
    {
        switch ($action) {
            case 'activate':
            case 'deactivate':
                $action .= 'd';
                break;
            case 'uninstall':
                $action .= 'ed';
                break;
        }
        
        return $action;
    }
    
    /**
     * Write action executing.
     * 
     * @param  string  $action
     * @param  string  $plugin
     * @return void
     */
    protected function executing($action, $plugin)
    {
        $this->io->write('  - ' . ucfirst($this->getActionExecuting($action)) . ' plugin <info>' . $plugin . '</info>');
        $this->io->write('');
    }
    
    /**
     * Write failed action warning.
     * 
     * @param  string  $action
     * @param  string  $plugin
     * @return void
     */
    protected function failed($action, $plugin)
    {
        $this->io->write('<warning>The plugin ' . $plugin . ' could not be ' . $this->getActionPast($action) . '.</warning>');
    }
    
    /**
     * Write failed action warning.
     * 
     * @param  string  $action
     * @param  string  $plugin
     * @return void
     */
    protected function excluded($action, $plugin)
    {
        $this->io->write('<warning>The plugin ' . $plugin . ' was not ' . $this->getActionPast($action) . ' because it is known to cause issues.</warning>');
        $this->io->write('<info>You can still activate this plugin manually.</info>');
        $this->io->write('');
    }
}
