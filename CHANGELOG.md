# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

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
