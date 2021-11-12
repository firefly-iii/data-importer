# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## 2.7.0 - 2021-10-10

### Deprecated
- The CSV importer requires Firefly III 5.6.0 or higher.

### Fixed
- [Issue 5047](https://github.com/firefly-iii/firefly-iii/issues/5047) When importing using large configuration files, the importer would only save the top ~400 mapping entries.

## 2.6.1 - 2020-09-04

### Changed
- The mapper now sorts all dropdowns by alphabet.

### Security
- Updated JS and PHP packages.

## 2.6.0 - 2020-07-23

### Changed
- The CSV importer now requires PHP 8.0

## 2.5.4 - 2020-07-21

### Security

- Updated JS and PHP packages.

## 2.5.3 - 2020-06-07

### Fixed
- [Issue 4840](https://github.com/firefly-iii/firefly-iii/issues/4840) Corrected error catching in IBAN check

## 2.5.2 - 2020-05-23

### Fixed
- [Issue 4714](https://github.com/firefly-iii/firefly-iii/issues/4714) Bad count in line numbers.
- [PR 147](https://github.com/firefly-iii/csv-importer/pull/147) Vanity URL in messages.

### Changed
- Some textual changes.
- [PR 146](https://github.com/firefly-iii/csv-importer/pull/146) iDeal transactions in ING specific.

### Security
- Lots of updated packages.


## 2.5.1 - 2021-04-11

### Added
- Expand the readme with instructions and new logo. Thanks, @ColinMaudry!

### Fixed
- Several cases where URL's could be empty.
- CSV importer will start counting from 1, not from 0 when showing messages. Thanks, @lastlink!

## 2.5.0 - 2021-02-06

### Added
- [Issue 3788](https://github.com/firefly-iii/firefly-iii/issues/3788) It's now possible to change the way the CSV importer detects duplicates. Please read [the documentation](https://docs.firefly-iii.org/csv/usage/configure/).

### Fixed

- [Issue 4333](https://github.com/firefly-iii/firefly-iii/issues/4333) Bad coding on my part mixed up the vanity URL and the normal URL.

## 2.4.0 - 2021-01-29

### Added
- [Issue 4297](https://github.com/firefly-iii/firefly-iii/issues/4297) You can now POST upload new imports. See [the documentation](https://docs.firefly-iii.org/csv/usage/post/) for more info.

### Fixed
- [Issue 4298](https://github.com/firefly-iii/firefly-iii/issues/4298) Tags would not append, only overwrite when using multiple tag columns.
- [Issue 4293](https://github.com/firefly-iii/firefly-iii/issues/4293) In some cases, importing transactions with new IBAN's twice would create duplicate transactions.

## 2.3.4 - 2021-01-20

### Fixed
- Fix issue with uninitialized variables.
- Fix issue found in https://github.com/YunoHost-Apps/firefly-iii/issues/29

### Changed

- Update Laravel and other libraries.
- New Docker build!

## 2.3.3 - 2021-01-03

### Fixed

- [Issue 4215](https://github.com/firefly-iii/firefly-iii/issues/4215) Sloppy programming on my side broke the CSV importer, my apologies.

## 2.3.2 - 2021-01-02

### Added
- [Issue 4134](https://github.com/firefly-iii/firefly-iii/issues/4134) CSV importer will also send emails when running on command line.

### Fixed
- [Issue 4106](https://github.com/firefly-iii/firefly-iii/issues/4106) Make sure amounts like "EUR 1.234,56" can be parsed.
- [Issue 4183](https://github.com/firefly-iii/firefly-iii/issues/4183) Make sure all calls use the verify settings, thx @zjean.

### Security
- Lots of library updates as usual.

## 2.3.1 - 2020-11-20

### Fixed

- [Issue 4100](https://github.com/firefly-iii/firefly-iii/issues/4100) Bad parameters in token call.


## 2.3.0 - 2020-11-29

⚠️ Several changes in this release may break Firefly III's duplication detection or are backwards incompatible.

### Changed

- ⚠️ All environment variables that used to be called "URI" are now called "URL" because I finally learned the difference between a URL and a URI.

### Fixed

- [Issue 4094](https://github.com/firefly-iii/firefly-iii/issues/4094) CSV importer would only files with a lowercase `.csv` extension.

## 2.2.4 - 2020-11-20

### Fixed

- [Issue 3975](https://github.com/firefly-iii/firefly-iii/issues/3975) Better error handling when Firefly III responds with invalid JSON.
- [Issue 4069](https://github.com/firefly-iii/firefly-iii/issues/4069) CSV importer would contact the wrong URL to get an access token if you configure a vanity URL.

## 2.2.3 - 2020-11-05

### Fixed
- [Issue 4209](https://github.com/firefly-iii/firefly-iii/issues/4209) Bad error parsing wasn't really user friendly.

## 2.2.2 - 2020-10-23

### Fixed
- An informational message was shown twice.
- Bad JSON wasn't intercepted, leading to weird errors.

## 2.2.1 - 2020-10-04

### Added
- New `/autoimport` route you can POST to.

### Fixed
- A bug where JSON errors would lead to errors in the CSV importer.

## 2.2.0 - 2020-09-21

⚠️ Several changes in this release may break Firefly III's duplication detection or are backwards incompatible.

### Added
- Support for public clients. Check out the docs.
- Created a [public installation](https://docs.firefly-iii.org/csv/help/public/).

### Changed
- ⚠️ This version of the CSV importer requires PHP 7.4.
- ⚠️ The import commands now start with `importer:` instead of `csv:`

### Fixed
- ABN AMRO specific was broken.

## 2.1.1 - 2020-09-05

### Added
- Can now parse locale dates, see the [documentation](https://docs.firefly-iii.org/csv/install/configure/)

### Fixed
- [Issue 3706](https://github.com/firefly-iii/firefly-iii/issues/3706) Bug in amount parsing.
- [Issue 3767](https://github.com/firefly-iii/firefly-iii/issues/3767) Date parsing was broken.

## 2.1.0 - 2020-09-05

### Added
- Can now parse locale dates, see the [documentation](https://docs.firefly-iii.org/csv/install/configure/)

### Fixed
- [Issue 3706](https://github.com/firefly-iii/firefly-iii/issues/3706) Bug in amount parsing.

## 2.0.5 - 2020-08-10

### Fixed

- Longer standard timeout for slow installations.

## 2.0.4 - 2020-08-10

### Fixed

- Nullpointer in support class.

## 2.0.3 - 2020-08-09

### Fixed

- Bad exception call in support class.

## 2.0.2 - 2020-08-09

### Added
- Reset button

### Fixed
- [Issue 3644](https://github.com/firefly-iii/firefly-iii/issues/3644) Bank specific options were ignored.
- [Issue 3676](https://github.com/firefly-iii/firefly-iii/issues/3676) Better error handling.

## 2.0.1 - 2020-08-01

### Changed
- Now supports the "S", when using the generic bank debit/credit selector (German banks use it)

## 2.0.0 - 2020-07-10

### Changed
- Now requires PHP 7.4. Make sure you update!
- Can now use a vanity URL. See the example environment variables file, `.env.example` for instructions.
- This version requires Firefly III v5.3.0

## 1.0.15 - 2020-07-05

⚠️ Several changes in this release may break Firefly III's duplication detection. Be careful importing large batches.

### Fixed
- ⚠️ The importer will no longer match account names like `online` to accounts like `online account`. If you were relying on this behavior, please use the
 "mapping" function instead.
- The "mapping" page would always show you all mappable fields, even when you only selected one field to map.

## 1.0.14 - 2020-06-30

Fixes [issue 3501](https://github.com/firefly-iii/firefly-iii/issues/3501).

## 1.0.12 - 2020-06-19

Now liabilities can be selected as the default account.

## 1.0.11 - 2020-06-16

Some changes in the ING (Belgium) parser.

## 1.0.10 - 2020-06-04

⚠️ Several changes in this release may break Firefly III's duplication detection. Be careful importing large batches.

### Added

You can now set the timezone using the `TZ` environment variable.

### Changed
- Improved the error message when you forget to upload stuff.
- All documentation will point to the `latest` branch for more consistency.
- Some date values were not imported properly.

### Fixed
- ⚠️ Several edge cases exist where the CSV importer and Firefly III disagree on which account to use. This can result in errors like "*Could not find a
 valid source account when searching for ...*." I have introduced several fixes to mitigate this issue. These fixes will most definitively change the
  way transactions are handled, so be careful importing large batches.
- IBAN in lower case or spaces works now. 

## 1.0.9 - 2020-05-19

### Fixed
- Fixed error message about "root directory" because the CSV importer submitted an empty string.

### Changed
- CSV importer requires the latest version of Firefly III.


## 1.0.8 - 2020-05-14

⚠️ Several changes in this release may break Firefly III's duplication detection. Be careful importing large batches.

### Added
- The import tag now has a date as well.
- ⚠️ [issue 3346](https://github.com/firefly-iii/firefly-iii/issues/3346) If your file has them, you can import the timestamp with the transaction.
- You can store your import configurations under `storage/configurations` for easy access during the import.
- The UI would not respect specifics in your JSON config.

### Fixed
- If the API response was bad, the importer would crash. No longer.
- [Issue 3345](https://github.com/firefly-iii/firefly-iii/issues/3345) Would ignore the delimiter in some cases.

## 1.0.7 - 2020-05-04

⚠️ Several changes in this release may break Firefly III's duplication detection. Be careful importing large batches.

### Added
- ⚠️ Reimplement the search for IBANs and names. This makes it easier to import using incomplete data. This changes the importer's behavior.
- CSV import can add a tag to your import.

### Fixed
- [Issue 3290](https://github.com/firefly-iii/firefly-iii/issues/3290) Issues with refunds from credit cards.
- [Issue 3299](https://github.com/firefly-iii/firefly-iii/issues/3299) Issue with bcmod.
- Merge [fix](https://github.com/firefly-iii/csv-importer/pull/5) for mail config.
- Catch JSON errors, so the importer handles invalid UTF8 data properly. 

## 1.0.6 - 2020-04-26

⚠️ Several changes in this release may break Firefly III's duplication detection. Be careful importing large batches.

### Added
- You can now navigate back and forth between steps.
- You can configure the importer to send email reports. Checkout `.env.example`.

### Changed
- ⚠️ When the destination of a withdrawal is empty, *or* the source of a deposit is empty, the CSV importer will substitute these values with `(no name)` as
 it used to do when the CSV importer was part of Firefly III itself.

## 1.0.5 - 2020-04-22

### Fixed
- [Issue 3268](https://github.com/firefly-iii/firefly-iii/issues/3268) Issue with asset management.
- [Issue 3271](https://github.com/firefly-iii/firefly-iii/issues/3271) Bad handing of debit/credit columns.
- [Issue 3279](https://github.com/firefly-iii/firefly-iii/issues/3279) Issue handling JSON.


## 1.0.4 - 2020-04-16

- [Issue 3266](https://github.com/firefly-iii/firefly-iii/issues/3266) Import loop due to bad bccomp call.
- Some code cleanup.

## 1.0.3 - 2020-04-13

- Fix issue with account selection.
- Fix issue with amounts.

## 1.0.2 - 2020-04-12

### Added
- Add ability to handle `TRUSTED_PROXIES` environment variable.

### Fixed
- [Issue 3253](https://github.com/firefly-iii/firefly-iii/issues/3253) Could not map values if the delimiter wasn't a comma.
- [Issue 3254](https://github.com/firefly-iii/firefly-iii/issues/3254) Better handling of strings.
- [Issue 3258](https://github.com/firefly-iii/firefly-iii/issues/3258) Better handling of existing accounts.
- Better error handling (500 errors will not make the importer loop).
- Fixed handling of specifics, thanks to @FelikZ

## 1.0.1 - 2020-04-10

### Fixed
- Call to `convertBoolean` with bad parameters.
- Catch exception where Firefly III returns the wrong account.
- Update minimum version for Firefly III to 5.2.0.

## 1.0.0 - 2020-04-10

This release was preceded by several alpha and beta versions:

- 1.0.0-alpha.1 on 2019-10-31
- 1.0.0-alpha.2 on 2020-01-03
- 1.0.0-alpha.3 on 2020-01-11
- 1.0.0-beta.1 on 2020-02-23
- 1.0.0-beta.2 on 2020-03-13
- 1.0.0-beta.3 on 2020-04-08

### Added
- Initial release.

### Changed
- Initial release.

### Deprecated
- Initial release.

### Removed
- Initial release.

### Fixed
- Initial release.

### Security
- Initial release.
