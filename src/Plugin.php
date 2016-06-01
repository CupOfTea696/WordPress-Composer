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
     * @var The composer.json file
     */
    protected $composerJson;
    
    /**
     * @var Sort order for composer.json properties
     */
    protected $composerOrder = [
        'name',
        'type',
        'description',
        'keywords',
        'version',
        'license',
        'homepage',
        'time',
        'authors',
        'repositories',
        'minimum-stability',
        'prefer-stable',
        'support',
        'require',
        'require-dev',
        'conflict',
        'replace',
        'provide',
        'suggest',
        'autoload',
        'autoload-dev',
        'bin',
        'archive',
        'non-feature-branches',
        'config',
        'extra',
        'scripts',
    ];
    
    /**
     * @var Sort order for the composer.json autoload & autoload-dev properties
     */
    protected $autoloadOrder = [
        'psr-4',
        'psr-0',
        'classmap',
        'exclude-from-classmap',
        'files',
    ];
    
    /**
     * @var Public directory name.
     */
    protected $publicDirectory;
    
    /**
     * {@inheritDoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $installer = new Installer($io, $composer, $this);
        
        $composer->getInstallationManager()->addInstaller($installer);
        
        $this->composer = $composer;
        $this->io = $io;
    }
    
    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'pre-install-cmd' => 'configureComposerJson',
            'pre-update-cmd' => 'configureComposerJson'
        ];
    }
    
    /**
     * Configure the composer.json file.
     * 
     * @return void
     */
    public function configureComposerJson()
    {
        if (! $this->isPublicDirectorySet()) {
            $json = $this->readJson();
            
            $this->setExtra($json);
            $this->configureRepos($json);
            $this->configureSorting($json);
            $this->sortProperties($json);
            
            $this->saveJson($json);
        }
    }
    
    /**
     * Check if the public directory is set in the composer.json file.
     * 
     * @return bool
     */
    protected function isPublicDirectorySet()
    {
        $rootPkg = $this->composer->getPackage();
        $extra = $rootPkg->getExtra();
        
        return ! (! $rootPkg || ! $extra || ! $extra['public-dir']);
    }
    
    /**
     * Read the composer.json file.
     * 
     * @return array
     */
    protected function readJson()
    {
        $this->composerJson = new JsonFile('composer.json', null, $this->io);
        
        return $this->composerJson->read();
    }
    
    /**
     * Save the composer.json file.
     * 
     * @param array $json
     */
    protected function saveJson($json)
    {
        $this->composerJson->write($json);
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
        
        if (! $public) {
            if ($this->io->isInteractive()) {
                return $this->publicDirectory = trim($this->io->ask('What is the public directory (web root) for this project [<comment>' . $common_public_dirs[0] . '</comment>]? ', $common_public_dirs[0]), '/');
            }
            
            return $this->publicDirectory = $common_public_dirs[0];
        }
        
        if ($this->io->isInteractive()) {
            return $this->publicDirectory = trim($this->io->ask('What is the public directory (web root) for this project [<comment>' . $public . '</comment>]? ', $public), '/');
        }
        
        return $this->publicDirectory = $public;
    }
    
    /**
     * Set the 'extra' properties in the composer.json.
     * 
     * @param array &$json
     * @param string $public
     * @return void
     */
    protected function setExtra(&$json)
    {
        $public = $thi->getPublicDirectory();
        $extra = array_merge(isset($json['extra']) ? $json['extra'] : [], [
            'public-dir' => $public,
            'wordpress-install-dir' => $public . '/wp',
        ]);
        
        $plugins_path = $public . '/wp/wp-content/plugins/{$name}/';
        $themes_path = $public . '/themes/{$name}/';
        
        if (isset($extra['installer-paths'])) {
            foreach ($extra['installer-paths'] as $path => &$names) {
                if (
                    $path != $plugins_path && ($key = array_search('type:wordpress-plugin', $names)) !== false ||
                    $path != $themes_path && ($key = array_search('type:wordpress-theme', $names)) !== false
                ) {
                    unset($names[$key]);
                    
                    if (! count($names)) {
                        unset($extra['installer-paths'][$path]);
                    }
                }
            }
        }
        
        $extra['installer-paths'][$plugins_path][] = 'type:wordpress-plugin';
        $extra['installer-paths'][$themes_path][] = 'type:wordpress-theme';
        
        $json['extra'] = $extra;
    }
    
    /**
     * Configure additional repos for using WordPress with composer.
     * 
     * @param array &$json
     * @return void
     */
    protected function configureRepos(&$json)
    {
        $repositories = isset($json['repositories']) ? $json['repositories'] : [];
        
        if (! $this->preg_grep_recursive('/^http(s|\?)?:\/\/wpackagist\.org\/?$/', $repositories)) {
            $repositories[] = [
                'type' => 'composer',
                'url' => 'https://wpackagist.org',
            ];
            
            $json['repositories'] = $repositories;
        }
    }
    
    /**
     * Configure automatic sorting of required packages.
     * 
     * @param array &$json
     * @return void
     */
    protected function configureSorting(&$json)
    {
        $config = isset($json['config']) ? $json['config'] : [];
        $config['sort-packages'] = true;
        
        $json['config'] = $config;
    }
    
    /**
     * Sort the composer.json properties.
     * 
     * @param array &$json
     * @return void
     */
    protected function sortProperties(&$json)
    {
        $json = $this->sort_by_array($json, $this->composerOrder);
        
        if (isset($json['autoload'])) {
            $json['autoload'] = $this->sort_by_array($json['autoload'], $this->autoloadOrder);
        }
        
        if (isset($json['autoload-dev'])) {
            $json['autoload-dev'] = $this->sort_by_array($json['autoload-dev'], $this->autoloadOrder);
        }
        
        foreach (['support', 'require', 'require-dev', 'conflict', 'replace', 'provide', 'suggest'] as $property) {
            if (isset($json[$property])) {
                ksort($json[$property]);
            }
        }
    }
    
    /**
     * Recursive version of preg_grep.
     * 
     * @param  string  $pattern
     * @param  array  $haystack
     * @return array
     */
    protected function preg_grep_recursive($pattern, $haystack) {
        $matches = [];
        
        foreach ($haystack as $key => $item) {
            if (is_array($item)) {
				$sub_matches = $this->preg_grep_recursive($pattern, $item);
				
				if ($sub_matches) {
                	$matches[$key] = $sub_matches;
				}
            } elseif (preg_match($pattern, $item)) {
                $matches[$key] = $item;
            }
        }
        
        return $matches;
    }
    
    /**
     * Sort an array by its keys, using a given array as the sort order.
     * 
     * @param  array  $array
     * @param  array  $order
     * @return array
     */
    protected function sort_by_array($array, $order)
    {
        $keys = array_keys($array);
        
        return array_merge(
            array_flip(
                array_intersect($order, $keys) +
				array_diff($keys, $order)
            ),
            $array
        );
    }
}
