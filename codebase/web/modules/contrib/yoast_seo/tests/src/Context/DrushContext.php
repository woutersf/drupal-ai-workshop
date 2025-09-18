<?php

declare(strict_types=1);

namespace Drupal\Tests\yoast_seo\Context;

use Behat\Behat\Context\Context;
use Symfony\Component\Process\Process;

/**
 * Makes it possible to run Drush commands.
 */
class DrushContext implements Context {

  /**
   * Run Drush.
   *
   * @param string[] $arguments
   *   The arguments to provide.
   *
   * @return string
   *   The output if there is any and the error output otherwise.
   */
  public function drush(array $arguments) : string {
    $process = new Process(['drush', ...$arguments]);
    $process->setTimeout(3600);
    $process->run();

    if (!$process->isSuccessful()) {
      throw new \RuntimeException($process->getErrorOutput());
    }

    // Some drush commands write to standard error output (for example enable
    // use drush_log which default to _drush_print_log) instead of returning a
    // string (drush status use drush_print_pipe).
    if (!$process->getOutput()) {
      return $process->getErrorOutput();
    }

    return $process->getOutput();
  }

}
