<?php

namespace Drupal\backup_migrate\Core\Destination;

use Drupal\backup_migrate\Core\File\BackupFileReadableInterface;

/**
 * An interface for managing writeable destinations.
 *
 * @package Drupal\backup_migrate\Core\Destination
 */
interface WritableDestinationInterface extends DestinationInterface {

  /**
   * Save a file to the destination.
   *
   * @param \Drupal\backup_migrate\Core\File\BackupFileReadableInterface $file
   *   The file to save.
   */
  public function saveFile(BackupFileReadableInterface $file);

}
