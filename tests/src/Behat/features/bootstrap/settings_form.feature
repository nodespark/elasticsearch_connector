@javascript @api
Feature: Test the settings form
  In order configure Draco DFP
  As an authenticated user
  I need to be able to set the module's settings via it's admin form.

  Scenario: Fill and submit the administration form
    Given I am logged in as a user with the "administrator" role
    When I visit "admin/config/search/elasticsearch-connector"
    Then I should see the text "No clusters available."
