# Cross-Project Balance Feature Implementation

## GitHub Issue
Implements [GitHub issue #281 - Cross-project balances](https://github.com/julien-nc/cospend-nc/issues/281)

## Overview
This feature adds the ability to view aggregated balance information across all projects a user participates in. It shows:
- Total amount the user owes across all projects
- Total amount owed to the user across all projects  
- Net balance (positive = net creditor, negative = net debtor)
- Per-person breakdown showing relationships with each person across projects
- Project-level details for each person when multiple projects are involved

## What to Test

### 1. Navigation and Access
- **Location**: Click on "My cumulated balance" in the left navigation sidebar
- **Expected**: Opens cross-project balance view in the main content area
- **Test scenarios**:
  - User with no projects (should show empty state)
  - User with single project (should aggregate properly)
  - User with multiple projects (main use case)

### 2. Balance Calculations
- **Key Logic**: Balance calculations should match individual project settlement views
- **Test scenarios**:
  - Compare cross-project totals with manual addition of individual project balances
  - Verify that archived projects are excluded from calculations
  - Test with projects where user is not a member (should be excluded)
  - Test with mixed positive/negative balances across projects
  - Test with multiple projects in different currencies

### 3. Display Logic
- **"You owe" vs "Owes you" labels**: 
  - When someone owes YOU money → Shows "Owes you: X" in green
  - When YOU owe someone money → Shows "You owe: X" in red
- **Summary cards**:
  - "Total you owe" should be red/negative styling
  - "Total owed to you" should be green/positive styling
  - "Net balance" should be green if positive, red if negative

### 4. Person Aggregation
- **Nextcloud users**: Same user across projects should be aggregated by userid
- **Guest users**: Same name (case-insensitive) should be aggregated
- **Edge cases**: Different capitalization of names should aggregate correctly

### 5. Project Details
- **Single project**: Shows project name inline
- **Multiple projects**: Shows expandable "Show X projects" button
- **Expansion**: Click to show/hide individual project contributions

### 6. Error Handling
- **API failures**: Should show error state with retry button
- **Loading states**: Should show loading spinner during data fetch
- **Empty states**: Should show "All settled up!" when no balances exist

### 7. Performance
- **Large datasets**: Test with many projects and members
- **Response time**: API should respond reasonably quickly
- **Memory usage**: Component should not cause memory leaks

## Technical Implementation

### Backend Changes
1. **lib/Service/CospendService.php**
   - Added `getCrossGroupBalances()` method for balance aggregation
   - Added `calculateProjectDebts()` for per-project calculations  
   - Added `getPersonIdentifier()` for cross-project person matching
   - Added `generateBalanceSummary()` for human-readable summaries

2. **lib/Service/LocalProjectService.php**
   - Added public `getProjectBalance()` wrapper method

3. **lib/Controller/ApiController.php**
   - Added `getCrossGroupBalances()` API endpoint

4. **appinfo/routes.php**
   - Added `/api/v1/cross-project-balances` route

### Frontend Changes
1. **src/components/CrossProjectBalanceView.vue**
   - New dedicated component for cross-project balance display
   - Summary cards, person lists, project breakdowns
   - Loading/error states, expandable details

2. **src/App.vue**
   - Added `cross-project-balances` mode handling
   - Added event subscription for navigation requests

3. **src/components/CospendNavigation.vue**
   - Made cumulative balance clickable to trigger cross-project view

4. **src/network.js**
   - Added `getCrossProjectBalances()` API client function

## Balance Logic Explanation
The balance calculation maintains consistency with existing settlement views:

- **From API**: Member balances represent what each member owes (negative) or is owed (positive)
- **Cross-project aggregation**: Direct relationship between current user and each other member
- **Display interpretation**: 
  - Positive balance = current user owes money to that person
  - Negative balance = that person owes money to current user

This ensures that cross-project totals match the sum of individual project balances when viewed in settlement screens.

## Files Modified
- `lib/Service/CospendService.php` (backend balance logic)
- `lib/Service/LocalProjectService.php` (balance data access)
- `lib/Controller/ApiController.php` (API endpoint)
- `appinfo/routes.php` (route definition)
- `src/components/CrossProjectBalanceView.vue` (main UI component)
- `src/App.vue` (mode handling)
- `src/components/CospendNavigation.vue` (navigation trigger)
- `src/network.js` (API client)

## Compatibility
- **Nextcloud versions**: Compatible with existing Nextcloud support
- **Database**: Uses existing tables, no migrations required
- **API**: Follows existing OCS API patterns
- **UI**: Uses existing Nextcloud Vue components

## To Do
This is just the first version that gets the calculation to work. Future improvements should include:
- Settlement logic
- Mobile-optimized layouts
    - Compatibility with Moneybuster (?)
- Filtering by time period
- Export functionality
- Email notifications for large balances (?)