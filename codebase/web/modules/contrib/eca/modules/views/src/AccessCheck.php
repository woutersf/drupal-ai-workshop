<?php

namespace Drupal\eca_views;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\eca\Event\AccessEventInterface;
use Drupal\eca\Event\TriggerEvent;
use Drupal\views\Views;
use Symfony\Component\Routing\Route;

/**
 * Checks access for displaying configuration translation page.
 */
class AccessCheck implements AccessInterface {

  /**
   * CustomAccessCheck constructor.
   */
  public function __construct(
    protected TriggerEvent $triggerEvent,
  ) {}

  /**
   * A custom access check.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The parametrized route.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account): AccessResultInterface {
    $result = AccessResult::forbidden('No view available');
    $viewId = $route_match->getParameter('view_id');
    if ($viewId !== NULL) {
      $view = Views::getView($viewId);
      if ($view !== NULL) {
        $displayId = $route_match->getParameter('display_id');
        $view->setDisplay($displayId);
        $result = AccessResult::forbidden('No ECA configuration set an access result');
        $event = $this->triggerEvent->dispatchFromPlugin('eca_views:access', $view, $account);
        if ($event instanceof AccessEventInterface) {
          $eventResult = $event->getAccessResult();
          if ($eventResult !== NULL) {
            $result = $eventResult;
          }
        }
      }
    }
    return $result;
  }

}
