<?php

namespace Drupal\simple_crawler;

use GuzzleHttp\Client;
use ivan_boring\Readability\Configuration;
use ivan_boring\Readability\Readability;

/**
 * Guzzle Crawler API.
 */
class Crawler {

  /**
   * The http client.
   */
  protected Client $client;

  /**
   * Constructs a new Crawler object.
   *
   * @param \GuzzleHttp\Client $client
   *   Http client.
   */
  public function __construct(Client $client) {
    $this->client = $client;
  }

  /**
   * Crawl.
   *
   * @param string $url
   *   The url to visit.
   * @param array $parameters
   *   The parameters for the call. q have to be set.
   *
   * @return string
   *   The body response.
   */
  public function request($url, array $parameters) {
    $options['connect_timeout'] = $parameters['connect_timeout'] ?? 15;
    $options['read_timeout'] = $parameters['read_timeout'] ?? 15;

    // Don't fail on 404.
    $options['http_errors'] = FALSE;

    // Set custom headers.
    if (!empty($parameters['custom_headers'])) {
      $headers = $this->textareaToArray($parameters['custom_headers']);
      if (!empty($headers)) {
        $options['headers'] = $headers;
      }
    }

    // Set custom cookies.
    if (!empty($parameters['custom_cookies'])) {
      $cookies = $this->textareaToArray($parameters['custom_cookies'], '=');
      if (!empty($cookies)) {
        $options['headers']['Cookie'] = '';
        foreach ($cookies as $key => $val) {
          $options['headers']['Cookie'] .= "$key=$val; ";
        }
      }
    }

    // Set user agent.
    if (!empty($parameters['user_agent'])) {
      $options['headers']['user-agent'] = $parameters['user_agent'];
    }

    // Set basic auth.
    if (!empty($parameters['basic_auth_username']) && !empty($parameters['basic_auth_password'])) {
      $options['auth'] = [
        $parameters['basic_auth_username'],
        $parameters['basic_auth_password'],
      ];
    }

    // Send request.
    $response = $this->client->request('GET', $url, $options);
    if (!empty($parameters['rate_limit_wait'])) {
      usleep($parameters['rate_limit_wait']);
    }
    return $response->getBody();
  }

  /**
   * Gets a usable HTML replacment client that can get articles.
   *
   * @param string $url
   *   The url to scrape.
   * @param bool $article_only
   *   If we want to get only the article.
   *
   * @return string|null
   *  The body response or null if failure.
   */
  public function scrapePageAsBrowser($url, $article_only = TRUE) {
    $body = $this->request($url, [
      'connect_timeout' => 15,
      'read_timeout' => 15,
      'custom_headers' => "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\nAccept-Language: en-US,en;q=0.5",
      'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3',
    ]);

    // If we want to get only the article.
    if ($article_only) {
      $readability = new Readability(new Configuration());
      $done = $readability->parse($body);
      $body = $done ? $readability->getContent() : NULL;
    }
    else {
      $body = mb_convert_encoding((string) $body, 'utf-8', 'utf-8');
    }

    return $body;
  }

  /**
   * Convert textarea to array.
   *
   * @param string $text
   *   The text to convert.
   * @param string $separator
   *   The separator.
   *
   * @return array
   *   The array.
   */
  protected function textareaToArray($text, $separator = ":") {
    $lines = explode("\n", $text);
    $return = [];
    foreach ($lines as $line) {
      $line = explode($separator, $line);
      if (count($line) !== 2) {
        continue;
      }
      $return[trim($line[0])] = trim($line[1]);
    }
    return $return;
  }

}
