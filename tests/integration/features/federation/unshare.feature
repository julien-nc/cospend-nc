@federation
Feature: Federation project unsharing
  Remove federated project shares and verify cleanup on both sides

  Background:
    Given using server "LOCAL"
    And federation is enabled on "LOCAL"
    And user "admin" exists on "LOCAL"
    Given using server "REMOTE"
    And federation is enabled on "REMOTE"
    And user "admin" exists on "REMOTE"

  Scenario: Host removes a federated share and remote user loses access
    Given using server "LOCAL"
    And "admin" creates a project "Shared For Removal" on "LOCAL"
    When "admin" on "LOCAL" shares project with federated user "admin"
    Then "admin" on "REMOTE" has 1 pending invitation
    When "admin" on "REMOTE" accepts the pending invitation
    Then "admin" on "REMOTE" can see the federated project "Shared For Removal"
    When "admin" on "LOCAL" removes the federated share
    Then "admin" on "LOCAL" has 0 federated shares on the project
    And "admin" on "REMOTE" has 0 pending invitations after waiting

  Scenario: Host removes a share that was never accepted
    Given using server "LOCAL"
    And "admin" creates a project "Pending Only" on "LOCAL"
    When "admin" on "LOCAL" shares project with federated user "admin"
    Then "admin" on "LOCAL" has 1 federated shares on the project
    And "admin" on "REMOTE" has 1 pending invitation
    When "admin" on "LOCAL" removes the federated share
    Then "admin" on "LOCAL" has 0 federated shares on the project
