# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
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
