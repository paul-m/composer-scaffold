<?php

namespace Drupal\Component\Scaffold\Operations;

use Composer\IO\IOInterface;
use Drupal\Component\Scaffold\ScaffoldFilePath;
use Drupal\Component\Scaffold\ScaffoldOptions;

/**
 * Scaffold operation to skip a scaffold file (do nothing).
 */
class SkipOp implements OperationInterface {

  /**
   * Skip the specified scaffold file.
   *
   * {@inheritdoc}
   */
  public function process(ScaffoldFilePath $destination, IOInterface $io, ScaffoldOptions $options) {
    $interpolator = $destination->getInterpolator();
    $io->write($interpolator->interpolate("  - Skip <info>[dest-rel-path]</info>: disabled"));
    return (new ScaffoldResult($destination))->setManaged(FALSE);
  }

}
