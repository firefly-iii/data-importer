# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## 1.2.1 - 2023-03-13

### Fixed
- [Issue 7214](https://github.com/firefly-iii/firefly-iii/issues/7214) Previous issue with account currency matching needed a Data Importer fix as well.

### Changed
- This release will only work with Firefly III v6.0.4

## 1.2.0 - 2023-03-13

### Fixed
- [Issue 7207](https://github.com/firefly-iii/firefly-iii/issues/7207) Missing key validation

### Changed
- This release will only work with Firefly III v6.0.3

## 1.1.0 - 2023-03-12

### Added
- Warnings when you use bad configuration values.
- `IMPORT_DIR_WHITELIST` is now `IMPORT_DIR_ALLOWLIST` 

### Changed
- Improve logging when reporting on possible duplicates.

## 1.0.2 - 2022-01-27

### Fixed
- Missing array key breaks Nordigen and Spectre imports.

## 1.0.1 - 2022-01-26

### Fixed
- Fixed missing options when importing CSV file.
- Remove `APP_KEY` generation since the data importer doesn't use one.

## 1.0.0 - 2022-01-23

- ⚠️ This release requires PHP 8.2.
- ⚠️ This release may create duplicate transactions. Don't import large batches!

### Added
- The data importer has a health checkpoint, thanks @davidschlachter!

### Changed
- ⚠️ Use Nordigen's internal transaction ID as the transaction ID.
- Switch to Mastodon in the readme.
- Fix the link to the PHP docs, thanks @sa7mon!
- Small fixes to the `.env.example` file, thanks @axelsimon!

### Fixed
- [Issue 6518](https://github.com/firefly-iii/firefly-iii/issues/6518) Issue with bad account listings
- Fix bad method call
- Make sure CSV import values are trimmed (prevents account names like `PAYPAL    `)

## 0.9.17 - 2021-10-24

⚠️ This release WILL create duplicate transactions. Don't import large batches!

### Added
- Better error handling.

### Fixed
- Fix [issue 6548](https://github.com/firefly-iii/firefly-iii/issues/6548)
- Fix [issue 6525](https://github.com/firefly-iii/firefly-iii/issues/6525)

## 0.9.16 - 2022-09-12

### Added
- Ability to recognize 'Charge' as a debit transaction.
- Expand error messages with URL.
- Log more errors.

### Fixed
- [Issue 6354](https://github.com/firefly-iii/firefly-iii/issues/6354) Path fix for subdirectory installations.
- [Issue 6377](https://github.com/firefly-iii/firefly-iii/issues/6377) Switch issue were transaction amounts were reversed (and thus also the types)
- [Issue 6412](https://github.com/firefly-iii/firefly-iii/issues/6412) Update for command line import.
- [Issue 6419](https://github.com/firefly-iii/firefly-iii/issues/6419) Time out and SSL check wasn't consistent.

### Security
- Update packages

## 0.9.15 - 2022-07-25

### Fixed
- [Issue 6259](https://github.com/firefly-iii/firefly-iii/issues/6259) Fixed an issue where deposits would not be given a source account.

## 0.9.14 - 2022-07-21

### Added
- Fallback to additional information field if description is empty, thanks @martinslota

### Fixed
- [Issue 6242](https://github.com/firefly-iii/firefly-iii/issues/6242) Bad string conversion when dates are empty.
- [Issue 6244](https://github.com/firefly-iii/firefly-iii/issues/6244) Rename field from 'uri' to 'url'.
- Fix missing field data check for spectre imports.

## 0.9.13 - 2022-07-19

### Fixed
- Fixed an issue where the importer would break when importing mapped transactions.

## 0.9.12 - 2022-07-16

### Fixed
- Make sure packages match PHP 8.0

## 0.9.11 - 2022-07-16

### Fixed
- Issue where config files with empty strings would break the date selection.
- [Issue 6146](https://github.com/firefly-iii/firefly-iii/issues/6146) Fix issue where missing Nordigen data would break the import.
- [Issue 6233](https://github.com/firefly-iii/firefly-iii/issues/6233) Fix issue where mapping an expense account to a revenue spot would break the import.
- Fix issue where the importer would not detect empty descriptions. Thanks @BerghsJelmer

## 0.9.10 - 2022-05-03

### Changed
- Require the most recent version of Firefly III

## 0.9.9 - 2022-05-03

### Changed
- Made the texts more generic to accommodate future changes
- Catch deleted transactions
- Require most recent Firefly III

### Fixed
- [Issue 5980](https://github.com/firefly-iii/firefly-iii/issues/5980) Fix date range.

## 0.9.8 - 2022-04-13

### Fixed
- Fixes an issue with an underlying package.

## 0.9.7 - 2022-04-11

### Fixed
- Fixes an issue with an underlying package.

## 0.9.6 - 2022-04-11

### Fixed
- Fixes an issue with an underlying package.

## 0.9.5 - 2022-04-10

### Fixed
- Fixes an issue with an underlying package.

## 0.9.4 - 2022-04-10

### Fixed
- Fixes another issue with detecting duplicate transactions.

## 0.9.3 - 2022-04-05

### Fixed

- A small change should make it easier for the data importer to detect failed transaction creation attempts.
- [Issue 5964](https://github.com/firefly-iii/firefly-iii/issues/5964) Updated libraries to fix issue.

## 0.9.2 - 2022-04-02

### Fixed
- Removed some overly chatty status messages.

## 0.9.1 - 2022-04-02

### Added
- There is an option to set the maximum days to import from Nordigen, thanks @krehl!
- Importer will now report expired Nordigen User Agreements, thanks @MacPaille!

### Fixed
- [Issue 5871](https://github.com/firefly-iii/firefly-iii/issues/5871) Importer would ignore time-outs

## 0.9.0 - 2022-02-22

- ⚠️ This release WILL create duplicate transactions. Don't import large batches!

### Added
- Add command to upgrade import configurations.

### Changed
- Stop logging stack traces to prevent logs from filling up.
- ⚠️ Don't submit unused `amount_modifier` field

### Fixed
- Better error catching in configuration controller and other places.
- Sanity check to prevent the importer from treating JSON files as CSV files.
- [Issue 5731](https://github.com/firefly-iii/firefly-iii/issues/5731) Could not import transfers from Spectre
- [Issue 5735](https://github.com/firefly-iii/firefly-iii/issues/5735) Better support for old import configuration files

## 0.8.0 - 2022-02-07

- ⚠️ Using Nordigen? This release WILL create duplicate transactions. Don't import large batches.

### Added
- Collect more Nordigen information for future debug.
- Collect IBAN from secondary source if possible.
- Add value date as "payment date" whenever possible.

### Fixed
- [Issue 5590](https://github.com/firefly-iii/firefly-iii/issues/5590) 500 error when attempting to add bank account from Nordigen
- [Issue 5650](https://github.com/firefly-iii/firefly-iii/issues/5650) Better error catching for timeouts
- [Issue 5700](https://github.com/firefly-iii/firefly-iii/issues/5700) Missing route for Nordigen (experimental fix)
- Clean up some logging.
- Better check on session states
- Better check on messages to report
- Will not email if not configured to

## 0.7.0 - 2022-01-22

- ⚠️ Using Nordigen? This release WILL create duplicate transactions. Don't import large batches.

A special thanks to the excellent folks over at @nordigen for some quick debugging and fixing.

### Added
- A special 500 page so you can see what's happening.
- Nordigen will now import transaction details in notes.
- If you have no Firefly III accounts, the importer will warn you.
- Extra account details debug information.

### Fixed
- A missing method broke navigation.
- Sometimes session details would get lost in translation.

## 0.6.5 - 2022-01-15

### Fixed

- Catch error in `balanceAfterTransaction` field.

## 0.6.4 - 2022-01-15

### Fixed

- `ownerAddressUnstructured` could unexpectedly be unset.

## 0.6.3 - 2022-01-12

### Fixed

- Nordigen reports the `ownerAddressUnstructured` both as string and array, thanks @dawid-czarnecki

## 0.6.2 - 2022-01-12

### Fixed

- [Issue 5507](https://github.com/firefly-iii/firefly-iii/issues/5507) `ownerAddressUnstructured` field is of the wrong data type.
- Version 0.6.1 also reports as 0.6.0.

## 0.6.1 - 2022-01-08

### Fixed

- Missing continue statement in transaction processor

## 0.6.0 - 2022-01-08

- ⚠️ Duplicate detection could be broken, due to changes in the way transactions are handled. Be careful importing large batches.
- 💡 Some people have reported running into loops when trying to start importing CSV files. Please [open an issue](https://github.com/firefly-iii/firefly-iii/issues) if this happens to you.

### Added
- The Spectre import checks more fields for payee information, thanks @ddelbondio!

### Changed
- The importer can stop warning you about duplicate transactions, making for a cleaner import. Check out `.env.example`.
- If there is nothing to report, there will be no email message.
- The import tag will not be created until the first transaction is successfully imported.
- The configuration file export will mention the data importer version.

### Fixed
- Various issues where people would end up in a redirect loop.
- In some cases, the "mapping" feature was hidden.
- The debug page could add newlines, breaking the table.
- The autoupload endpoint would require CSV files, even when using Nordigen or Spectre.
- [Issue 5502](https://github.com/firefly-iii/firefly-iii/issues/5502) Sloppy copy/paste bug leads to confusion between the base URL and the vanity URL.

## 0.5.0 - 2022-01-01

- ⚠️ Duplicate detection could be broken, due to changes in the way transactions are handled. Be careful importing large batches.
- 💡 Some people have reported running into loops when trying to start importing CSV files. Please [open an issue](https://github.com/firefly-iii/firefly-iii/issues) if this happens to you.

### Added
- Code to support the [cloud installation](https://docs.firefly-iii.org/data-importer/install/public/).
- Proper page for maintenance mode.

### Changed
- [Issue 5453](https://github.com/firefly-iii/firefly-iii/issues/5453) Different text for button
- Importer will complain about bad environment variables.
- Only create the import tag when necessary.

### Fixed
- [Issue 5354](https://github.com/firefly-iii/firefly-iii/issues/5354) Fix edge case when importing CSV files.
- [Issue 5440](https://github.com/firefly-iii/firefly-iii/issues/5440) Can now handle amounts formatted `0,xxxxx`
- [Issue 5452](https://github.com/firefly-iii/firefly-iii/issues/5452) Bad vanity URL in reports
- [Issue 5459](https://github.com/firefly-iii/firefly-iii/issues/5459) Fix issue when skipping configuration page.
- Filter spaces from IBANs

## 0.4.1 - 2021-12-23

- ⚠️ Duplicate detection could be broken, due to changes in the way transactions are handled. Be careful importing large batches.
- 💡 Some people have reported running into loops when trying to start importing CSV files. Please [open an issue](https://github.com/firefly-iii/firefly-iii/issues) if this happens to you.

### Added
- Dark mode. Responds to your browser or OS.

### Fixed
- [Issue 5416](https://github.com/firefly-iii/firefly-iii/issues/5416) Mismatch in function name breaks Nordigen.

## 0.4.0 - 2021-12-22

### Known issues

- ⚠️ Duplicate detection could be broken, due to changes in the way transactions are handled. Be careful importing large batches.
- 💡 Some people have reported running into loops when trying to start importing CSV files. Please [open an issue](https://github.com/firefly-iii/firefly-iii/issues) if this happens to you.

### Changed
- The importer will search for, and match account numbers as well as IBANs.
- The importer will prefer 'bookDate' over 'valueDate'

### Added
- POST import and import via upload is now possible. Several mandatory security measures are listed in `.env.example`.

### Fixed
- [Issue 5397](https://github.com/firefly-iii/firefly-iii/issues/5397) Could not import into liabilities.
- [Issue 23](https://github.com/firefly-iii/data-importer/pull/23), fixed date range settings, thanks @p-rintz
- [Issue 5407](https://github.com/firefly-iii/firefly-iii/issues/5407) Fix issue with empty accountlists.

## 0.3.0 - 2021-12-11

### Added
- [Issue 5342](https://github.com/firefly-iii/firefly-iii/issues/5342) Can import "external URL" field.
- Will now send report messages over mail if you want.

### Fixed
- Remove TODO's from README file.
- Respect date range when importing.
- Nordigen will pre-select country + bank
- Nordigen will recycle requisition
- Mapping works for Spectre + Nordigen
- Better secret management for Nordigen + Spectre
- [Issue 5351](https://github.com/firefly-iii/firefly-iii/issues/5351) Fix URL's
- Fix duplicate detection.
- Fix issue with transfers being imported as deposits.
- New debit indicator thanks to @reeperbahnause

## 0.2.0 - 2021-12-04

### Added
- There is a `/debug` page if you need it.
- Auto-import works over the command line.
- Importing works over the command line.
- Full documentation in place.

### Changed
- More consistent layout

### Fixed
- [Issue 5336](https://github.com/firefly-iii/firefly-iii/issues/5336) Undefined array key "Liabilities
- [Issue 5337](https://github.com/firefly-iii/firefly-iii/issues/5337) Config download fails
- [Issue 5338](https://github.com/firefly-iii/firefly-iii/issues/5338) APISubmitter not found in RoutineManager
- [Issue 5339](https://github.com/firefly-iii/firefly-iii/issues/5339) fails to read CSV delimiter correctly
- [Issue 5343](https://github.com/firefly-iii/firefly-iii/issues/5343) Missing config variable
- [Issue 5344](https://github.com/firefly-iii/firefly-iii/issues/5344) Protocol mismatch
- [Issue 5345](https://github.com/firefly-iii/firefly-iii/issues/5345) Some transactions are said to have the same source and destination

## 0.1.0 - 2021-11-27

First release of the data importer.

## 0.0.0 - 2021-xx-xx

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
