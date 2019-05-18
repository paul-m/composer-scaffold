<?php

namespace Drupal\Component\Scaffold;

use Composer\Composer;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\CommandEvent;
use Composer\Script\Event;
use Composer\Util\Filesystem;
use Drupal\Component\Scaffold\Operations\OperationCollection;
use Drupal\Component\Scaffold\Operations\OperationFactory;

/**
 * Core class of the plugin.
 *
 * Contains the primary logic which determines the files to be fetched and processed.
 */
class Handler {
  const PRE_COMPOSER_SCAFFOLD_CMD = 'pre-composer-scaffold-cmd';
  const POST_COMPOSER_SCAFFOLD_CMD = 'post-composer-scaffold-cmd';
  /**
   * The Composer service.
   *
   * @var \Composer\Composer
   */
  protected $composer;
  /**
   * Composer's I/O service.
   *
   * @var \Composer\IO\IOInterface
   */
  protected $io;
  protected $manageOptions;
  protected $manageAllowedPackages;
  protected $postPackageListeners;

  /**
   * Handler constructor.
   *
   * @param \Composer\Composer $composer
   *   The Composer service.
   * @param \Composer\IO\IOInterface $io
   *   The Composer I/O service.
   */
  public function __construct(Composer $composer, IOInterface $io) {
    $this->composer = $composer;
    $this->io = $io;
    $this->manageOptions = new ManageOptions($composer);
    $this->manageAllowedPackages = new AllowedPackages($composer, $io, $this->manageOptions);
    $this->postPackageListeners = [];
  }

  /**
   * Post install command event to execute the scaffolding.
   *
   * @param \Composer\Script\Event $event
   *   The Composer event.
   */
  public function onPostCmdEvent(Event $event) {
    $this->scaffold();
  }

  /**
   * The beforeRequire method is called before any 'require' event runs.
   *
   * @param \Composer\Plugin\CommandEvent $event
   *   The Composer Command event.
   */
  public function beforeRequire(CommandEvent $event) {
    // In order to differentiate between post-package events called after
    // 'composer require' vs. the same events called at other times, we will
    // only install our handler when a 'require' event is detected.
    $this->postPackageListeners[] = new DetectAddingPackagesWithScaffolding($this->manageAllowedPackages);
  }

  /**
   * Post package command event.
   *
   * We want to detect packages 'require'd that have scaffold files, but are
   * not yet allowed in the top-level composer.json file.
   *
   * @param \Composer\Installer\PackageEvent $event
   *   Composer package event sent on install/update/remove.
   */
  public function onPostPackageEvent(PackageEvent $event) {
    foreach ($this->postPackageListeners as $listener) {
      $listener->event($event);
    }
  }

  /**
   * Gets the array of file mappings provided by a given package.
   *
   * @param \Composer\Package\PackageInterface $package
   *   The Composer package from which to get the file mappings.
   *
   * @return \Drupal\Component\Scaffold\Operations\OperationInterface[]
   *   An array of destination paths => scaffold operation objects.
   */
  public function getPackageFileMappings(PackageInterface $package) {
    $options = $this->manageOptions->packageOptions($package);
    if ($options->hasFileMapping()) {
      return $this->createScaffoldOperations($package, $options->fileMapping());
    }
    else {
      if (!$options->hasAllowedPackages()) {
        $this->io->writeError("The allowed package {$package->getName()} does not provide a file mapping for Composer Scaffold.");
      }
      return [];
    }
  }

  /**
   * Create scaffold operation objects for all items in the file mappings.
   *
   * @param \Composer\Package\PackageInterface $package
   *   The package that relative paths will be relative from.
   * @param array $package_file_mappings
   *   The package file mappings array (destination path => operation metadata array)
   *
   * @return \Drupal\Component\Scaffold\Operations\OperationInterface[]
   *   A list of scaffolding operation objects
   */
  protected function createScaffoldOperations(PackageInterface $package, array $package_file_mappings) {
    $options = $this->manageOptions->getOptions();
    $scaffoldOpFactory = new OperationFactory($this->composer);
    $scaffoldOps = [];
    foreach ($package_file_mappings as $key => $value) {
      $metadata = $scaffoldOpFactory->normalizeScaffoldMetadata($key, $value);
      $scaffoldOps[$key] = $scaffoldOpFactory->createScaffoldOp($package, $key, $metadata, $options);
    }
    return $scaffoldOps;
  }

  /**
   * Copies all scaffold files from source to destination.
   */
  public function scaffold() {
    // Recursively get the list of allowed packages. Only allowed packages
    // may declare scaffold files. Note that the top-level composer.json file
    // is implicitly allowed.
    $allowedPackages = $this->manageAllowedPackages->getAllowedPackages();
    if (empty($allowedPackages)) {
      return;
    }
    // Call any pre-scaffold scripts that may be defined.
    $dispatcher = new EventDispatcher($this->composer, $this->io);
    $dispatcher->dispatch(self::PRE_COMPOSER_SCAFFOLD_CMD);
    // Fetch the list of file mappings from each allowed package and
    // normalize them.
    $file_mappings = $this->getFileMappingsFromPackages($allowedPackages);
    // Analyze the list of file mappings, and determine which take priority.
    $scaffoldCollection = new OperationCollection($this->io);
    $locationReplacements = $this->manageOptions->getLocationReplacements();
    $scaffoldCollection->coalateScaffoldFiles($file_mappings, $locationReplacements);
    // Write the collected scaffold files to the designated location on disk.
    $scaffoldResults = $scaffoldCollection->processScaffoldFiles($this->manageOptions->getOptions());
    // Generate an autoload file in the document root that includes
    // the autoload.php file in the vendor directory, wherever that is.
    // Drupal requires this in order to easily locate relocated vendor dirs.
    $autoloadPath = ScaffoldFilePath::autoloadPath($this->rootPackageName(), $this->getWebRoot());
    $generator = new GenerateAutoloadReferenceFile($this->getVendorPath());
    $scaffoldResults[] = $generator->generateAutoload($autoloadPath);
    // Add the managed scaffold files to .gitignore if applicable.
    $manager = new ManageGitIgnore(getcwd());
    $manager->manageIgnored($scaffoldResults, $this->manageOptions->getOptions());
    // Call post-scaffold scripts.
    $dispatcher->dispatch(self::POST_COMPOSER_SCAFFOLD_CMD);
  }

  /**
   * Retrieve the path to the web root.
   *
   * Note that only the root package can define the web root.
   *
   * @return string
   *   The file path of the web root.
   *
   * @throws \Exception
   */
  public function getWebRoot() {
    return $this->manageOptions->getOptions()->requiredLocation('web-root', "The extra.composer-scaffold.location.web-root is not set in composer.json.");
  }

  /**
   * Get the path to the 'vendor' directory.
   *
   * @return string
   *   The file path of the vendor directory.
   */
  public function getVendorPath() {
    $vendorDir = $this->composer->getConfig()->get('vendor-dir');
    $filesystem = new Filesystem();
    $filesystem->ensureDirectoryExists($vendorDir);
    return $filesystem->normalizePath(realpath($vendorDir));
  }

  /**
   * Gets a consolidated list of file mappings from all allowed packages.
   *
   * @param \Composer\Package\Package[] $allowed_packages
   *   A multidimensional array of file mappings, as returned by
   *   self::getAllowedPackages().
   *
   * @return \Drupal\Component\Scaffold\Operations\OperationInterface[]
   *   An array of destination paths => scaffold operation objects.
   */
  protected function getFileMappingsFromPackages(array $allowed_packages) {
    $file_mappings = [];
    foreach ($allowed_packages as $package_name => $package) {
      $package_file_mappings = $this->getPackageFileMappings($package);
      $file_mappings[$package_name] = $package_file_mappings;
    }
    return $file_mappings;
  }

  /**
   * Get the root package name.
   *
   * @return string
   *   The package name of the root project
   */
  protected function rootPackageName() {
    $root_package = $this->composer->getPackage();
    return $root_package->getName();
  }

}
