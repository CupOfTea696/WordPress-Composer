<?php namespace CupOfTea\WordPress\Composer;

use PDO;
use PDOException;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class WordPressLoader
{
    /**
     * The Plugin instance.
     * 
     * @var \Composer\Plugin\PluginInterface
     */
    protected $plugin;
    
    /**
     * WordPress load state.
     * 
     * @var bool
     */
    protected $wpLoaded = false;
    
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
     * Load WordPress.
     * 
     * @param  callable  $callback
     * @return mixed
     */
    public function load(callable $callback = null)
    {
        if ($this->wpLoaded) {
            return $callback();
        }
        
        $this->configureDB();
        $this->loadWordPress();
        $this->wpLoaded = true;
        
        return $callback();
    }
    
    /**
     * Configure the DB Hostname.
     * 
     * @return void
     */
    protected function configureDB()
    {
        if (defined('DB_HOST')) {
            return;
        }
        
        $host = env('DB_HOST', 'localhost');
        $name = env('DB_NAME', 'homestead');
        $user = env('DB_USER', 'homestead');
        $pass = env('DB_PASS', 'secret');
        
        try {
            $db = new PDO('mysql:host=' . $host . ';dbname=' . $name, $user, $pass);
        } catch (PDOException $e) {
            if ($host == 'localhost') {
                $host = '127.0.0.1';
            }
            
            $db = new PDO('mysql:host=' . $host . ';port=33060;dbname=' . $name, $user, $pass);
            
            $host = $host . ':33060';
        } catch (PDOException $e) {
            return;
        }
        
        define('DB_HOST', $host);
    }
    
    /**
     * Set the ABSPATH constant and include WordPress.
     * 
     * @return void
     */
    protected function loadWordPress()
    {
        if (! defined('ABSPATH')) {
            define('ABSPATH', $this->plugin->getPublicDirectory() . '/wp/');
        }
        
        include_once dirname(__FILE__) . '/wordpress.php';
    }
}
