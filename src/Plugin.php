<?php

namespace CupOfTea\WordPress\Composer;

use Composer\Config;
use Composer\Composer;
use Composer\IO\IOInterface;
use CupOfTea\Package\Package;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    use Package;
    
    /**
     * Package Vendor.
     *
     * @const string
     */
    const VENDOR = 'CupOfTea';
    
    /**
     * Package Name.
     *
     * @const string
     */
    const PACKAGE = 'WordPress-Composer';
    
    /**
     * Package Version.
     *
     * @const string
     */
    const VERSION = '1.0.5';
    
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
     * The Dotenv instance.
     *
     * @var \Dotenv\Dotenv
     */
    protected $env;
    
    /**
     * Plugin class instances.
     *
     * @var array
     */
    protected $instances = [];
    
    /**
     * Public directory name.
     *
     * @var string
     */
    protected $publicDirectory;
    
    /**
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $installer = new Installer($io, $composer, $this);
        
        $composer->getInstallationManager()->addInstaller($installer);
        
        $this->composer = $composer;
        $this->io = $io;
        
        include_once dirname(__FILE__) . '/helpers.php';
        
        if (file_exists($this->getVendorDirectory() . '/autoload.php')) {
            include_once $this->getVendorDirectory() . '/autoload.php';
            
            if (class_exists('\Dotenv\Dotenv')) {
                $this->env = new \Dotenv\Dotenv($this->getRootDirectory());
                $this->env->overload();
            }
        }
        
        EventSubscriber::setPlugin($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        $eventMap = [];
        $events = EventSubscriber::getSubscribedEvents();
        
        foreach ($events as $event => $params) {
            if (is_string($params)) {
                $eventMap[$event] = 'forwardEventTo' . ucfirst($params);
            } elseif (is_array($params)) {
                if (count($params) == 2 && is_int($params[1])) {
                    $eventMap[$event] = ['forwardEventTo' . ucfirst($params[0]), $params[1]];
                } else {
                    foreach ($params as $listener) {
                        if (is_string($listener) || (is_array($listener) && count($listener) == 1)) {
                            $listener = is_array($listener) ? $listener[0] : $listener;
                            
                            $eventMap[$event][] = ['forwardEventTo' . ucfirst($listener), 0];
                        } else {
                            $eventMap[$event][] = ['forwardEventTo' . ucfirst($listener[0]), $listener[1]];
                        }
                    }
                }
            }
        }
        
        return $eventMap;
    }
    
    /**
     * Dynamically forward events.
     *
     * @param  string $method
     * @param  array $args
     * @return void
     */
    public function __call($method, $args)
    {
        if (preg_match('/^forwardEventTo([A-Z][A-z]+)$/', $method, $matches)) {
            $listener = lcfirst($matches[1]);
            
            EventSubscriber::getInstance()->$listener($args[0]);
        }
    }
    
    /**
     * Get an instance of a class.
     *
     * @param  string  $class
     * @return object
     */
    public function getInstanceOf($class)
    {
        if (! isset($this->instances[$class])) {
            $this->instances[$class] = new $class($this);
        }
        
        return $this->instances[$class];
    }
    
    /**
     * Get the root directory.
     *
     * @return string
     */
    public function getRootDirectory()
    {
        $config = $this->composer->getConfig();
        
        return str_replace($config->get('vendor-dir', Config::RELATIVE_PATHS), '', $config->get('vendor-dir'));
    }
    
    /**
     * Get the vendor directory.
     *
     * @return string
     */
    public function getVendorDirectory()
    {
        return $this->composer->getConfig()->get('vendor-dir');
    }
    
    /**
     * Get the public directory.
     *
     * @return string
     */
    public function getPublicDirectory()
    {
        if (isset($this->publicDirectory)) {
            return $this->publicDirectory;
        }
        
        $rootPkg = $this->composer->getPackage();
        
        if ($rootPkg) {
            $extra = $rootPkg->getExtra();
            
            if ($extra && ! empty($extra['public-dir'])) {
                return $this->publicDirectory = $extra['public-dir'];
            }
        }
        
        $common_public_dirs = [
            'public',
            'public_html',
            'htdocs',
            'httpdocs',
            'html',
            'web',
            'www',
        ];
        
        $public = null;
        
        foreach ($common_public_dirs as $dir) {
            if (file_exists($dir)) {
                $public = $dir;
                
                break;
            }
        }
        
        $publicDirQuestion = 'What is the public directory (web root) for this project?';
        
        if (! $public) {
            if ($this->io->isInteractive()) {
                return $this->publicDirectory = trim($this->ask($publicDirQuestion, $common_public_dirs[0]), '/');
            }
            
            return $this->publicDirectory = $common_public_dirs[0];
        }
        
        if ($this->io->isInteractive()) {
            return $this->publicDirectory = trim($this->ask($publicDirQuestion, $public), '/');
        }
        
        return $this->publicDirectory = $public;
    }
    
    /**
     * Add default value to IOInterface::ask question.
     *
     * @param  string  $question
     * @param  mixed  $default = null
     * @return mixed
     */
    protected function ask($question, $default = null)
    {
        $defaultComment = '';
        
        if ($default !== null) {
            $defaultComment = ' [<comment>' . $default . '</comment>]';
        }
        
        $question = preg_replace('/(?:(\?)\s*$|(\?)?\s+$)|(?<!\s|\?)$/', $defaultComment . '$1 ', $question);
        
        return $this->io->ask($question, $default);
    }
}
