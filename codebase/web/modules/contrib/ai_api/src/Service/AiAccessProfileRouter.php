<?php

namespace Drupal\ai_api\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ai_api\Entity\AiAccessProfile;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The access profile router helps finding the correct access profile to use.
 */
class AiAccessProfileRouter {

  /**
   * The cache key for the access profiles.
   *
   * @var string
   */
  protected string $cacheKey = 'ai_api:access_profiles_list_';

  /**
   * Constructor.
   */
  public function __construct(
    protected readonly RequestStack $request,
    protected readonly CacheBackendInterface $cache,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Find the correct access point to use.
   *
   * @param string $operation_type
   *   The operation type to find the access profile for.
   *
   * @return \Drupal\ai_api\Entity\AiAccessProfile|null
   *   The access profile to use.
   */
  public function findAccessProfile(string $operation_type): AiAccessProfile|NULL {
    $profiles = $this->loadAccessProfileRules($operation_type);

    // Start testing the profiles.
    foreach ($profiles[$operation_type] as $profile) {
      // Check if the access key is in the request.
      if ($this->testAccessProfile($profile)) {
        return $profile;
      }
    }
    return NULL;
  }

  /**
   * Test access profile against a request.
   *
   * @param \Drupal\ai_api\Entity\AiAccessProfile $profile
   *   The profile to test.
   *
   * @return bool
   *   If the profile is valid.
   */
  protected function testAccessProfile(AiAccessProfile $profile): bool {
    // Check which method to use.
    $method = $profile->get('access_method');
    // If its query key, we check the query.
    if ($method === 'query_string') {
      $query = $this->request->getCurrentRequest()->query->get($profile->get('access_key'));
      return $query === $profile->get('permission');
    }
    // If its header key, we check the header.
    if ($method === 'headers') {
      // Check if the header has a colon - like bearer token.
      $parts = explode(':', $profile->get('access_key'));
      if (count($parts) == 2) {
        $header = $this->request->getCurrentRequest()->headers->get($parts[0]);
        $header = trim(str_replace(trim($parts[1]), '', $header));
      }
      else {
        $header = $this->request->getCurrentRequest()->headers->get($profile->get('access_key'));
      }
      return $header === $profile->get('id');
    }

    return FALSE;
  }

  /**
   * Load the access profiles.
   *
   * @param string $operation_type
   *   The operation type to load the access profiles for.
   *
   * @return array
   *   The access profiles with their rules.
   */
  protected function loadAccessProfileRules(string $operation_type): array {
    $cache = $this->cache->get($this->cacheKey . $operation_type);
    if ($cache) {
      return $cache->data;
    }

    // Load all access profiles.
    /** @var \Drupal\ai_api\Entity\AiAccessProfile[] $profiles */
    $profiles = $this->entityTypeManager->getStorage('ai_access_profile')->loadMultiple();
    $profile_list = [];
    foreach ($profiles as $profile) {
      // If its disabled, we don't add it.
      if (!$profile->get('status')) {
        continue;
      }
      // If its not the correct operation type, we don't add it.
      if ($operation_type && !in_array($operation_type, array_keys($profile->get('operation_types')))) {
        continue;
      }

      foreach ($profile->get('operation_types') as $type => $value) {
        $profile_list[$type][] = $profile;
      }
    }

    $this->cache->set($this->cacheKey . $operation_type, $profile_list);
    return $profile_list;
  }

}
