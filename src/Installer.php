<?php namespace CupOfTea\WordPress\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use InvalidArgumentException;
use Composer\Util\Filesystem;
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
     * @var \Composer\Plugin\PluginInterface
     */
    protected $plugin;
    
    /**
     * @var array
     */
    protected $templates = [];
    
    /**
     * {@inheritDoc}
     */
    public function __construct(IOInterface $io, Composer $composer, PluginInterface $plugin, $type = 'library', Filesystem $filesystem = null, BinaryInstaller $binaryInstaller = null)
    {
        parent::__construct($io, $composer, $type, $filesystem, $binaryInstaller);
        
        $this->plugin = $plugin;
    }
    
    /**
     * {@inheritDoc}
     */
    public function supports($packageType)
    {
        return $packageType === self::PACKAGE_TYPE;
    }
    
    /**
     * {@inheritDoc}
     */
    public function isInstalled(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        if ($repo->hasPackage($package)) {
            $files = $this->getInstallFiles($package);
            
            return count($files) && is_readable($this->getInstallPath($package) . '/' . $files[0]);
        }
        
        return false;
    }
    
    /**
     * {@inheritDoc}
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $this->installCode($package);
        
        if (! $repo->hasPackage($package)) {
            $repo->addPackage(clone $package);
        }
    }
    
    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        if (! $repo->hasPackage($package)) {
            throw new InvalidArgumentException('Package is not installed: '.$package);
        }
        
        $this->removeCode($package);
        $repo->removePackage($package);
    }
    
    /**
     * {@inheritDoc}
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
        
        $groups['user rules'] = [];
        
        foreach ($gitignore as $rule) {
            if (preg_match('/^#\s*(.*)/', $rule, $matches)) {
                $group = strtolower($matches[1]);
                
                if (! isset($groups[$group])) {
                    $groups[$group] = [];
                }
            } elseif (! preg_match('/^\s*$/', $rule) && ! in_array($rule, $groups[$group])) {
                $groups[$group][] = $rule;
            }
        }
        
        $gitignore = [];
        
        foreach ($groups as $group => $rules) {
            if (! count($rules) || $group == 'user rules') {
                continue;
            }
            
            sort($rules);
            
            $gitignore[] = '# ' . ucfirst($group);
            $gitignore = array_merge($gitignore, $rules);
            $gitignore[] = '';
        }
        
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
            'NONCE_SALT'
        ];
        
        $env = ['APP_PUBLIC' => $this->plugin->getPublicDirectory()];
        
        foreach ($saltKeys as $salt) {
            $env[$salt] = $this->generateSalt();
        }
        
        $this->compileTemplate($templatePath, $dotEnvPath, $env);
        
        $envExample = ['APP_PUBLIC' => 'public'];
        
        foreach ($saltKeys as $salt) {
            $envExample[$salt] = 'YOUR_' . $salt . '_GOES_HERE';
        }
        
        $this->compileTemplate($templatePath, $dotEnvExamplePath, $envExample);
    }
    
    protected function configureComposer()
    {
        $composerConfigurator = new ComposerConfigurator($this->plugin);
        $composerConfigurator->configure($this->composer, $this->io);
    }
    
    /**
     * {@inheritDoc}
     */
    protected function installCode(PackageInterface $package)
    {
        $files = $this->getInstallFiles($package);
        $installPath = $this->getInstallPath($package);
        $downloadPath = $this->getTempPath($package);
        $publicPath = $this->plugin->getPublicDirectory();
        
        $this->downloadManager->download($package, $downloadPath);
        
        foreach ($files as $file) {
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
        
        $this->installGitignore($package);
        $this->installDotEnv($package);
        $this->configureComposer();
        
        $this->filesystem->remove($downloadPath);
    }
    
    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
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
     * Generate a Salt Key
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
        
        return $str;
    }
}
