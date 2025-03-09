<?php

namespace Drupal\backup_migrate\Core\Service;

/**
 * A very basic mailer that uses the php mail function.
 *
 * In most systems this will be replaced by a wrapper around whatever mail
 * library is used in that system.
 *
 * @package Drupal\backup_migrate\Core\Environment
 */
class Mailer implements MailerInterface {

  /**
   * {@inheritdoc}
   */
  public function send($key, $to, $subject, $body, $replacements = [], $additional_headers = []) {
    // Combine the to objects.
    if (is_array($to)) {
      $to = implode(',', $to);
    }

    // Do the string replacement.
    if ($replacements) {
      $subject = strtr($subject, $replacements);
      $body = strtr($body, $replacements);
    }

    $langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
    \Drupal::service('plugin.manager.mail')->mail('backup_migrate', $key, $to, $langcode, [
      'message' => $body,
      'subject' => $subject,
    ]);
  }

}
