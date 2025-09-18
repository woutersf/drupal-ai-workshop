<?php

namespace Drupal\simple_crawler\Batch;

use ivan_boring\Readability\Configuration;
use ivan_boring\Readability\Readability;

/**
 * The Link Crawler for batch processing.
 */
class DepthCrawler {

  /**
   * Start the crawl.
   *
   * @param object $entity
   *   The entity.
   * @param string $link
   *   The link to crawl.
   * @param array $config
   *   The config.
   * @param object $fieldDefinition
   *   The field definition.
   * @param string $mode
   *   The mode.
   * @param array $context
   *   The context.
   */
  public static function startCrawl($entity, $link, array $config, $fieldDefinition, $mode, &$context) {
    if (!isset($context['results']['links_left'])) {
      $context['results']['links_left'] = $config['links_left'] ?? 1;
    }
    if (!isset($context['results']['old_links'])) {
      $context['results']['old_links'] = $config['old_links'] ?? [
        $link => $link,
      ];
    }

    $context['message'] = 'Crawling ' . $link;

    $context['results']['links_left']--;
    if (!empty($config['cool_down'])) {
      // Milliseconds.
      usleep($config['cool_down'] * 1000);
    }
    // Scrape the link.
    $rawHtml = \Drupal::service('simple_crawler.crawler')->request($link, $config);

    $value = '';
    switch ($config['crawler_mode']) {
      case 'all':
        $value = mb_convert_encoding((string) $rawHtml, 'utf-8', 'utf-8');
        break;

      case 'readibility':
        $readability = new Readability(new Configuration());
        $done = $readability->parse($rawHtml);
        $value = $done ? $readability->getContent() : 'No scrape';
        break;

      case 'selector':
        $value = \Drupal::service('simple_crawler.crawler_helper')->getPartial($rawHtml, $config['crawler_tag'], $config['crawler_remove']);
        break;
    }
    // Put url on top if wanted.
    if ($config['url_on_top'] && $value) {
      $value = 'Source: ' . $link . "<br>\n" . $value;
    }

    if ($value) {
      $context['results']['found_texts'][] = $value;
    }

    // If its wanted to just do inside the body, we get the body only using regex.
    if ($config['body_only']) {
      preg_match('/<body[^>]*>(.*?)<\/body>/is', $rawHtml, $body);
      if (!empty($body[1])) {
        $rawHtml = $body[1];
      }
    }
    // Parse the html, collecting links starting with http* or / using regex.
    preg_match_all('/href=["\']?([^"\'>]+)["\']?/', $rawHtml, $matches);

    // Just add new operations.
    $newOperations = [];
    $batch = \batch_get();
    if (!empty($matches[1])) {
      $links = $matches[1];
      $links = \Drupal::service('simple_crawler.crawler_helper')->cleanLinks($links, $link, $config);

      $config['depth']--;
      // If we have links, scrape them.
      $config['links_left'] = $context['results']['links_left'];
      $config['old_links'] = $context['results']['old_links'];
      foreach ($links as $link) {
        if (!isset($config['old_links'][$link])) {
          $config['old_links'][$link] = $link;
          $config['links_left']++;
        }
      }

      foreach ($links as $link) {
        // Get the extension if it has one.
        $extension = pathinfo($link, PATHINFO_EXTENSION);
        // If its in found links, we don't scrape it.
        if (in_array($link, $context['results']['old_links'])) {
          continue;
        }
        // If it has no extension or if it is a web page, we scrape it.
        if (in_array($extension, ['html', 'htm', 'asp', 'php']) || empty($extension)) {
          $newOperations[] = [
            'Drupal\simple_crawler\Batch\DepthCrawler::startCrawl',
            [$entity, $link, $config, $fieldDefinition, $mode],
          ];
        }
      }
      if (!empty($newOperations)) {
        $batch['operations'] = !empty($batch['operations']) ? array_merge_recursive($batch['operations'], $newOperations) : $newOperations;
        \batch_set($batch);
      }

      $jobsLeft = FALSE;
      if (!empty($batch['operations'])) {
        foreach ($batch['operations'] as $operation) {
          if ($operation[0] == 'Drupal\simple_crawler\Batch\DepthCrawler::startCrawl') {
            $jobsLeft = TRUE;
            break;
          }
        }
      }
      // If there are no jobs left, save it.
      if (!$jobsLeft) {
        $saveTexts = [];
        foreach ($context['results']['found_texts'] as $foundText) {
          if ($mode == 'string') {
            $saveTexts[] = $foundText;
          } else {
            $saveTexts[] = ['value' => $foundText, 'format' => \Drupal::service('simple_crawler.crawler_helper')->getTextFormat($fieldDefinition)];
          }
        }
        $entity->set($fieldDefinition->getName(), $saveTexts);
        $entity->save();
      }
    }
  }

}
