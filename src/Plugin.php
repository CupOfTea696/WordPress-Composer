<?php namespace CupOfTea\WordPress\Composer;

use Composer\Composer;
use Composer\Json\JsonFile;
use Composer\IO\IOInterface;
use CupOfTea\Package\Package;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    use Package;
    
    /**
     * Package Name.
     *
     * @const string
     */
    const PACKAGE = 'CupOfTea/WordPress-Composer';
    
    /**
     * Package Version.
     *
     * @const string
     */
    const VERSION = '0.0.0';
    
    /**
     * @var The composer instance
     */
    protected $composer;
    
    /**
     * @var The IO instance
     */
    protected $io;
    
    /**
     * @var Public directory name.
     */
    protected $publicDirectory;
    
    /**
     * {@inheritDoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        var_dump('plugin activated');
        
        $installer = new Installer($io, $composer, $this);
        
        $composer->getInstallationManager()->addInstaller($installer);
        
        $this->composer = $composer;
        $this->io = $io;
        
        EventSubscriber::setPlugin($this);
    }
    
    /**
     * {@inheritDoc}
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
    
    public function __call($method, $args)
    {
        if (preg_match('/^forwardEventTo([A-Z][A-z]+)$/', $method, $matches)) {
            $listener = lcfirst($matches[1]);
            
            EventSubscriber::getInstance()->$listener($args[0]);
        }
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
        $extra = $rootPkg->getExtra();
        
        if ($rootPkg && $extra && $extra['public-dir']) {
            return $this->publicDirectory = $extra['public-dir'];
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
        
        $question = preg_replace('/(\??)\s*$/', '', $question . $defaultComment . '$1 ');
        
        return $this->io->ask($question, $default);
    }
}
