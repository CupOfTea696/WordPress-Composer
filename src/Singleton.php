<?php namespace CupOfTea\WordPress\Composer;

use Composer\Plugin\PluginInterface;

class Singleton
{
    private static $instance;
    
    private static $plugin;
    
    protected function __construct()
    {
        //
    }
    
    public static function getInstance()
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        
        return static::$instance;
    }
    
    public static function setPlugin(PluginInterface $plugin)
    {
        static::$plugin = $plugin;
    }
}
