<?php

namespace Drupal\ai_api\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\ai_api\PluginManager\AiApiAccessPointManager;
use Drupal\ai_api\Service\AiAccessProfileRouter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides routes for the AI API module.
 */
class AiApiRoutes extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Constructor.
   */
  public function __construct(
    protected readonly AiAccessProfileRouter $accessProfileRouter,
    protected readonly RequestStack $request,
    protected readonly AiApiAccessPointManager $accessPointManager,
  ) {
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('ai_api.access_profile_router'),
      $container->get('request_stack'),
      $container->get('plugin.manager.ai_api_access_point'),
    );
  }

  /**
   * Returns the chat completion endpoint.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A JSON response.
   */
  public function chatCompletion(): Response {
    // Try to figure out which AI Access Point to use.
    $access_profile = $this->accessProfileRouter->findAccessProfile('chat');
    if (!$access_profile) {
      return new JsonResponse([
        'No access profile found.',
      ], 401);
    }
    // If an provider is sent, make sure that this is allowed.
    if ($access_profile->get('operation_types')['chat']['allowed_models'] == 'default' && $this->request->getCurrentRequest()->get('provider')) {
      return new JsonResponse([
        'Custom provider is not allowed.',
      ], 401);
    }
    // If a specific provider is allowed, make sure that this is the one.
    if ($access_profile->get('operation_types')['chat']['allowed_models'] == 'specific') {
      $provider = $this->request->getCurrentRequest()->get('provider');
      $model = $this->request->getCurrentRequest()->get('model');
      // Use the shorthand form.
      if (!in_array($provider . '__' . $model, $access_profile->get('operation_types')['chat']['allowed_models'])) {
        return new JsonResponse([
          'The model set is not allowed.',
        ], 403);
      }
    }
    // Load the plugin and let it take over.
    $plugin = $this->accessPointManager->createInstance($access_profile->get('operation_types')['chat']['plugin']);
    return $plugin->runRequest($this->request->getCurrentRequest());
  }

  /**
   * Checks for access to the AI API.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function chatCompletionAccess(AccountInterface $account): AccessResultInterface {
    // Get the correct access profile.
    $access_profile = $this->accessProfileRouter->findAccessProfile('chat');
    if (!$access_profile) {
      return AccessResult::forbidden();
    }

    // Check if the user has the correct permissions.
    if (!$account->hasPermission($access_profile->get('permission'))) {
      return AccessResult::forbidden();
    }

    // If the user is anonymous, check if the setting is set.
    if ($account->isAnonymous() && Settings::get('ai_api_permissive_mode') !== TRUE) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

}
