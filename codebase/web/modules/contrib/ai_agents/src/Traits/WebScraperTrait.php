<?php

namespace Drupal\ai_agents\Traits;

/**
 * This makes sure to load the correct web scraper tool.
 */
trait WebScraperTrait {

  /**
   * The scraper service.
   *
   * @var \Drupal\scrapingbot\ScrapingBot|\Drupal\simple_crawler\Crawler|null
   */
  protected $scraper = NULL;

  /**
   * The scraper tool.
   *
   * @var string
   */
  protected $tool;

  /**
   * Load the correct web scraper tool.
   *
   * @param string $tool
   *   The tool to load.
   *
   * @return bool
   *   TRUE if the tool was loaded, FALSE otherwise.
   */
  public function loadTool($tool) {
    $this->tool = $tool;
    $this->scraper = NULL;
    if ($tool == 'simple_crawler' && \Drupal::hasService('simple_crawler.crawler')) {
      $this->scraper = \Drupal::service('simple_crawler.crawler');
    }
    elseif ($tool == 'scrapingbot' && \Drupal::hasService('scrapingbot.api')) {
      $this->scraper = \Drupal::service('scrapingbot.api');
    }

    return $this->scraper !== NULL;
  }

  /**
   * Scrape a page.
   *
   * @param string $url
   *   The url to scrape.
   * @param bool $use_readable
   *   Use a readable version of the html.
   * @param array $config
   *   Config for scrapingbot.
   *
   * @return string
   *   The scraped html.
   */
  public function scrape($url, $use_readable = TRUE, $config = []) {
    if ($this->tool = 'simple_scraper') {
      return $this->scraper->scrapePageAsBrowser($url, $use_readable);
    }
    elseif ($this->tool = 'scrapingbot') {
      $new_config = [
        'useChrome' => $config['useChrome'] ?? TRUE,
        'premiumProxy' => $config['premiumProxy'] ?? FALSE,
        'proxyCountry' => $config['proxyCountry'] ?? 'US',
        'waitForNetworkRequests' => $config['waitForNetworkRequests'] ?? TRUE,
      ];
      return $this->scraper->scrapeRaw($url, $use_readable, $new_config);
    }
    throw new \Exception('No scraper tool loaded.');
  }

}
