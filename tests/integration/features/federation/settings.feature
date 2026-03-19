@federation
Feature: Federation settings enforcement
  Admin settings for incoming and outgoing federation are respected

  Background:
    Given using server "LOCAL"
    And federation is enabled on "LOCAL"
    And user "admin" exists on "LOCAL"
    Given using server "REMOTE"
    And federation is enabled on "REMOTE"
    And user "admin" exists on "REMOTE"

  Scenario: Outgoing federation disabled prevents sharing
    Given using server "LOCAL"
    And "admin" creates a project "Blocked Outgoing" on "LOCAL"
    And outgoing federation is disabled on "LOCAL"
    When "admin" on "LOCAL" shares project with federated user "admin"
    Then the OCS status code should be "400"
    And "admin" on "LOCAL" has 0 federated shares on the project

  Scenario: Incoming federation disabled rejects OCM share
    Given using server "LOCAL"
    And "admin" creates a project "Blocked Incoming" on "LOCAL"
    And incoming federation is disabled on "REMOTE"
    When "admin" on "LOCAL" shares project with federated user "admin"
    Then the OCS status code should be "400"
    And "admin" on "REMOTE" has 0 pending invitations
