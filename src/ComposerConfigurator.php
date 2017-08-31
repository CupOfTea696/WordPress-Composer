<?php

namespace CupOfTea\WordPress\Composer;

use Composer\Composer;
use Composer\Json\JsonFile;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class ComposerConfigurator
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
     * The composer.json file.
     *
     * @var string
     */
    protected $jsonFile;
    
    /**
     * The composer.json contents.
     *
     * @var array
     */
    protected $json;
    
    /**
     * Sort order for composer.json properties.
     *
     * @var array
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
     * Sort order for the composer.json autoload & autoload-dev properties.
     *
     * @var array
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
     * @param  \Composer\Composer  $composer
     * @param  \Composer\IO\IOInterface  $io
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
        $this->jsonFile = null;
        $this->io = $io;
        
        $publicDirectorySet = $this->isPublicDirectorySet();
        $wordPressInstallDirectorySet = $this->isWordPressInstallDirectorySet();
        $reposConfigured = $this->areReposConfigured();
        $sortingConfigured = $this->isSortingConfigured();
        
        if ($publicDirectorySet && $wordPressInstallDirectorySet && $reposConfigured && $sortingConfigured) {
            return;
        }
        
        if (! $publicDirectorySet) {
            $this->setPublicDirectory();
        }
        
        if (! $wordPressInstallDirectorySet) {
            $this->setWordPressInstallDirectory();
        }
        
        if (! $reposConfigured) {
            $this->configureRepos();
        }
        
        if (! $sortingConfigured) {
            $this->configureSorting();
        }
        
        $this->sortProperties();
        $this->saveJson();
    }
    
    /**
     * Check if the public directory is set in the composer.json file.
     *
     * @return bool
     */
    protected function isPublicDirectorySet()
    {
        if (! $this->json) {
            $this->readJson();
        }
        
        return ! empty($this->json['extra']['public-dir']);
    }
    
    /**
     * Set the public directory in the composer.json.
     *
     * @return void
     */
    protected function setPublicDirectory()
    {
        if (! $this->json) {
            $this->readJson();
        }
        
        $this->json['extra']['public-dir'] = $this->plugin->getPublicDirectory();
    }
    
    /**
     * Check if the WordPress installation directory is set in the composer.json file.
     *
     * @return bool
     */
    protected function isWordPressInstallDirectorySet()
    {
        if (! $this->json) {
            $this->readJson();
        }
        
        return ! empty($this->json['extra']['wordpress-install-dir']);
    }
    
    /**
     * Get the WordPress installation directory in the composer.json.
     *
     * @return string
     */
    public function getWordPressInstallDirectory()
    {
        if (! $this->json) {
            $this->readJson();
        }
        
        return $this->json['extra']['wordpress-install-dir'];
    }
    
    /**
     * Set the WordPress installation directory in the composer.json.
     *
     * @return void
     */
    protected function setWordPressInstallDirectory()
    {
        if (! $this->json) {
            $this->readJson();
        }
        
        $this->json['extra']['wordpress-install-dir'] = $this->plugin->getPublicDirectory() . '/wp';
    }
    
    /**
     * Check if the additional repositories for using WordPress with composer are set.
     *
     * @return bool
     */
    protected function areReposConfigured()
    {
        if (! $this->json) {
            $this->readJson();
        }
        
        return ! empty($this->json['repositories']) && $this->pregGrepRecursive('/^http(s|\?)?:\/\/wpackagist\.org\/?$/', $this->json['repositories']);
    }
    
    /**
     * Configure additional repositories for using WordPress with composer,
     * and set the installation directories for WordPress packages.
     *
     * @return void
     */
    protected function configureRepos()
    {
        if (! $this->json) {
            $this->readJson();
        }
        
        $public = $this->plugin->getPublicDirectory();
        $plugins_path = $public . '/plugins/{$name}/';
        $themes_path = $public . '/themes/{$name}/';
        
        $this->json['repositories'][] = [
            'type' => 'composer',
            'url' => 'https://wpackagist.org',
        ];
        
        $extra = $this->json['extra'];
        
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
        
        $this->json['extra'] = $extra;
    }
    
    /**
     * Check if autmatic sorting of linked packages is enabled.
     *
     * @return bool
     */
    protected function isSortingConfigured()
    {
        if (! $this->json) {
            $this->readJson();
        }
        
        return ! empty($this->json['config']) && ! empty($this->json['config']['sort-packages']);
    }
    
    /**
     * Configure automatic sorting of linked packages.
     *
     * @return void
     */
    protected function configureSorting()
    {
        if (! $this->json) {
            $this->readJson();
        }
        
        $this->json['config']['sort-packages'] = true;
    }
    
    /**
     * Sort the composer.json properties.
     *
     * @return void
     */
    protected function sortProperties()
    {
        if (! $this->json) {
            $this->readJson();
        }
        
        $this->json = $this->sortByArray($this->json, $this->composerOrder);
        
        if (isset($this->json['autoload'])) {
            $this->json['autoload'] = $this->sortByArray($this->json['autoload'], $this->autoloadOrder);
        }
        
        if (isset($this->json['autoload-dev'])) {
            $this->json['autoload-dev'] = $this->sortByArray($this->json['autoload-dev'], $this->autoloadOrder);
        }
        
        foreach (['support', 'require', 'require-dev', 'conflict', 'replace', 'provide', 'suggest'] as $property) {
            if (isset($this->json[$property])) {
                ksort($this->json[$property]);
            }
        }
    }
    
    /**
     * Get the JsonFile.
     *
     * @return \Composer\Json\JsonFile
     */
    protected function getJsonFile()
    {
        if (! $this->jsonFile) {
            $this->jsonFile = new JsonFile('composer.json', null, $this->io);
        }
        
        return $this->jsonFile;
    }
    
    /**
     * Read the composer.json file.
     *
     * @return void
     */
    public function readJson()
    {
        $this->json = $this->getJsonFile()->read();
    }
    
    /**
     * Save the composer.json file.
     *
     * @return void
     */
    protected function saveJson()
    {
        $this->getJsonFile()->write($this->json);
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
