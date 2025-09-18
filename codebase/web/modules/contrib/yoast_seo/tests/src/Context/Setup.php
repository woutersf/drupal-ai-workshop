<?php

declare(strict_types=1);

namespace Drupal\Tests\yoast_seo\Context;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Environment\InitializedContextEnvironment;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

/**
 * Set-up related tasks for our behat tests.
 */
class Setup implements Context {

  /**
   * The DrushContext to run Drush commands with.
   */
  private DrushContext $drushContext;

  /**
   * Provide a clean install before every scenario.
   *
   * @BeforeScenario
   */
  public function installDrupal(BeforeScenarioScope $scope) : void {
    $environment = $scope->getEnvironment();
    assert($environment instanceof InitializedContextEnvironment);
    $drushContext = $environment->getContext(DrushContext::class);
    $this->drushContext = $drushContext;

    // Reset the config cache in our test runner since it may contain config
    // from a previous scenario.
    // @phpstan-ignore-next-line
    \Drupal::configFactory()->reset();

    // Install a fresh site for testing.
    $this->drushContext->drush([
      "site-install",
      "-y",
      "minimal",
      "--extra=--skip-ssl",
      "--site-name='Automated Behat Tests for Real-Time SEO'",
      "--account-name=admin",
      "--account-pass=admin",
      "--account-mail=admin@example.com",
    ]);

    // When there's no database Drupal kicks into Install mode which sets up a
    // read only config. Now that we have a database loaded we need to get
    // Drupal out of that mode.
    // Steps need to be in a specific order here since the install mode also
    // doesn't load the system module (which every module under the sun assumes
    // is loaded).
    //
    // 1.Remove the global that keeps the container in install mode.
    // @phpstan-ignore-next-line
    unset($GLOBALS['conf']['container_service_providers']['InstallerServiceProvider']);
    // 2. Rebuild the container to ensure the Module Handler gets a new module
    //    list.
    $kernel = \Drupal::service('kernel');
    $kernel->invalidateContainer();
    $kernel->rebuildContainer();
    // 3. Reload all the modules to ensure the system module is loaded
    \Drupal::moduleHandler()->reload();
    // 4. Flush all the caches to ensure we don't cache data from the previously
    //    loaded database. This will trigger another container rebuild but
    //    that's fine.
    drupal_flush_all_caches();
    // 5. We must clear the current user, since the container rebuild saves it,
    //    but it references a non-existent user now.
    \Drupal::currentUser()->setInitialAccountId(0);

    // We must enable the Olivero theme with block module so that we have a
    // logout link which is what is needed for DrupalContext to know whether
    // login succeeded.
    $this->assertModuleEnabled("block");
    $this->drushContext->drush(["theme:install", "-y", "olivero"]);
    $this->drushContext->drush(["config:set", "-y", "system.theme", "default", "olivero"]);

    // We always want to enable the module we're testing so it doesn't need to
    // be repeated in every feature file.
    $this->assertModuleEnabled("yoast_seo");
  }

  /**
   * Ensures a given module is enabled.
   *
   * @param string $module
   *   The module to enable.
   *
   * @Given module :module is enabled
   */
  public function assertModuleEnabled(string $module) : void {
    $this->drushContext->drush(["pm:install", "-y", $module]);
    // The container for our bootstrap held by the DrupalExtension for behat
    // must be rebuilt since it may not otherwise know about classes contained
    // in the enabled module.
    $kernel = \Drupal::service('kernel');
    $kernel->invalidateContainer();
    $kernel->rebuildContainer();
  }

  /**
   * Ensures a given theme is enabled.
   *
   * @param string $theme
   *   The theme to enable.
   *
   * @Given theme :module is enabled
   */
  public function assertThemeEnabled(string $theme) : void {
    $this->drushContext->drush(["theme:install", "-y", $theme]);
    // The container for our bootstrap held by the DrupalExtension for behat
    // must be rebuilt since it may not otherwise know about classes contained
    // in the enabled module.
    $kernel = \Drupal::service('kernel');
    $kernel->invalidateContainer();
    $kernel->rebuildContainer();
  }

  /**
   * Update a configuration object.
   *
   * @Given config :config has key :key with value :value
   */
  public function updateSetting(string $config, string $key, string $value) {
    $this->drushContext->drush(["cset", $config, $key, $value]);
  }

}
