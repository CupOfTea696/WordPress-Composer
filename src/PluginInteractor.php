<?php

namespace CupOfTea\WordPress\Composer;

use Composer\Composer;
use ReflectionFunction;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Symfony\Component\Process\PhpProcess;

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
        $plugin = $this->prepare(__FUNCTION__, $composer, $io, $pluginName);
        
        if ($plugin) {
            $result = $this->wp(function () use ($plugin) {
                wp_cache_set('plugins', [], 'plugins');
                
                return activate_plugin($plugin);
            });
            
            $this->succeeded(__FUNCTION__, $pluginName, $result === null);
        }
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
        $plugin = $this->prepare(__FUNCTION__, $composer, $io, $pluginName);
        
        if ($plugin) {
            $result = $this->wp(function () use ($plugin) {
                return deactivate_plugins($plugin);
            });
            
            $this->succeeded(__FUNCTION__, $pluginName, $result === null);
        }
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
        $plugin = $this->prepare(__FUNCTION__, $composer, $io, $pluginName);
        
        if ($plugin) {
            $result = $this->wp(function () use ($plugin) {
                return uninstall_plugin($plugin);
            });
            
            $this->succeeded(__FUNCTION__, $pluginName, $result === true || $result === null);
        }
    }
    
    /**
     * Prepare plugin interaction.
     *
     * @param  string  $action
     * @param  \Composer\Composer  $composer
     * @param  \Composer\IO\IOInterface  $io
     * @param  string  $pluginName
     * @return string|false
     */
    protected function prepare($action, Composer $composer, IOInterface $io, $pluginName)
    {
        $this->composer = $composer;
        $this->io = $io;
        
        if ($this->isPluginExcluded($action, $pluginName)) {
            return $this->excluded($action, $pluginName) && false;
        }
        
        $plugin = $this->getPlugin($pluginName);
        
        if (! $plugin) {
            return $this->failed($action, $pluginName) && false;
        }
        
        $this->executing($action, $pluginName);
        
        return $plugin;
    }
    
    /**
     * Show an action failed message if $success is falsy.
     *
     * @param  string  $action
     * @param  string  $pluginName
     * @param  bool  $success
     * @return void
     */
    protected function succeeded($action, $pluginName, $success)
    {
        if (! $success) {
            $this->failed($action, $pluginName);
        }
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
        $this->io->write('');
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
     * Run a closure in the WordPress environment.
     *
     * @param  closure  $cmd
     * @return mixed
     */
    protected function wp(closure $cmd)
    {
        $cmd = new ReflectionFunction($cmd);
        $code = implode(array_slice(file($cmd->getFileName()), ($startLine = $cmd->getStartLine() - 1), $cmd->getEndLine() - $startLine));
        
        preg_match('/\\{(.*)\\}/s', $code, $body);
        
        $vars = $cmd->getStaticVariables();
        $cmd = trim(preg_replace_callback('/return(?:;|\s((?:[^;(]*(?:\(.*\))?)+);)/s', function ($matches) {
            if (! empty($matches[1])) {
                return "return print 'OUTPUT>>>' . serialize({$matches[1]});";
            }
            
            return "return print 'OUTPUT>>>' . serialize(null);";
        }, $body[1]));
        
        $config = [
            '__host' => env('DB_HOST', 'localhost'),
            '__name' => env('DB_NAME', 'homestead'),
            '__user' => env('DB_USER', 'homestead'),
            '__pass' => env('DB_PASS', 'secret'),
            '__abspath' => $this->plugin->getPublicDirectory() . '/wp/',
            '__wp' => dirname(__FILE__) . '/wordpress.php',
        ];
        
        $config = serialize($config);
        $vars = serialize($vars);
        
        $p = new PhpProcess("<?php
            extract(unserialize('$config'));
            
            try {
                \$db = new PDO('mysql:host=' . \$__host . ';dbname=' . \$__name, \$__user, \$__pass);
            } catch (PDOException \$e) {
                if (\$__host == 'localhost') {
                    \$__host = '127.0.0.1';
                }
                
                \$db = new PDO('mysql:host=' . \$__host . ';port=33060;dbname=' . \$__name, \$__user, \$__pass);
                
                \$__host = \$__host . ':33060';
            } catch (PDOException \$e) {
                return;
            }
            
            define('DB_HOST', \$__host);
            define('ABSPATH', \$__abspath);
            
            \$_SERVER = [
                'HTTP_HOST' => 'http://mysite.com',
                'SERVER_NAME' => 'http://mysite.com',
                'REQUEST_URI' => '/',
                'REQUEST_METHOD' => 'GET'
            ];
            
            //require the WP bootstrap
            require_once ABSPATH . '/wp-admin/includes/plugin.php';
            require_once ABSPATH . '/wp-load.php';
            
            extract(unserialize('$vars'));
            $cmd
            ");
        
        $p->run();
        
        if (preg_match('/OUTPUT>>>(.*)$/s', $p->getOutput(), $matches)) {
            return unserialize($matches[1]);
        }
    }
}
