<?php

namespace Drupal\ComposerScaffold\Operations;

/**
 * Use OriginalOpAwareTrait to be informed of any op at the same destination path.
 */
trait OriginalOpAwareTrait {
  /**
   * The original operation at the same destination path.
   *
   * @var OperationInterface
   *   The original operation at the same destination path.
   */
  protected $originalOp;

  /**
   * {@inheritdoc}
   */
  public function setOriginalOp(OperationInterface $originalOp) {
    $this->originalOp = $originalOp;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasOriginalOp() {
    return isset($this->originalOp);
  }

  /**
   * {@inheritdoc}
   */
  public function originalOp() {
    return $this->originalOp;
  }

}
