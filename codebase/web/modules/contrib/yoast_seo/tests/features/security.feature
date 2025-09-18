@api @javascript
Feature: Regression tests for known security issues

  Scenario: XSS in node title field
    Given module node is enabled
    And content type:
      | type    | name    |
      | article | Article |
    And field:
      | entity_type | bundle  | type      | field_name | field_label | form_widget      |
      | node        | article | yoast_seo | field_seo  | SEO         | yoast_seo_widget |
    And I am logged in as a user with the "create article content,use yoast seo,create url aliases" permission
    And I am on "/node/add/article"

    When I fill in "Title" with "&amp;lt;img src=x onerror=&amp;quot;console.error('xss vulnerability')&amp;quot;&amp;gt;"
    And I press "Seo preview"
    And wait for the widget to be updated

  Scenario: XSS in node title field with keyword highlighting
    Given module node is enabled
    And content type:
      | type    | name    |
      | article | Article |
    And field:
      | entity_type | bundle  | type      | field_name | field_label | form_widget      |
      | node        | article | yoast_seo | field_seo  | SEO         | yoast_seo_widget |
    And I am logged in as a user with the "create article content,use yoast seo,create url aliases" permission
    And I am on "/node/add/article"

    When I fill in "Title" with "&amp;lt;img src=x onerror=&amp;quot;console.error('xss vulnerability')&amp;quot;&amp;gt;"
    And I fill in "Focus keyword" with "xss"
    And I press "Seo preview"
    And wait for the widget to be updated
