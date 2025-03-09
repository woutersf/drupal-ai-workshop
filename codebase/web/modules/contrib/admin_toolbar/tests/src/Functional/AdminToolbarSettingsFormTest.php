<?php

namespace Drupal\Tests\admin_toolbar\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test the Admin Toolbar settings form.
 *
 * @group admin_toolbar
 */
class AdminToolbarSettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'admin_toolbar',
  ];

  /**
   * A user with access to the Admin Toolbar settings form permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $permissions = [
      'access toolbar',
      'access administration pages',
      'administer site configuration',
    ];
    $this->adminUser = $this->drupalCreateUser($permissions);
  }

  /**
   * Test backend admin toolbar settings form fields and submission.
   */
  public function testAdminToolbarSettingsForm(): void {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    // Log in as an admin user to test admin pages.
    $this->drupalLogin($this->adminUser);

    // Test the 'Admin Toolbar settings' page form submission and fields.
    $this->drupalGet('admin/config/user-interface/admin-toolbar');
    $this->submitForm([], 'Save configuration');
    $assert->pageTextContains('The configuration options have been saved.');

    // Test 'Menu depth' for the Admin Toolbar settings form, under:
    // Configuration > User interface > Admin Toolbar.
    // Default value '4': the menu should be displayed as level 3.
    $assert->elementExists('xpath', '//div[@class="toolbar-menu-administration"]//ul[contains(@class, "toolbar-menu")]//li[contains(@class, "menu-item")]//ul[@class="toolbar-menu"]//li[contains(@class, "menu-item")]//ul[@class="toolbar-menu"]//li[@class="menu-item"]//a[contains(@href, "/admin/config/user-interface/admin-toolbar") and contains(.,"Admin Toolbar")]');

    // Set the 'Menu depth' to '2' and save the form.
    $edit = [
      'menu_depth' => '2',
    ];
    $this->submitForm($edit, 'Save configuration');
    $assert->pageTextContains('The configuration options have been saved.');

    // Check the menu item 'Admin Toolbar' is not displayed.
    $assert->elementNotExists('xpath', '//div[@class="toolbar-menu-administration"]//ul[contains(@class, "toolbar-menu")]//li[contains(@class, "menu-item")]//ul[@class="toolbar-menu"]//li[contains(@class, "menu-item")]//ul[@class="toolbar-menu"]//li[@class="menu-item"]//a[contains(@href, "/admin/config/user-interface/admin-toolbar") and contains(.,"Admin Toolbar")]');
    // Check the menu item 'User interface' does not have a child 'ul'.
    $assert->elementNotExists('xpath', '//div[@class="toolbar-menu-administration"]//ul[contains(@class, "toolbar-menu")]//li[contains(@class, "menu-item")]//ul[@class="toolbar-menu"]//li[contains(@class, "menu-item")]//ul');
    // Check the menu item 'User interface' has a single child 'a' link tag.
    $assert->elementExists('xpath', '//div[@class="toolbar-menu-administration"]//ul[contains(@class, "toolbar-menu")]//li[contains(@class, "menu-item")]//ul[@class="toolbar-menu"]//li[contains(@class, "menu-item") and count(child::*)=1 and child::*=a]');

  }

}
