@api @javascript
Feature: The module can be configured through its configuration page

  Scenario: User without permissions can not visit the config page
    Given I am logged in as a user with the authenticated role

    When I am on "/admin/config/yoast_seo"

    Then I should see "Access denied"
    And I should see "You are not authorized to access this page."

  Scenario: User with permission can visit the config page
    Given I am logged in as a user with the 'administer yoast seo' permission

    When I am on "/admin/config/yoast_seo"

    Then I should not see "Access denied"
    And I should not see "You are not authorized to access this page."
    And I should see "Real-time SEO"

  Scenario: Can toggle the auto refresh setting
    Given I am logged in as a user with the 'administer yoast seo' permission
    And I am on "/admin/config/yoast_seo"

    When I check "Enable auto refresh of the Real Time SEO widget result"
    And I press "Save configuration"

    Then the "Enable auto refresh of the Real Time SEO widget result" checkbox should be checked
