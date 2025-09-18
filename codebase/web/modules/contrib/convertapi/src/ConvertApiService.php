<?php

use ConvertApi\ConvertApi;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\file\Entity\File;
use Drupal\key\KeyRepositoryInterface;

class ConvertApiService {

  /**
   * The api key.
   */
  private string $apiKey;

  /**
   * Constructs a new ConvertApiService object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\key\KeyRepositoryInterface $keyRepository
   *   The key repository.
   */
  public function __construct(ConfigFactoryInterface $configFactory, KeyRepositoryInterface $keyRepository) {
    $key = $configFactory->get('convertapi.settings')->get('api_key') ?? '';
    if ($key) {
      $this->apiKey = $keyRepository->getKey($key)->getKeyValue();
    }
  }

  /**
   * Convert the file.
   *
   * @param \Drupal\file\Entity\File $file
   *   The file to convert.
   *
   * @return string
   *   The converted file data.
   */
  public function convertFromFile(File $file) {
    ConvertApi::setApiSecret($this->apiKey);
    if (in_array($file->getMimeType(), [
      'application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'application/vnd.ms-powerpoint',
      'application/vnd.openxmlformats-officedocument.presentationml.presentation',
      'application/vnd.ms-excel',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'application/pdf',
    ])) {
      $result = ConvertApi::convert('txt', ['File' => $file->getFileUri()]);
      $values[] = $result->getFile()->getContents();
    }
    else {
      throw new \Exception('Invalid file type for ConvertAPI.');
    }
  }

}
