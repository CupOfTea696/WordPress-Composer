<?php

namespace CupOfTea\WordPress\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use InvalidArgumentException;
use Composer\Plugin\PluginInterface;
use Composer\Package\PackageInterface;
use Composer\Installer\BinaryInstaller;
use Composer\Installer\LibraryInstaller;
use Composer\Downloader\FilesystemException;
use Composer\Repository\InstalledRepositoryInterface;

class Installer extends LibraryInstaller
{
    /**
     * Package Type handled by this Installer.
     *
     * @const string
     */
    const PACKAGE_TYPE = 'cupoftea-wordpress';
    
    /**
     * The Plugin instance.
     *
     * @var \Composer\Plugin\PluginInterface
     */
    protected $plugin;
    
    /**
     * Template contents.
     *
     * @var array
     */
    protected $templates = [];
    
    /**
     * {@inheritdoc}
     */
    public function __construct(IOInterface $io, Composer $composer, PluginInterface $plugin, $type = 'library', Filesystem $filesystem = null, BinaryInstaller $binaryInstaller = null)
    {
        parent::__construct($io, $composer, $type, $filesystem, $binaryInstaller);
        
        $this->plugin = $plugin;
    }
    
    /**
     * {@inheritdoc}
     */
    public function supports($packageType)
    {
        return $packageType === self::PACKAGE_TYPE;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isInstalled(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        if ($repo->hasPackage($package)) {
            return is_readable($this->getInstallPath($package) . '/' . $package->getExtra()['main']);
        }
        
        return false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $this->installCode($package);
        
        if (! $repo->hasPackage($package)) {
            $repo->addPackage(clone $package);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        if (! $repo->hasPackage($initial)) {
            throw new InvalidArgumentException('Package is not installed: ' . $initial);
        }
        
        $this->updateCode($initial, $target);
        $repo->removePackage($initial);
        
        if (! $repo->hasPackage($target)) {
            $repo->addPackage(clone $target);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        if (! $repo->hasPackage($package)) {
            throw new InvalidArgumentException('Package is not installed: ' . $package);
        }
        
        $this->removeCode($package);
        $repo->removePackage($package);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getInstallPath(PackageInterface $package)
    {
        if ($package->getPrettyName() !== 'cupoftea/wordpress') {
            throw new InvalidArgumentException('This installer can only be used to install the cupoftea/wordpress package.');
        }
        
        return '.';
    }
    
    /**
     * Get the temp path.
     *
     * @param  \Composer\Package\PackageInterface  $package
     * @return string
     */
    protected function getTempPath(PackageInterface $package)
    {
        return str_replace('/', '-', $package->getPrettyName()) . '_' . $package->getPrettyVersion();
    }
    
    /**
     * Get the list of files to install.
     *
     * @param  \Composer\Package\PackageInterface  $package
     * @return array
     */
    protected function getInstallFiles(PackageInterface $package)
    {
        return $package->getExtra()['files'];
    }
    
    /**
     * Set up the .gitignore file.
     *
     * @param  \Composer\Package\PackageInterface  $package
     * @return void
     */
    protected function installGitignore(PackageInterface $package)
    {
        $installPath = $this->getInstallPath($package) . '/.gitignore';
        $templatePath = $this->getTempPath($package) . '/.gitignore.template';
        
        $data = ['APP_PUBLIC' => $this->plugin->getPublicDirectory()];
        
        if (! file_exists($installPath)) {
            return $this->compileTemplate($templatePath, $installPath, $data);
        }
        
        $downloadGitignore = $this->compileTemplate($templatePath, $data);
        $installGitignore = file_get_contents($installPath);
        
        $gitignore = preg_split('/\r?\n/', $downloadGitignore . PHP_EOL . '# User rules' . PHP_EOL . $installGitignore);
        $group = 'user rules';
        $groups = [];
        $rules = [];
        
        $groups['user rules'] = [];
        
        foreach ($gitignore as $rule) {
            if (preg_match('/^#\s*(.*)/', $rule, $matches)) {
                $group = strtolower($matches[1]);
                
                if (! isset($groups[$group])) {
                    $groups[$group] = [];
                }
            } elseif (! preg_match('/^\s*$/', $rule) && ! in_array($rule, $rules)) {
                $groups[$group][] = $rules[] = $rule;
            }
        }
        
        $gitignore = [];
        
        foreach ($groups as $group => $rules) {
            if (! count($rules) || $group == 'user rules') {
                continue;
            }
            
            $this->sortRules($rules);
            
            $gitignore[] = '# ' . ucfirst($group);
            $gitignore = array_merge($gitignore, $rules);
            $gitignore[] = '';
        }
        
        $this->sortRules($groups['user rules']);
        
        $gitignore[] = '# User rules';
        $gitignore = array_merge($gitignore, $groups['user rules']);
        $gitignore[] = '';
        
        file_put_contents($installPath, implode(PHP_EOL, $gitignore));
    }
    
    /**
     * Set up the .env file.
     *
     * @param  \Composer\Package\PackageInterface  $package
     * @return void
     */
    protected function installDotEnv(PackageInterface $package)
    {
        $templatePath = $this->getTempPath($package) . '/.env.template';
        $dotEnvPath = $this->getInstallPath($package) . '/.env';
        $dotEnvExamplePath = $this->getInstallPath($package) . '/.env.example';
        
        if (! file_exists($templatePath)) {
            return;
        }
        
        $saltKeys = [
            'AUTH_KEY',
            'SECURE_AUTH_KEY',
            'LOGGED_IN_KEY',
            'NONCE_KEY',
            'AUTH_SALT',
            'SECURE_AUTH_SALT',
            'LOGGED_IN_SALT',
            'NONCE_SALT',
        ];
        
        $envExample = ['APP_PUBLIC' => 'public'];
        
        foreach ($saltKeys as $salt) {
            $envExample[$salt] = 'YOUR_' . $salt . '_GOES_HERE';
        }
        
        $this->compileTemplate($templatePath, $dotEnvExamplePath, $envExample);
        
        if (file_exists($dotEnvPath)) {
            return;
        }
        
        $env = ['APP_PUBLIC' => $this->plugin->getPublicDirectory()];
        
        foreach ($saltKeys as $salt) {
            $env[$salt] = $this->generateSalt();
        }
        
        $this->compileTemplate($templatePath, $dotEnvPath, $env);
    }
    
    /**
     * Get the ComposerConfigurator instance.
     *
     * @return \CupOfTea\WordPress\Composer\ComposerConfigurator
     */
    protected function getComposerConfigurator()
    {
        return $this->plugin->getInstanceOf(ComposerConfigurator::class);
    }
    
    /**
     * Configure Composer.
     *
     * @return void
     */
    protected function configureComposer()
    {
        $this->getComposerConfigurator()->configure($this->composer, $this->io);
    }
    
    /**
     * Check if WordPress is installed in the correct directory,
     * and move it there if it is not.
     *
     * @return  void
     */
    protected function checkWordPressInstallation()
    {
        $defaultWpInstallDir = $this->plugin->getRootDirectory() . '/wordpress';
        $wpInstallDir = $this->getComposerConfigurator()->getWordPressInstallDirectory();
        
        if (file_exists($defaultWpInstallDir) && is_dir($defaultWpInstallDir)) {
            if (! is_dir($wpInstallDir)) {
                if (file_exists($wpInstallDir)) {
                    unlink($wpInstallDir);
                }
                
                rename($defaultWpInstallDir, $wpInstallDir);
            } else {
                rmdir($defaultWpInstallDir);
            }
        }
    }
    
    /**
     * Select the preferred en_GB option in the WordPress installation language form.
     *
     * @return void
     */
    protected function selectPreferredLanguageOnWordPressInstall()
    {
        $wpInstallScriptPath = $this->getComposerConfigurator()->getWordPressInstallDirectory() . '/wp-admin/install.php';
        
        if (file_exists($wpInstallScriptPath)) {
            $wpInstallScript = file_get_contents($wpInstallScriptPath);
            $wpInstallScript = preg_replace('/<\\/body>\n<\\/html>/', '<script>' . PHP_EOL
                . "if (jQuery('#language').find('option[value=\"en_GB\"]').length) {" . PHP_EOL
                . "    jQuery('#language').val('en_GB').change();" . PHP_EOL
                . '}' . PHP_EOL
                . '</script>' . PHP_EOL
                . '</body>' . PHP_EOL
                . '</html>', $wpInstallScript);
            
            file_put_contents($wpInstallScriptPath, $wpInstallScript);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    protected function installCode(PackageInterface $package)
    {
        $files = $this->getInstallFiles($package);
        $installPath = $this->getInstallPath($package);
        $downloadPath = $this->getTempPath($package);
        $publicPath = $this->plugin->getPublicDirectory();
        
        $this->downloadManager->download($package, $downloadPath);
        $this->installFiles($installPath, $downloadPath, $publicPath, $files);
        
        $this->installGitignore($package);
        $this->installDotEnv($package);
        $this->configureComposer();
        
        $this->checkWordPressInstallation();
        $this->selectPreferredLanguageOnWordPressInstall();
        
        $this->filesystem->remove($downloadPath);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function updateCode(PackageInterface $current, PackageInterface $target)
    {
        $currentInstallPath = $this->getInstallPath($current);
        $targetInstallPath = $this->getInstallPath($target);
        
        if ($targetInstallPath !== $currentInstallPath) {
            // if the target and initial dirs intersect, we force a remove + install
            // to avoid the rename wiping the target dir as part of the initial dir cleanup
            if (substr($currentInstallPath, 0, strlen($targetInstallPath)) === $targetInstallPath
                || substr($targetInstallPath, 0, strlen($currentInstallPath)) === $currentInstallPath
            ) {
                $this->removeCode($current);
                $this->installCode($target);
                
                return;
            }
            
            $this->filesystem->rename($currentInstallPath, $targetInstallPath);
        }
        
        $currentFiles = $this->getInstallFiles($current);
        $targetFiles = $this->getInstallFiles($target);
        $deleteFiles = array_diff($currentFiles, $targetFiles);
        
        foreach ($deleteFiles as $file) {
            $this->filesystem->remove($targetInstallPath . '/' . $file);
        }
        
        $this->installCode($target);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function removeCode(PackageInterface $package)
    {
        $files = $this->getInstallFiles($package);
        $installPath = $this->getInstallPath($package);
        
        foreach ($files as $file) {
            $this->filesystem->remove($installPath . '/' . $file);
        }
    }
    
    /**
     * Install files.
     *
     * @param  string  $installPath
     * @param  string  $downloadPath
     * @param  string  $publicPath
     * @param  array  $files
     * @return void
     */
    protected function installFiles($installPath, $downloadPath, $publicPath, $files = [])
    {
        foreach ($files as $file => $overwrite) {
            if ($overwrite || (! $overwrite && ! file_exists($installPath . '/' . $file))) {
                if (! file_exists($downloadPath . '/' . $file)) {
                    throw new FilesystemException('The file ' . $file . ' could not be found. Please report to cupoftea/wordpress.');
                }
                
                $installFile = $installPath . '/' . $file;
                
                if ($publicPath != 'public') {
                    $installFile = $installPath . '/' . preg_replace('/^public/', $publicPath, $file);
                }
                
                if (preg_match('/\\/$/', $file)) {
                    $this->filesystem->ensureDirectoryExists($installFile);
                    
                    continue;
                }
                
                $this->filesystem->rename($downloadPath . '/' . $file, $installFile);
            }
        }
    }
    
    /**
     * Compile a template file.
     *
     * @param  string  $templatePath
     * @param  string  $destinationPath
     * @param  array|null  $data
     * @return string
     */
    private function compileTemplate($templatePath, $destinationPath, $data = null)
    {
        if ($data == null) {
            $data = $destinationPath;
            $destinationPath = null;
        }
        
        if (! isset($this->templates[$templatePath])) {
            $this->templates[$templatePath] = file_get_contents($templatePath);
        }
        
        $compiled = preg_replace_callback('/{{\s*([A-z0-9_-]+)\s*}}/', function ($matches) use ($data) {
            if (isset($data[$matches[1]])) {
                return $data[$matches[1]];
            }
            
            return $matches[0];
        }, $this->templates[$templatePath]);
        
        if ($destinationPath) {
            file_put_contents($destinationPath, $compiled);
        }
        
        return $compiled;
    }
    
    /**
     * Sort gitignore rules.
     *
     * @param  array  &$rules
     * @return void
     */
    private function sortRules(&$rules)
    {
        sort($rules);
        usort($rules, function ($a, $b) {
            return strlen($a) - strlen($b);
        });
    }
    
    /**
     * Generate a Salt Key.
     *
     * @return string
     */
    private function generateSalt()
    {
        $str = '';
        $length = 64;
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*(){}[]/|`,.?+-_=:;<> ';
        $count = strlen($chars);
        
        while ($length--) {
            $str .= $chars[mt_rand(0, $count - 1)];
        }
        
        return 'base64:' . base64_encode($str);
    }
}
