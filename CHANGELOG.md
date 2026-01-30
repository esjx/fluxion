# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com)
and this project adheres to [Semantic Versioning](https://semver.org). Thia is always true of the master branch. Some earlier branches remain supported and security fixes are applied to them; if the security fix represents a breaking change, it may have to be applied as a minor or patch version.

## TBD - 5.5.0

### Added

- Nothing yet.

### Removed

- Nothing yet.

### Changed

- Nothing yet.

### Moved

- Nothing yet.

### Deprecated

- Nothing yet.

### Fixed

- Nothing yet.

## 2026-01-10 - 5.4.0

### Added

- Store image in cell (Xlsx only). [Issue #4014](https://github.com/PHPOffice/PhpSpreadsheet/issues/4014) [Issue #4034](https://github.com/PHPOffice/PhpSpreadsheet/issues/4034) [Issue #913](https://github.com/PHPOffice/PhpSpreadsheet/issues/913) [PR #4677](https://github.com/PHPOffice/PhpSpreadsheet/pull/4677)
- Passthrough support (with some restrictions) for otherwise unsupported drawing elements. [Issue #4037](https://github.com/PHPOffice/PhpSpreadsheet/issues/4037) [Issue #4704](https://github.com/PHPOffice/PhpSpreadsheet/issues/4704) [PR #4712](https://github.com/PHPOffice/PhpSpreadsheet/pull/4712)
- Set all locale variables at once in a threadsafe manner. [Issue #954](https://github.com/PHPOffice/PhpSpreadsheet/issues/954) [PR #4760](https://github.com/PHPOffice/PhpSpreadsheet/pull/4760)
- Reader/Html add ability to suppress warning messages from loadhtml. [Issue #647](https://github.com/PHPOffice/PhpSpreadsheet/issues/647) [Issue #849](https://github.com/PHPOffice/PhpSpreadsheet/issues/849) [PR #4761](https://github.com/PHPOffice/PhpSpreadsheet/pull/4761)

### Changed

- Evaluation of WEBSERVICE no longer requires external client, but will use oldCalculatedValue unless the request is for a domain in a user-supplied whitelist. [PR #4751](https://github.com/PHPOffice/PhpSpreadsheet/pull/4751)

### Moved

- Code to merge cell base style with table and conditional styles moved from Html Writer to its own class. [Issue #1058](https://github.com/PHPOffice/PhpSpreadsheet/issues/1058) [PR #4763](https://github.com/PHPOffice/PhpSpreadsheet/pull/4763)

### Deprecated

- Settings methods setHttpClient, unsetHttpClient, getHttpClient, and getRequestFactory are no longer used. No replacement.
- Reader/Html protected property dataArray, described as used only for testing, is not used for testing. No replacement.

### Fixed

- Slightly better support for escaped characters in Xlsx Reader/Writer. [Discussion #4724](https://github.com/PHPOffice/PhpSpreadsheet/discussions/4724) [PR #4726](https://github.com/PHPOffice/PhpSpreadsheet/pull/4726)
- CODE/UNICODE and CHAR/UNICHAR. [PR #4727](https://github.com/PHPOffice/PhpSpreadsheet/pull/4727)
- Minor changes to TextGrid. [PR #4735](https://github.com/PHPOffice/PhpSpreadsheet/pull/4735) [PR #4743](https://github.com/PHPOffice/PhpSpreadsheet/pull/4743)
- Single-character table names. [Issue #4739](https://github.com/PHPOffice/PhpSpreadsheet/issues/4739) [PR #4740](https://github.com/PHPOffice/PhpSpreadsheet/pull/4740)
- Improvements to SORT and SORTBY. [PR #4743](https://github.com/PHPOffice/PhpSpreadsheet/pull/4743)
- Coverage-related changes in Shared. [PR #4745](https://github.com/PHPOffice/PhpSpreadsheet/pull/4745)
- ListWorksheetInfo improvements for Xlsx and Ods. [Issue #3255](https://github.com/PHPOffice/PhpSpreadsheet/issues/3255) [PR #4746](https://github.com/PHPOffice/PhpSpreadsheet/pull/4746)
- Fix functions related to Student-T distribution. [Issue #4167](https://github.com/PHPOffice/PhpSpreadsheet/issues/4167) [PR #4748](https://github.com/PHPOffice/PhpSpreadsheet/pull/4748)
- Fix drawing hyperlinks. [Issue #993](https://github.com/PHPOffice/PhpSpreadsheet/issues/993) [PR #4764](https://github.com/PHPOffice/PhpSpreadsheet/pull/4764)
- Fix clone spreadsheet with defined names. [PR #4753](https://github.com/PHPOffice/PhpSpreadsheet/pull/4753)
- Changes to WEBSERVICE. [PR #4751](https://github.com/PHPOffice/PhpSpreadsheet/pull/4751)
- More consistent handling of unsupported functions. [Issue #606](https://github.com/PHPOffice/PhpSpreadsheet/issues/606) [PR #4772](https://github.com/PHPOffice/PhpSpreadsheet/pull/4772)
- Chart shadow `kx` and `ky`. [PR #4770](https://github.com/PHPOffice/PhpSpreadsheet/pull/4770)
- SUBTOTAL and hidden rows. [Issue #820](https://github.com/PHPOffice/PhpSpreadsheet/issues/820) [PR #4765](https://github.com/PHPOffice/PhpSpreadsheet/pull/4765)
- Fix some hyperlink problems. [Issue #3889](https://github.com/PHPOffice/PhpSpreadsheet/issues/3889) [Issue #2464](https://github.com/PHPOffice/PhpSpreadsheet/issues/2464) [PR #4771](https://github.com/PHPOffice/PhpSpreadsheet/pull/4771)
- Xls Writer and empty RichText. [Issue #918](https://github.com/PHPOffice/PhpSpreadsheet/issues/918) [PR #4769](https://github.com/PHPOffice/PhpSpreadsheet/pull/4769)
- Strings that look like huge floating-point numbers. [Issue #4766](https://github.com/PHPOffice/PhpSpreadsheet/issues/4766) [PR #4768](https://github.com/PHPOffice/PhpSpreadsheet/pull/4768)
- Rowspan in Html. [Issue #1319](https://github.com/PHPOffice/PhpSpreadsheet/issues/1319) [PR #4767](https://github.com/PHPOffice/PhpSpreadsheet/pull/4767)
- Mpdf styling of multi-line strings. [Issue #4773](https://github.com/PHPOffice/PhpSpreadsheet/issues/4773) [PR #4775](https://github.com/PHPOffice/PhpSpreadsheet/pull/4775)
