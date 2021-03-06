<?php

namespace Grasmash\ComposerScaffold;

use Composer\Util\Filesystem;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;
use Grasmash\ComposerScaffold\ScaffoldFilePath;
use Grasmash\ComposerScaffold\Operations\ScaffoldResult;

/**
 * Generates an 'autoload.php' that includes the autoloader created by Composer.
 */
class GenerateAutoloadReferenceFile {
  protected $vendorPath;

  /**
   * GenerateAutoloadReferenceFile constructor.
   *
   * @param string $vendorPath
   *   Path to the vendor directory.
   */
  public function __construct($vendorPath) {
    $this->vendorPath = $vendorPath;
  }

  /**
   * Generate the autoload file at the specified location.
   *
   * This only writes a bit of PHP that includes the autoload file that
   * Composer generated. Drupal does this so that it can guarentee that there
   * will always be an `autoload.php` file in a well-known location.
   *
   * @param \Grasmash\ComposerScaffold\ScaffoldFilePath $autoloadPath
   *   Where to write the autoload file.
   *
   * @return \Grasmash\ComposerScaffold\Operations\ScaffoldResult
   *   The result of the autoload file generation
   */
  public function generateAutoload(ScaffoldFilePath $autoloadPath) {
    $location = dirname($autoloadPath->fullPath());
    // Calculate the relative path from the webroot (location of the project
    // autoload.php) to the vendor directory.
    $fs = new SymfonyFilesystem();
    $relativeVendorPath = $fs->makePathRelative($this->vendorPath, realpath($location));
    $fs->dumpFile($autoloadPath->fullPath(), $this->autoLoadContents($relativeVendorPath));
    return (new ScaffoldResult($autoloadPath))->setManaged();
  }

  /**
   * Build the contents of the autoload file.
   *
   * @return string
   *   Return the contents for the autoload.php.
   */
  protected function autoLoadContents($relativeVendorPath) {
    $relativeVendorPath = rtrim($relativeVendorPath, '/');
    return <<<EOF
<?php

/**
 * @file
 * Includes the autoloader created by Composer.
 *
 * This file was generated by composer-scaffold.
 *.
 * @see composer.json
 * @see index.php
 * @see core/install.php
 * @see core/rebuild.php
 * @see core/modules/statistics/statistics.php
 */

return require __DIR__ . '/{$relativeVendorPath}/autoload.php';

EOF;
  }

}
