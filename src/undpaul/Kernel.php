<?php

namespace undpaul;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * Defines the symfony kernel class.
 */
class Kernel extends BaseKernel {

  use MicroKernelTrait;

  /**
   * In PHAR, Kernel cannot auto-detect this path.
   *
   * @return string
   *   The project directory.
   */
  #[\Override]
  public function getProjectDir(): string {
    return dirname(__DIR__);
  }

}
