<?php

namespace Drupal\Component\Scaffold\Operations;

use Composer\IO\IOInterface;
use Drupal\Component\Scaffold\ScaffoldFilePath;
use Drupal\Component\Scaffold\ScaffoldOptions;

/**
 * Data file that keeps track of one scaffold file's source, destination, and package.
 */
interface OperationInterface {

  /**
   * Process this scaffold operation.
   *
   * @param \Drupal\Component\Scaffold\ScaffoldFilePath $destination
   *   Scaffold file's destination path.
   * @param \Composer\IO\IOInterface $io
   *   IOInterface to writing to.
   * @param \Drupal\Component\Scaffold\ScaffoldOptions $options
   *   Various options that may alter the behavior of the operation.
   *
   * @return ScaffoldResult
   *   Result of the scaffolding operation (is this file managed or unmanaged, etc.)
   */
  public function process(ScaffoldFilePath $destination, IOInterface $io, ScaffoldOptions $options);

}
