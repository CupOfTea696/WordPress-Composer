<?php namespace CupOfTea\WordPress\Composer;

use Composer\Composer;
use Composer\Json\JsonFile;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class ComposerConfigurator
{
    /**
     * @var \Composer\Plugin\PluginInterface
     */
    protected $plugin;
    
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
     * Create a new ComposerConfigurator instance.
     *
     * @param  \Composer\Plugin\PluginInterface  $plugin
     * @return void
     */
    public function __construct(PluginInterface $plugin)
    {
        $this->plugin = $plugin;
    }
    
    /**
     * Configure the composer.json file.
     *
     * @return void
     */
    public function configure(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->composerJson = null;
        
        $publicDirectorySet = $this->isPublicDirectorySet();
        $wordPressInstallDirectorySet = $this->isWordPressInstallDirectorySet();
        $reposConfigured = $this->areReposConfigured();
        $sortingConfigured = $this->isSortingConfigured();
        
        if ($publicDirectorySet && $wordPressInstallDirectorySet && $reposConfigured && $sortingConfigured) {
            return;
        }
        
        $json = $this->readJson();
        
        if (! $publicDirectorySet) {
            $this->setPublicDirectory($json);
        }
        
        if (! $wordPressInstallDirectorySet) {
            $this->setWordPressInstallDirectory($json);
        }
        
        if (! $reposConfigured) {
            $this->configureRepos($json);
        }
        
        if (! $sortingConfigured) {
            $this->configureSorting($json);
        }
        
        $this->sortProperties($json);
        $this->saveJson($json);
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
        
        return $rootPkg && $extra && ! empty($extra['public-dir']);
    }
    
    /**
     * Set the public directory in the composer.json.
     *
     * @param  array  $json
     * @return void
     */
    protected function setPublicDirectory(&$json)
    {
        $json['extra']['public-dir'] = $this->plugin->getPublicDirectory();
    }
    
    /**
     * Check if the WordPress installation directory is set in the composer.json file.
     *
     * @return bool
     */
    protected function isWordPressInstallDirectorySet()
    {
        $rootPkg = $this->composer->getPackage();
        $extra = $rootPkg->getExtra();
        
        return $rootPkg && $extra && ! empty($extra['wordpress-install-dir']);
    }
    
    /**
     * Set the WordPress installation directory in the composer.json.
     *
     * @param  array  $json
     * @return void
     */
    protected function setWordPressInstallDirectory(&$json)
    {
        $json['extra']['wordpress-install-dir'] = $this->plugin->getPublicDirectory() . '/wp';
    }
    
    /**
     * Check if the additional repositories for using WordPress with composer are set.
     *
     * @return bool
     */
    protected function areReposConfigured()
    {
        $rootPkg = $this->composer->getPackage();
        $repositories = $rootPkg->getRepositories();
        
        return $this->pregGrepRecursive('/^http(s|\?)?:\/\/wpackagist\.org\/?$/', $repositories);
    }
    
    /**
     * Configure additional repositories for using WordPress with composer,
     * and set the installation directories for WordPress packages.
     *
     * @param  array  $json
     * @return void
     */
    protected function configureRepos(&$json)
    {
        $public = $this->plugin->getPublicDirectory();
        $plugins_path = $public . '/wp/wp-content/plugins/{$name}/';
        $themes_path = $public . '/themes/{$name}/';
        
        $json['repositories'][] = [
            'type' => 'composer',
            'url' => 'https://wpackagist.org',
        ];
        
        $extra = $json['extra'];
        
        if (isset($extra['installer-paths'])) {
            foreach ($extra['installer-paths'] as $path => &$names) {
                if ($path != $plugins_path && ($key = array_search('type:wordpress-plugin', $names)) !== false ||
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
     * Check if autmatic sorting of linked packages is enabled.
     *
     * @return bool
     */
    protected function isSortingConfigured()
    {
        return $this->composer->getConfig()->get('sort-packages');
    }
    
    /**
     * Configure automatic sorting of linked packages.
     *
     * @param  array  $json
     * @return void
     */
    protected function configureSorting(&$json)
    {
        $json['config']['sort-packages'] = true;
    }
    
    /**
     * Sort the composer.json properties.
     *
     * @param  array  $json
     * @return void
     */
    protected function sortProperties(&$json)
    {
        $json = $this->sortByArray($json, $this->composerOrder);
        
        if (isset($json['autoload'])) {
            $json['autoload'] = $this->sortByArray($json['autoload'], $this->autoloadOrder);
        }
        
        if (isset($json['autoload-dev'])) {
            $json['autoload-dev'] = $this->sortByArray($json['autoload-dev'], $this->autoloadOrder);
        }
        
        foreach (['support', 'require', 'require-dev', 'conflict', 'replace', 'provide', 'suggest'] as $property) {
            if (isset($json[$property])) {
                ksort($json[$property]);
            }
        }
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
     * Recursive version of preg_grep.
     *
     * @param  string  $pattern
     * @param  array  $haystack
     * @return array
     */
    protected function pregGrepRecursive($pattern, $haystack)
    {
        $matches = [];
        
        foreach ($haystack as $key => $item) {
            if (is_array($item)) {
                $sub_matches = $this->pregGrepRecursive($pattern, $item);
                
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
    protected function sortByArray($array, $order)
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
