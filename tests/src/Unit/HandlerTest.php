<?php

namespace Drupal\Tests\Component\Scaffold\Unit;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Drupal\Component\Scaffold\Handler;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\Component\Scaffold\Handler
 *
 * @group Scaffold
 */
class HandlerTest extends TestCase {
  /**
   * The Composer service.
   *
   * @var \Composer\IO\IOInterface
   */
  protected $composer;
  /**
   * The Composer IO service.
   *
   * @var \Composer\IO\IOInterface
   */
  protected $io;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->composer = $this->prophesize(Composer::class);
    $this->io = $this->prophesize(IOInterface::class);
  }

  /**
   * @covers ::getWebRoot
   */
  public function testGetWebRoot() {
    $expected = './build/docroot';
    $extra = ['composer-scaffold' => ['locations' => ['web-root' => $expected]]];
    $package = $this->prophesize(PackageInterface::class);
    $package->getExtra()->willReturn($extra);
    $this->composer->getPackage()->willReturn($package->reveal());
    $fixture = new Handler($this->composer->reveal(), $this->io->reveal());
    $this->assertSame($expected, $fixture->getWebRoot());
    // Verify correct errors.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('The extra.composer-scaffold.location.web-root is not set in composer.json.');
    $extra = ['allowed-packages' => ['foo/bar']];
    $package->getExtra()->willReturn($extra);
    $this->composer->getPackage()->willReturn($package->reveal());
    $fixture = new Handler($this->composer->reveal(), $this->io->reveal());
    $fixture->getWebRoot();
  }

}
