<?php

namespace Drupal\simple_crawler\Batch;

/**
 * The Link Crawler for batch processing.
 */
class LinkCrawler {

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
   * @param array $context
   *   The context.
   */
  public static function startCrawl($entity, $link, array $config, $fieldDefinition, &$context) {
    if (!empty($config['cool_down'])) {
      // Milliseconds.
      usleep($config['cool_down'] * 1000);
    }
    if (!isset($context['results']['links_left'])) {
      $context['results']['links_left'] = $config['links_left'] ?? 1;
    }
    $context['message'] = 'Crawling ' . $link;

    $context['results']['links_left']--;
    // If we have already scraped this link, return.
    if (in_array($link, $context['results']['found_links'] ?? [])) {
      return;
    }
    // Scrape the link.
    $options['useChrome'] = $config['use_chrome'];
    $options['waitForNetworkRequests'] = $config['wait_for_network'];
    $options['proxyCountry'] = $config['proxy_country'];
    $options['premiumProxy'] = $config['use_premium_proxy'];
    $rawHtml = \Drupal::service('simple_crawler.crawler')->request($link, $options);
    $rawHtml = mb_convert_encoding((string) $rawHtml, 'utf-8', 'utf-8');
    // If we are at the end, save and return.
    if ($config['depth'] == 0) {
      if ($context['results']['links_left'] == 0) {
        $saveLinks = [];
        foreach ($context['results']['found_links'] as $foundLink) {
          $saveLinks[] = ['uri' => $foundLink];
        }
        $entity->set($fieldDefinition->getName(), $saveLinks);
        $entity->save();
      }
      return;
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
    if (!empty($matches[1])) {
      $links = $matches[1];
      $links = \Drupal::service('simple_crawler.crawler_helper')->cleanLinks($links, $link, $config);

      $config['depth']--;
      $batch = \batch_get();
      // If we have links, scrape them.
      $config['links_left'] = $context['results']['links_left'] + count($links);
      // If we have links and they fit the html part, scrape them.
      $formats = \Drupal::service('simple_crawler.crawler_helper')->getFormats();
      foreach ($links as $link) {
        // Get the extension if it has one.
        $extension = pathinfo($link, PATHINFO_EXTENSION);
        // Check if we should save the link.
        if (in_array($extension, $formats) && !in_array($link, $context['results']['found_links'])) {
          $context['results']['found_links'][] = $link;
        }
        // If it has no extension or if it is a web page, we scrape it.
        if (in_array($extension, ['html', 'htm', 'asp', 'php']) || empty($extension)) {
          // Add to the batch job.
          $context['results']['links_left']++;
          $batch['operations'][] = [
            'Drupal\simple_crawler\Batch\LinkCrawler::startCrawl',
            [$entity, $link, $config, $fieldDefinition],
          ];
        }
      }
      if (!empty($batch['operations'])) {
        \batch_set($batch);
      }
    }
  }

}
