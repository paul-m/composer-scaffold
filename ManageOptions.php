<?php

namespace Drupal\ComposerScaffold;

use Composer\Composer;
use Composer\Package\PackageInterface;
use Composer\Util\Filesystem;

/**
 * Per-project options from the 'extras' section of the composer.json file.
 *
 * Projects that describe scaffold files do so via their scaffold options.
 * This data is pulled from the 'composer-scaffold' portion of the extras
 * section of the project data.
 */
class ManageOptions {
  /**
   * The Composer service.
   *
   * @var \Composer\Composer
   */
  protected $composer;

  /**
   * ManageOptions constructor.
   */
  public function __construct($composer) {
    $this->composer = $composer;
  }

  /**
   * Get the root-level scaffold options for this project.
   *
   * @return ScaffoldOptions
   *   The scaffold otpions object
   */
  public function getOptions() {
    return $this->packageOptions($this->composer->getPackage());
  }

  /**
   * The scaffold options for the stipulated project.
   *
   * @param \Composer\Package\PackageInterface $package
   *   The package to fetch the scaffold options from.
   *
   * @return ScaffoldOptions
   *   The scaffold otpions object
   */
  public function packageOptions(PackageInterface $package) {
    return ScaffoldOptions::create($package->getExtra());
  }

  /**
   * GetLocationReplacements creates an interpolator for the 'locations' element.
   *
   * The interpolator returned will replace a path string with the tokens
   * defined in the 'locations' element.
   *
   * Note that only the root package may define locations.
   *
   * @return Interpolator
   *   Object that will do replacements in a string using tokens in 'locations' element.
   */
  public function getLocationReplacements() {
    return (new Interpolator())->setData($this->ensureLocations());
  }

  /**
   * Ensure that all of the locatons defined in the scaffold filed exist.
   *
   * Create them on the filesystem if they do not.
   */
  public function ensureLocations() {
    $fs = new Filesystem();
    $locations = $this->getOptions()->locations() + ['web_root' => './'];
    $locations = array_map(function ($location) use ($fs) {
      $fs->ensureDirectoryExists($location);
      $location = realpath($location);
      return $location;
    }, $locations);
    return $locations;
  }

}
