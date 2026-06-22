@federation
Feature: Federation project sharing
  Share Cospend projects across federated Nextcloud instances

  Background:
    Given using server "LOCAL"
    And federation is enabled on "LOCAL"
    And user "admin" exists on "LOCAL"
    Given using server "REMOTE"
    And federation is enabled on "REMOTE"
    And user "admin" exists on "REMOTE"

  Scenario: Share a project with a user on a remote server
    Given using server "LOCAL"
    And "admin" creates a project "Shared Project" on "LOCAL"
    When "admin" on "LOCAL" shares project with federated user "admin"
    Then the OCS status code should be "200"
    And "admin" on "LOCAL" has 1 federated shares on the project

  Scenario: Remote user receives a pending invitation
    Given using server "LOCAL"
    And "admin" creates a project "Invite Project" on "LOCAL"
    When "admin" on "LOCAL" shares project with federated user "admin"
    Then the OCS status code should be "200"
    And "admin" on "REMOTE" has 1 pending invitation

  Scenario: Remote user accepts a pending invitation
    Given using server "LOCAL"
    And "admin" creates a project "Accepted Project" on "LOCAL"
    When "admin" on "LOCAL" shares project with federated user "admin"
    Then "admin" on "REMOTE" has 1 pending invitation
    When "admin" on "REMOTE" accepts the pending invitation
    Then "admin" on "REMOTE" can see the federated project "Accepted Project"

  Scenario: Remote user rejects a pending invitation
    Given using server "LOCAL"
    And "admin" creates a project "Rejected Project" on "LOCAL"
    When "admin" on "LOCAL" shares project with federated user "admin"
    Then "admin" on "REMOTE" has 1 pending invitation
    When "admin" on "REMOTE" rejects the pending invitation
    Then "admin" on "REMOTE" cannot see any federated projects
    And "admin" on "REMOTE" has 0 pending invitations
