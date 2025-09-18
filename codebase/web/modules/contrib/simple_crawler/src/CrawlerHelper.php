<?php

namespace Drupal\simple_crawler;

use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * The crawler helper.
 */
class CrawlerHelper {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Construct the crawler helper.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct($entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Crude simple DOM traverser.
   *
   * @todo Use an actual traversal library.
   *
   * @var string $html
   *   The html.
   * @var string $tag
   *   The tag to get.
   * @var string $remove
   *   The tags to remove.
   *
   * @return string
   *   The cut out html.
   */
  public function getPartial($html, $tag = 'body', $remove = "") {
    // Get the whole HTML.
    $dom = new \DOMDocument();
    $dom->loadHTML($html);
    // Get the new one.
    $mock = new \DOMDocument();

    // Figure out if classes or id is involved.
    $parts = explode('.', $tag);
    $tag = isset($parts[1]) ? $parts[0] : $tag;
    $class = $parts[1] ?? '';
    $parts = explode('#', $tag);
    $tag = isset($parts[1]) ? $parts[0] : $tag;
    $id = $parts[1] ?? '';

    // Remove.
    foreach (explode("\n", $remove) as $tagRemove) {
      $removals = $dom->getElementsByTagName($tagRemove);
      for ($t = 0; $t < $removals->count(); $t++) {
        $dom->removeChild($removals->item($t));
      }
    }

    // Get the rest.
    $tags = $dom->getElementsByTagName($tag);

    for ($t = 0; $t < $tags->count(); $t++) {
      /** @var DOMNode */
      $tag = $tags->item($t);
      if ($class && $tag->getAttribute('class') != $class) {
        continue;
      }
      if ($id && $tag->getAttribute('id') != $id) {
        continue;
      }
      foreach ($tag->childNodes as $child) {
        $mock->appendChild($mock->importNode($child, TRUE));
      }
    }
    return $mock->saveHTML();
  }

  /**
   * Cleanup links.
   *
   * @param array $links
   *   The links to clean.
   * @param string $parentLink
   *   The parent link.
   * @param array $config
   *   The config.
   *
   * @return array
   *   The cleaned links.
   */
  public function cleanLinks($links, $parentLink, $config) {
    $cleaned = [];
    // Get the parent host and protocol as one string.
    $parentHost = parse_url($parentLink, PHP_URL_SCHEME) . '://' . parse_url($parentLink, PHP_URL_HOST);

    foreach ($links as $link) {
      // If it is a hash link, remove it.
      if (str_contains($link, '#')) {
        // We get the base link.
        $link = explode('#', trim($link))[0];
        if (!$link) {
          continue;
        }
      }
      // If it is a mailto link, remove it.
      if (strpos($link, 'mailto:') === 0) {
        continue;
      }
      // If it is a tel link, remove it.
      if (strpos($link, 'tel:') === 0) {
        continue;
      }
      // If it is a javascript link, remove it.
      if (strpos($link, 'javascript:') === 0) {
        continue;
      }
      // If it is a data link, remove it.
      if (strpos($link, 'data:') === 0) {
        continue;
      }
      // If it is a ftp link, remove it.
      if (strpos($link, 'ftp:') === 0) {
        continue;
      }
      // If it is a skype link, remove it.
      if (strpos($link, 'skype:') === 0) {
        continue;
      }
      // If it is a callto link, remove it.
      if (strpos($link, 'callto:') === 0) {
        continue;
      }
      // If it is a sms link, remove it.
      if (strpos($link, 'sms:') === 0) {
        continue;
      }
      // If it is a whatsapp link, remove it.
      if (strpos($link, 'whatsapp:') === 0) {
        continue;
      }
      // If it is a viber link, remove it.
      if (strpos($link, 'viber:') === 0) {
        continue;
      }
      // If it is a facetime link, remove it.
      if (strpos($link, 'facetime:') === 0) {
        continue;
      }
      // If it is a facetime-audio link, remove it.
      if (strpos($link, 'facetime-audio:') === 0) {
        continue;
      }
      // If its relative starting with /, make it absolute.
      if (strpos($link, '/') === 0) {
        $link = $parentHost . $link;
      }
      // If its relative, but without /, make it absolute.
      if (strpos($link, '/') !== 0 && strpos($link, 'http') !== 0) {
        // If the parent link has a file we have to remove it.
        $parts = explode('/', $parentLink);
        array_pop($parts);
        $parentLink = implode('/', $parts);
        $parentLink = substr($parentLink, -1) == '/' ? $parentLink : $parentLink . '/';
        // Then add parents
        $link = $parentLink . $link;
      }
      // If its wanted to just keep the host, then remove the rest.
      if ($config['host_only']) {
        if (strpos($link, $parentHost) !== 0) {
          continue;
        }
      }
      // Check if there is a include pattern and run it.
      if (!empty($config['include_pattern'])) {
        if (!preg_match('/' . $config['include_pattern'] . '/', $link)) {
          continue;
        }
      }
      // Check if there is a exclude pattern and run it.
      if (!empty($config['exclude_pattern'])) {
        if (preg_match('/' . $config['exclude_pattern'] . '/', $link)) {
          continue;
        }
      }

      // If the link is within the list of excluded pages, skip it.
      foreach (explode(',', $config['exclude_pages']) as $exclude) {
        $abs = $parentHost . '/' . trim($exclude);
        if ($abs == $link || $abs . '/' == $link) {
          continue 2;
        }
      }
      // If the link is with index.php, index.html etc. remove it from the link.
      if (preg_match('/index\.(php|html|htm|asp|aspx|jsp|cfm|cgi|pl|py|rb|cs|vb|c|cpp|java|js|css|scss|less|sass|ts|tsx|jsx|json|xml|yml|yaml|md|txt)$/', $link)) {
        $link = preg_replace('/index\.(php|html|htm|asp|aspx|jsp|cfm|cgi|pl|py|rb|cs|vb|c|cpp|java|js|css|scss|less|sass|ts|tsx|jsx|json|xml|yml|yaml|md|txt)$/', '', $link);
      }
      $link = trim($link);
      $cleaned[$link] = $link;
    }

    return array_values($cleaned);
  }

  /**
   * Get all the possible formats to scrape.
   *
   * @param array $config
   *   The config.
   *
   * @return array
   *   The formats.
   */
  public function getFormats($config) {
    $formats = [];
    $scrapeFormats = $config['types_to_scrape'];
    foreach ($scrapeFormats as $scrapeFormat => $set) {
      if (!$set) {
        continue;
      }
      switch ($scrapeFormat) {
        case 'webpages':
          $formats = array_merge($formats, ['html', 'htm', 'asp', 'php', '']);
          break;

        case 'images':
          $formats = array_merge($formats, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp']);
          break;

        case 'pdfs':
          $formats = array_merge($formats, ['pdf']);
          break;

        case 'docs':
          $formats = array_merge($formats, ['doc', 'docx']);
          break;

        case 'videos':
          $formats = array_merge($formats, ['mp4', 'avi', 'mov', 'webm', 'mkv', 'flv']);
          break;

        case 'audios':
          $formats = array_merge($formats, ['mp3', 'wav', 'flac', 'ogg', 'm4a', 'wma', 'aac']);
          break;

        case 'archives':
          $formats = array_merge($formats, ['zip', 'rar', '7z']);
          break;

        case 'scripts':
          $formats = array_merge($formats, ['js', 'css']);
          break;

        case 'others':
          // Explode the other formats.
          $otherFormats = explode(',', $config['other_formats']);
          foreach ($otherFormats as $format) {
            $formats[] = trim($format);
          }
          break;
      }
    }
    return $formats;
  }

  /**
   * Get text format.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   *
   * @return string|null
   *   The format.
   */
  public function getTextFormat(FieldDefinitionInterface $fieldDefinition) {
    $allFormats = $this->entityTypeManager->getStorage('filter_format')->loadMultiple();
    // Maybe no formats are set.
    if (empty($allFormats)) {
      return NULL;
    }
    $format = $fieldDefinition->getSetting('allowed_formats');
    return $format[0] ?? key($allFormats);
  }

}
