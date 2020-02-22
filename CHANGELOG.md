# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
## 0.3.3 – 2020-02-22
### Added
- occ export-project command
[#69](https://gitlab.com/eneiluj/cospend-nc/issues/69) @schwerpunkt
- bank transfer payment mode
- circle share
[#31](https://gitlab.com/eneiluj/cospend-nc/issues/31) @sunjam1
- show filtered balance in stats (if different from general balance)
[#58](https://gitlab.com/eneiluj/cospend-nc/issues/58) @archit3kt

### Changed
- project screenshots
- improve error messages
- upgrade tools used in CI

### Fixed
- remove minimum value for 'amount' field in bill edition
[#72](https://gitlab.com/eneiluj/cospend-nc/issues/72) @schwerpunkt
- avoid mess when changing displayed bill during saving request
[#73](https://gitlab.com/eneiluj/cospend-nc/issues/73) @schwerpunkt

## 0.3.2 – 2020-01-23
### Added
- project currencies management and conversion
[#36](https://gitlab.com/eneiluj/cospend-nc/issues/36)
[#46](https://gitlab.com/eneiluj/cospend-nc/issues/46) @archit3kt @Allirion @deepbluev7 @puerki
- custom categories
[#65](https://gitlab.com/eneiluj/cospend-nc/issues/65) @Helloha

### Changed
- show avatars where it's possible
- improve spent value display in bill edition form
[#68](https://gitlab.com/eneiluj/cospend-nc/issues/68) @jaroslaw.gerin
- improve weight value display in member list item
[#68](https://gitlab.com/eneiluj/cospend-nc/issues/68) @jaroslaw.gerin

### Fixed
- use proper templates for public pages (guest access)
- utf8 characters in avatar
[#67](https://gitlab.com/eneiluj/cospend-nc/issues/67) @jaroslaw.gerin

## 0.3.0 – 2020-01-08
### Added
- option to change output directory
[#57](https://gitlab.com/eneiluj/cospend-nc/issues/57) @xsus95
- permissions for guest access and user/group shares
[#34](https://gitlab.com/eneiluj/cospend-nc/issues/34) @yward
- option to include all active members when repeating a bill
[#53](https://gitlab.com/eneiluj/cospend-nc/issues/53) @quizilkend
- new REST API which requires login
- able to import projects files exported from splitwise
[!152](https://gitlab.com/eneiluj/cospend-nc/merge_requests/152) @denics
- new 'reimbursement' category that can be used to filter stats
[#24](https://gitlab.com/eneiluj/cospend-nc/issues/24) @mr-manuel
- able to set a max repetition date
[#29](https://gitlab.com/eneiluj/cospend-nc/issues/29) @eldiep
- monthly stats
[#23](https://gitlab.com/eneiluj/cospend-nc/issues/23) @mr-manuel
- pie and polar area charts in stats
- now possible to edit member color
- show amount owed by each member in bill form

### Changed
- design improvements
- improve category list
[#58](https://gitlab.com/eneiluj/cospend-nc/issues/58) @archit3kt
- improve disabled design
- make all tables sortable
- now able to import/export all bill/members values
- move 'create bill(s)' button

### Fixed
- don't include disabled members when repeating
[#53](https://gitlab.com/eneiluj/cospend-nc/issues/53) @quizilkend
- always get global user balance in stats even with filters
- import/export csv
[#53](https://gitlab.com/eneiluj/cospend-nc/issues/53) @quizilkend
- share icon was hidden by some adblockers
[#53](https://gitlab.com/eneiluj/cospend-nc/issues/53) @quizilkend
- huge bug in bill repetition date condition

## 0.2.0 – 2019-12-16
### Added
- support activity stream for add/del/edit/repeat bill and share/unshare project
- new occ command: cospend:repeat-bills to manually trigger repeat system
- new api route for getBills with more information (to help client to perform partial sync)

### Changed
- refactor controllers code
- use repeat/category/payment mode when exporting/importing

### Fixed
- fix repeat system for 31th
[#49](https://gitlab.com/eneiluj/cospend-nc/issues/49) @PL5bTStMZLduri
[!158](https://gitlab.com/eneiluj/cospend-nc/merge_requests/158) @PL5bTStMZLduri
- fix repeat system if it wasn't triggered during several days
[#49](https://gitlab.com/eneiluj/cospend-nc/issues/49) @eneiluj
- fix some strings and design mistakes
- bug when NC color code is compact

## 0.1.5 – 2019-10-13
### Added
- some categories

## 0.1.4 – 2019-09-14
### Added
- show total payed in statistics
[#43](https://gitlab.com/eneiluj/cospend-nc/issues/43) @nerdoc
- project auto export
- payment modes
[#12](https://gitlab.com/eneiluj/cospend-nc/issues/12) @llucax
[#44](https://gitlab.com/eneiluj/cospend-nc/issues/44) @nerdoc
- bill categories
- statistics filters
[#12](https://gitlab.com/eneiluj/cospend-nc/issues/12) @llucax
[#44](https://gitlab.com/eneiluj/cospend-nc/issues/44) @nerdoc

### Changed
- color management now done by the server avatar service
- sort member list by lowercase name

### Fixed
- fix notification system for NC17

## 0.1.1 – 2019-07-25
### Added

### Changed
- improve settlement process (use https://framagit.org/almet/debts)
- adjust Notifications to NC 17
- compatible with NC >= 17

### Fixed
- make QRCode label more explicit

## 0.1.0 – 2019-05-04
### Added

### Changed
- use Migration DB system
[!81](https://gitlab.com/eneiluj/cospend-nc/merge_requests/81) @werner.schiller
- handle custom server port in links/QRCodes
[#32](https://gitlab.com/eneiluj/cospend-nc/issues/32) @derpeter1

### Fixed
- share autocomplete design
- concurrency problem when creating multiple bills simultaneously
[!111](https://gitlab.com/eneiluj/cospend-nc/merge_requests/111) @klonfish

## 0.0.10 – 2019-04-08
### Changed
- improved user/group sharing design

### Fixed
- avoid 0 weight
[#26](https://gitlab.com/eneiluj/cospend-nc/issues/26) @MoathZ

## 0.0.9 – 2019-04-04
### Changed
- make tests compatible with phpunit 8 (and use it in CI script)
- test with sqlite, mysql and postgresql
- keep validation button for new bill in normal mode
[#14](https://gitlab.com/eneiluj/cospend-nc/issues/14) @swestersund
- change opacity of member name/icon

### Fixed
- fix all/none buttons behaviour for 'personal part' bill
[#14](https://gitlab.com/eneiluj/cospend-nc/issues/14) @swestersund
- fix project selection behaviour (in menu), toggle != select
- fix float-related DB stuff (crashing with PostgreSQL)
- jshint warnings

## 0.0.8 – 2019-03-31
### Fixed
- stupid bug in some SQL queries (was invisible in SQLite...)
[#22](https://gitlab.com/eneiluj/cospend-nc/issues/22) @Questlog

## 0.0.7 – 2019-03-30
### Added
- don't put disabled users in share autocomplete
[#17](https://gitlab.com/eneiluj/cospend-nc/issues/17) @redplanet
- ability to share a project with a group
[#17](https://gitlab.com/eneiluj/cospend-nc/issues/17) @redplanet
- new bill type: even split with personal parts
[#14](https://gitlab.com/eneiluj/cospend-nc/issues/14) @swestersund
- controller tests

### Changed
- use NC DB methods instead of plain SQL
- change share button color when share input is displayed
- test with NC16beta2

### Fixed
- external project renaming field
- UI fix after delete bill error
- replace deprecated addAllowedChildSrcDomain

## 0.0.6 – 2019-03-09
### Added
- CI PhpUnit tests
- QRCode and https link to import project in MoneyBuster
- now able to add external projects (hosted in another Nextcloud instance)

### Changed
- design improvements: selected project bg color
- make password optional for new projects
[#13](https://gitlab.com/eneiluj/cospend-nc/issues/13) @MrCustomizer

### Fixed
- remove settle/stats button from settings

## 0.0.5 – 2019-02-28
### Added
- ability to add public link to NC files in bill name
[#4](https://gitlab.com/eneiluj/cospend-nc/issues/4) @poVoq
- import/export project as csv
[#6](https://gitlab.com/eneiluj/cospend-nc/issues/6) @eneiluj
- export project stats and settlement plan as csv
[#6](https://gitlab.com/eneiluj/cospend-nc/issues/6) @poVoq
- button to apply settlement by automatically adding corresponding bills
[#2](https://gitlab.com/eneiluj/cospend-nc/issues/2) @eneiluj
- option to periodically repeat a bill (day/week/month/year)
[#3](https://gitlab.com/eneiluj/cospend-nc/issues/3) @poVoq
- let user give custom amount per member for new bills => creates several bills
[#7](https://gitlab.com/eneiluj/cospend-nc/issues/7) @poVoq

### Changed
- make app description translatable

### Fixed
- slash is now forbidden in project ID
- add missing loading icons
- balance number display when close to 0
- avoid saving bill if values haven't changed
- SQL queries compat with PostgreSQL

## 0.0.3 – 2019-02-14
### Added
- loading icon everywhere
- display 'no bill' when necessary

### Changed
- UI improvements
- app name : payback -> cospend

### Fixed
- focus on fields when necessary
- remove modern js template string to make l10n.pl work correctly
- avoid one useless browser password saving

## 0.0.2 – 2019-02-07
### Added
- ability to share projects to NC users

## 0.0.1 – 2019-02-01
### Added
- the app

### Changed
- from nothing, it appeared

### Fixed
- fix the world with this app, no more, no less
