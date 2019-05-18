<?php

namespace Drupal\ComposerScaffold\Tests\Integration;

use Drupal\ComposerScaffold\Operations\AppendOp;
use Drupal\ComposerScaffold\Operations\ReplaceOp;
use Drupal\ComposerScaffold\ScaffoldOptions;
use Drupal\ComposerScaffold\Tests\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\ComposerScaffold\Operations\AppendOp
 *
 * @group Scaffold
 */
class AppendOpTest extends TestCase {

  /**
   * @covers ::process
   */
  public function testProcess() {
    $fixtures = new Fixtures();
    $destination = $fixtures->destinationPath('[web-root]/robots.txt');
    $source = $fixtures->sourcePath('drupal-assets-fixture', 'robots.txt');
    $options = ScaffoldOptions::defaultOptions();
    $originalOp = new ReplaceOp();
    $originalOp->setSource($source);
    $originalOp->setOverwrite(TRUE);
    $prepend = $fixtures->sourcePath('drupal-drupal-test-append', 'prepend-to-robots.txt');
    $append = $fixtures->sourcePath('drupal-drupal-test-append', 'append-to-robots.txt');
    $sut = new AppendOp();
    $sut->setOriginalOp($originalOp);
    $sut->setPrependFile($prepend);
    $sut->setAppendFile($append);
    // Assert that there is no target file before we run our test.
    $this->assertFileNotExists($destination->fullPath());
    // Test the system under test.
    $sut->process($destination, $fixtures->io(), $options);
    // Assert that the target file was created.
    $this->assertFileExists($destination->fullPath());
    // Assert the target contained the contents from the correct scaffold files.
    $contents = trim(file_get_contents($destination->fullPath()));
    $expected = <<<EOT
# robots.txt fixture scaffolded from "file-mappings" in drupal-drupal-test-append composer.json fixture.
# This content is prepended to the top of the existing robots.txt fixture.
# ::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::

# Test version of robots.txt from drupal/core.

# ::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
# This content is appended to the bottom of the existing robots.txt fixture.
# robots.txt fixture scaffolded from "file-mappings" in drupal-drupal-test-append composer.json fixture.
EOT;
    $this->assertEquals(trim($expected), $contents);
    // Confirm that expected output was written to our io fixture.
    $output = $fixtures->getOutput();
    $this->assertContains('Copy [web-root]/robots.txt from assets/robots.txt', $output);
    $this->assertContains('Prepend to [web-root]/robots.txt from assets/prepend-to-robots.txt', $output);
    $this->assertContains('Append to [web-root]/robots.txt from assets/append-to-robots.txt', $output);
  }

}
