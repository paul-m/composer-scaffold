<?php

namespace Drupal\ComposerScaffold\Operations;

use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use Drupal\ComposerScaffold\ScaffoldFilePath;
use Drupal\ComposerScaffold\ScaffoldOptions;

/**
 * Scaffold operation to copy or symlink from source to destination.
 */
class ReplaceOp implements OperationInterface {
  protected $source;
  protected $overwrite;

  /**
   * Set the relative path to the source.
   *
   * @param \Drupal\ComposerScaffold\ScaffoldFilePath $sourcePath
   *   The relative path to the source file.
   *
   * @return $this
   */
  public function setSource(ScaffoldFilePath $sourcePath) {
    $this->source = $sourcePath;
    return $this;
  }

  /**
   * Get the source.
   *
   * @return \Drupal\ComposerScaffold\ScaffoldFilePath
   *   The source file reference object.
   */
  public function getSource() {
    return $this->source;
  }

  /**
   * Set whether the scaffold file should overwrite existing files at the same path.
   *
   * @param bool $overwrite
   *   Whether to overwrite existing files.
   *
   * @return $this
   */
  public function setOverwrite($overwrite) {
    $this->overwrite = $overwrite;
    return $this;
  }

  /**
   * Determine whether scaffold file should overwrite files already at the same path.
   *
   * @return bool
   *   Value of the 'overwrite' option.
   */
  public function getOverwrite() {
    return $this->overwrite;
  }

  /**
   * Copy or Symlink the specified scaffold file.
   *
   * {@inheritdoc}
   */
  public function process(ScaffoldFilePath $destination, IOInterface $io, ScaffoldOptions $options) {
    $fs = new Filesystem();
    $destination_path = $destination->fullPath();
    // Do nothing if overwrite is 'false' and a file already exists at the destination.
    if ($this->getOverwrite() === FALSE && file_exists($destination_path)) {
      $interpolator = $destination->getInterpolator();
      $io->write($interpolator->interpolate("  - Skip <info>[dest-rel-path]</info> because it already exists and overwrite is <comment>false</comment>."));
      return (new ScaffoldResult($destination))->setManaged(FALSE);
    }
    // Get rid of the destination if it exists, and make sure that
    // the directory where it's going to be placed exists.
    @unlink($destination_path);
    $fs->ensureDirectoryExists(dirname($destination_path));
    if ($options->symlink() == TRUE) {
      return $this->symlinkScaffold($destination, $io, $options);
    }
    return $this->copyScaffold($destination, $io, $options);
  }

  /**
   * Copy the scaffold file.
   *
   * @param \Drupal\ComposerScaffold\ScaffoldFilePath $destination
   *   Scaffold file to process.
   * @param \Composer\IO\IOInterface $io
   *   IOInterface to writing to.
   * @param \Drupal\ComposerScaffold\ScaffoldOptions $options
   *   Various options that may alter the behavior of the operation.
   */
  public function copyScaffold(ScaffoldFilePath $destination, IOInterface $io, ScaffoldOptions $options) {
    $interpolator = $destination->getInterpolator();
    $this->getSource()->addInterpolationData($interpolator);
    $success = copy($this->getSource()->fullPath(), $destination->fullPath());
    if (!$success) {
      throw new \Exception($interpolator->interpolate("Could not copy source file <info>[src-rel-path]</info> to <info>[dest-rel-path]</info>!"));
    }
    $io->write($interpolator->interpolate("  - Copy <info>[dest-rel-path]</info> from <info>[src-rel-path]</info>"));
    return (new ScaffoldResult($destination))->setManaged($this->getOverwrite());
  }

  /**
   * Symlink the scaffold file.
   *
   * @param \Drupal\ComposerScaffold\ScaffoldFilePath $destination
   *   Scaffold file to process.
   * @param \Composer\IO\IOInterface $io
   *   IOInterface to writing to.
   * @param \Drupal\ComposerScaffold\ScaffoldOptions $options
   *   Various options that may alter the behavior of the operation.
   */
  public function symlinkScaffold(ScaffoldFilePath $destination, IOInterface $io, ScaffoldOptions $options) {
    $interpolator = $destination->getInterpolator();
    $source_path = $this->getSource()->fullPath();
    $destination_path = $destination->fullPath();
    try {
      $fs = new Filesystem();
      $fs->relativeSymlink($this->getSource()->fullPath(), $destination->fullPath());
    }
    catch (\Exception $e) {
      throw new \Exception($interpolator->interpolate("Could not symlink source file <info>[src-rel-path]</info> to <info>[dest-rel-path]</info>! "), 1, $e);
    }
    $io->write($interpolator->interpolate("  - Link <info>[dest-rel-path]</info> from <info>[src-rel-path]</info>"));
    return (new ScaffoldResult($destination))->setManaged($this->getOverwrite());
  }

}
