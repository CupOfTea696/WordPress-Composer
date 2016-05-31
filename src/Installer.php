<?php namespace CupOfTea\WordPress\Composer;

use CupOfTea\Package\Package;
use InvalidArgumentException;
use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Downloader\FilesystemException;
use Composer\Repository\InstalledRepositoryInterface;

class Installer extends LibraryInstaller
{
    use Package;
    
    /**
     * Package Name.
     *
     * @const string
     */
    const PACKAGE = 'CupOfTea/WordPress-Installer';
    
    /**
     * Package Version.
     *
     * @const string
     */
    const VERSION = '0.0.0';
    
    const PACKAGE_TYPE = 'cupoftea-wordpress';
    
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
    
    protected function getTempPath(PackageInterface $package)
    {
        return str_replace('/', '-', $package->getPrettyName()) . '_' . $package->getPrettyVersion();
    }
    
    protected function getInstallFiles(PackageInterface $package)
    {
        return $package->getExtra()['files'];
    }
    
    protected function installGitignore(PackageInterface $package)
    {
        $installPath = $this->getInstallPath($package) . '/.gitignore';
        $downloadPath = $this->getTempPath($package) . '/.gitignore';
        
        if (! file_exists($installPath)) {
            return $this->filesystem->rename($downloadPath, $installPath);
        }
        
        $gitignore = preg_split('/\r?\n/', file_get_contents($downloadPath) . PHP_EOL . '# User rules' . PHP_EOL . file_get_contents($installPath));
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
    
    protected function installCode(PackageInterface $package)
    {
        $files = $this->getInstallFiles($package);
        $installPath = $this->getInstallPath($package);
        $downloadPath = $this->getTempPath($package);
        
        $this->downloadManager->download($package, $downloadPath);
        $this->installGitignore($package);
        
        foreach ($files as $file) {
            if (! file_exists($downloadPath . '/' . $file)) {
                throw new FilesystemException('The file ' . $file . ' could not be found. Please report to cupoftea/wordpress.');
            }
            
            if (preg_match('/\\/$/', $file)) {
                $this->filesystem->ensureDirectoryExists($installPath . '/' . $file);
                
                continue;
            }
            
            $this->filesystem->rename($downloadPath . '/' . $file, $installPath . '/' . $file);
        }
        
        $this->filesystem->remove($downloadPath);
    }
    
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
            
            $this->filesystem->rename($initialDownloadPath, $targetDownloadPath);
        }
        
        $currentFiles = $this->getInstallFiles($current);
        $targetFiles = $this->getInstallFiles($target);
        $deleteFiles = array_diff($currentFiles, $targetFiles);
        
        foreach ($deleteFiles as $file) {
            $this->filesystem->remove($targetInstallPath . '/' . $file);
        }
        
        $this->installCode($target);
    }
    
    protected function removeCode(PackageInterface $package)
    {
        $files = $this->getInstallFiles($package);
        $installPath = $this->getInstallPath($package);
        
        foreach ($files as $file) {
            $this->filesystem->remove($installPath . '/' . $file);
        }
    }
}
