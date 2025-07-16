# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## v1.7.6 - 2025-07-15

>  ⚠️ Some changes in this release may unexpectedly lead to duplicate transactions. This is caused by changes in the data handling routines. This is unfortunate, but a result of new insights, changed APIs and other minor fixes. My apologies for any inconvenience. I try to avoid these kinds of changes, but it can't always be helped.

### Added
- Log entries will occasionally include the data importer version, so that you can see which version was used to import data.
- [Issue 8296](https://github.com/firefly-iii/firefly-iii/issues/8296) (GoCardless Revolute currency exchange) reported by @TomVarga

### Changed
- [Issue 10556](https://github.com/firefly-iii/firefly-iii/issues/10556) (Save the connection string for subsequent SimpleFIN imports) reported by @Gsyltc

### Fixed
- [Issue 10590](https://github.com/firefly-iii/firefly-iii/issues/10590) ([Importer] SimpleFIN Bridge fails on zero transaction imports) reported by @wyomatthew
- [Issue 10556](https://github.com/firefly-iii/firefly-iii/issues/10556) (Save the connection string for subsequent SimpleFIN imports) reported by @Gsyltc
- [Issue 10580](https://github.com/firefly-iii/firefly-iii/issues/10580) ([Importer] GoCardless account matching UI is broken in dark mode) reported by @avee87
- [PR 882](https://github.com/firefly-iii/data-importer/pull/882) (This PR fixes issue 8296) by @TomVarga

## v1.7.5 - 2025-07-06

### Fixed
- Improved duplicate detection for SimpleFIN imports.

## v1.7.4 - 2025-07-06

### Fixed
- [Issue 10550](https://github.com/firefly-iii/firefly-iii/issues/10550) ([Importer] Issues with SimpleFIN Import) reported by @PacketThrowr
- [Issue 10556](https://github.com/firefly-iii/firefly-iii/issues/10556) (Save the connection string for subsequent SimpleFIN imports) reported by @Gsyltc
- [Issue 10541](https://github.com/firefly-iii/firefly-iii/issues/10541) (Type error in str_replace function, breaks GoCardless import during configuration) reported by @alexmaisel

## v1.7.3 - 2025-07-04

### Fixed
- Restore duplicate detection options
- SimpleFIN import will not always try to create accounts

## v1.7.2 - 2025-07-03

### Fixed
- [Issue 10541](https://github.com/firefly-iii/firefly-iii/issues/10541) (Type error in str_replace function, breaks GoCardless import during configuration) reported by @alexmaisel

## v1.7.1 - 2025-07-02

>  ⚠️ Some changes in this release may unexpectedly lead to duplicate transactions. This is caused by changes in the data handling routines. This is unfortunate, but a result of new insights, changed APIs and other minor fixes. My apologies for any inconvenience. I try to avoid these kinds of changes, but it can't always be helped.

### Changed
- For GoCardless imports: opposing account names and other reference data is no longer added as tags, but as text in the notes. This may lead to duplicates.
- GoCardless: better handling of rate limits.

### Fixed
- [Issue 10508](https://github.com/firefly-iii/firefly-iii/issues/10508) (Firefly Version 1.7.0 uses SimpleFIN for GoCardless and crashes when importing) reported by @L3tum
- Improved parsing of dates.

## v1.7.0 - 2025-06-26

### Added
- Support for SimpleFIN, developed by user 'skell'

### Fixed
- Better check on Authorization header for users who use Basic Auth.
- [Issue 5396](https://github.com/firefly-iii/firefly-iii/issues/5396) (Data importer: SimpleFIN) reported by @marcusds, with special thanks again to skell, and all the beta testers who helped out!

## v1.6.3 - 2025-06-08

>  ⚠️ Some changes in this release may unexpectedly lead to duplicate transactions. This is caused by changes in the data handling routines. This is unfortunate, but a result of new insights, changed APIs and other minor fixes. My apologies for any inconvenience. I try to avoid these kinds of changes, but it can't always be helped.

### Added
- Add some more debug logging.
- [Issue 8994](https://github.com/firefly-iii/firefly-iii/issues/8994) (Creditor Reference Information) reported by @fschirinzi
- [Issue 9533](https://github.com/firefly-iii/firefly-iii/issues/9533) (Extension of GoCardess imports with tags from custom transaction fields) reported by @kondas

### Changed
- Data Importer generated transaction IDs are prefixed with `ff3-`.
- Account numbers have their spaces removed, for easier matching in Firefly III.

### Fixed
- [Discussion 10162](https://github.com/orgs/firefly-iii/discussions/10162) (Reverse proxy and `X-Forwarded-Prefix` header) started by @frenchu
- [Issue 10387](https://github.com/firefly-iii/firefly-iii/issues/10387) (Failing to import inverted transactions when there is no account name and the account number does not exist) reported by @oddstr13
- [PR 802](https://github.com/firefly-iii/data-importer/pull/802) Ensure all token acquisition methods are the same by @grgar
- [PR 803](https://github.com/firefly-iii/data-importer/pull/803) Ignore exception opening counter-main.txt by @grgar
- [PR 814](https://github.com/firefly-iii/data-importer/pull/814) Consider account number uniquely identifying by @oddstr13

## v1.6.2 - 2025-04-21

### Added
- [PR 782](https://github.com/firefly-iii/data-importer/pull/782) Auto import: Honor JSON_CONFIGURATION_DIR by @yparitcher

### Fixed

- [Issue 9980](https://github.com/firefly-iii/firefly-iii/issues/9980) (Firefly FIDI incorrect exit code when errors received) reported by @miguelangel-nubla
- [Issue 10099](https://github.com/firefly-iii/firefly-iii/issues/10099) (Data Importer loses Secrets from Variables after clicking "Start over") reported by @rrosenbauer

## 1.6.1 - 2025-02-19

> ⚠️⚠️ This release required **PHP 8.4.0** and will not work on earlier releases of PHP ⚠️⚠️

>  ⚠️ Some changes in this release may unexpectedly lead to duplicate transactions. This is caused by changes in the data handling routines. This is unfortunate, but a result of new insights, changed APIs and other minor fixes. My apologies for any inconvenience. I try to avoid these kinds of changes, but it can't always be helped.

### Fixed

- [Issue 9840](https://github.com/firefly-iii/firefly-iii/issues/9840) (Data Importer doesn't detect duplicates when using "notes" for the unique column) reported by @muhammad-saleh

## 1.6.0 - 2025-01-31

> ⚠️⚠️ This release required **PHP 8.4.0** and will not work on earlier releases of PHP ⚠️⚠️

>  ⚠️ Some changes in this release may unexpectedly lead to duplicate transactions. This is caused by changes in the data handling routines. This is unfortunate, but a result of new insights, changed APIs and other minor fixes. My apologies for any inconvenience. I try to avoid these kinds of changes, but it can't always be helped.

### Changed

Due to changes in the Docker image, the commands that use `WEB_SERVER=false` to automatically import data need another docker container. You can read more about it [in the documentation](https://docs.firefly-iii.org/how-to/data-importer/import/automated/).

- Better rate limit reporting for Nordigen
- Better exit codes
- Data is cached, should make the importer slightly faster

### Fixed 
- [Issue 9484](https://github.com/firefly-iii/firefly-iii/issues/9484) (Exit code 72 when no results are found - expecting 73) reported by @james-prince
- [Issue 9505](https://github.com/firefly-iii/firefly-iii/issues/9505) (data-importer develop failing on CSV file) reported by @MacPaille
- [Discussion 9513](https://github.com/orgs/firefly-iii/discussions/9513) (Importer Stuck on Configuration with no exception) started by @KasparRosin
- [Issue 9651](https://github.com/firefly-iii/firefly-iii/issues/9651) (Data importer fails to check configured self signed CA when checking for duplicate external IDs) reported by @zebrapurring
- [Issue 9688](https://github.com/firefly-iii/firefly-iii/issues/9688) ([Importer] Gocardless Import not using correct values for creditor) reported by @arnolicious

### Added
- Support for PHP 8.4
- [Discussion 9626](https://github.com/orgs/firefly-iii/discussions/9626) (Allow newlines in description/Add hook points to Data importer process) started by @Sobuno

### Removed
- Support for < PHP 8.4

## v1.5.7 - 2024-11-10

### Added
- Add debug information for rate limits from GoCardless
- [Pull request 686](https://github.com/firefly-iii/data-importer/pull/686) (Add DB as Debit indicator) by @akhy

### Changed
- [Issue 9272](https://github.com/firefly-iii/firefly-iii/issues/9272) (Update data-importer to reflect aquisition of Nordigen by Gocardless) reported by @gene1wood
- [Issue 9357](https://github.com/firefly-iii/firefly-iii/issues/9357) (Data Importer exits with error code 1 when no transactions are found) reported by @diogotcorreia

### Fixed
- Better error catching for rate limits from GoCardless
- Fix link to documentation 
- [Issue 9180](https://github.com/firefly-iii/firefly-iii/issues/9180) (Data importer - gocardless EUA is expired - print clear message instead of 500 server error) reported by @Ovenlade
- [Issue 9340](https://github.com/firefly-iii/firefly-iii/issues/9340) (Default transaction import without year to current year) reported by @mokkun
- [Issue 9347](https://github.com/firefly-iii/firefly-iii/issues/9347) (Importing transaction with huge external id throws error) reported by @diogotcorreia
- [Issue 9345](https://github.com/firefly-iii/firefly-iii/issues/9345) (data importer - error lines don't match to actual lines in csv) reported by @rel-thanu

## 1.5.6 - 2024-09-15

### Changed
- Improved GoCardless rate limiting handling

### Fixed
- GoCardless rate limit could report negative time.
- Documentation links

## 1.5.5 - 2024-09-05

### Changed
- Better GoCardless rate limiting reporting

### Fixed
- [Issue 9194](https://github.com/firefly-iii/firefly-iii/issues/9194) (data importer via gocardless EU - details of the account is not imported anymore after last two versions) reported by @fate8383

## 1.5.4 - 2024-08-22

### Added
- [Issue 9139](https://github.com/firefly-iii/firefly-iii/issues/9139) (Implement rate limiting for GoCardless) reported by @JC5

## 1.5.3 - 2024-07-28

### Added
- Support for another bank's debit indicator, by @0xjams 

### Fixed
- Case-sensitive matching on bank debit indicator, by @rajabilal555
- [Discussion 9067](https://github.com/orgs/firefly-iii/discussions/9067) (Unable to automate: Data-importer doesn't remember which accounts spectre accounts map to.) started by @demiGod095
- [Issue 9044](https://github.com/firefly-iii/firefly-iii/issues/9044) (Minor issue in AutoImport.php - does not catch a 'bad path' error) reported by @Terry-JF

## 1.5.2 - 2024-04-24

### Added
- [Issue 8769](https://github.com/firefly-iii/firefly-iii/issues/8769) (Perform balance check after the import) reported by @dawid-czarnecki

### Fixed
- [Issue 8799](https://github.com/firefly-iii/firefly-iii/issues/8799) (Undefined index in CAMT role selection) reported by @beune
- [Issue 8800](https://github.com/firefly-iii/firefly-iii/issues/8800) (Call to undefined function 'hasResponse') reported by @VanQz

## 1.5.1 - 2024-04-18

### Fixed
- [Issue 8798](https://github.com/firefly-iii/firefly-iii/issues/8798) (Undefined variable $version) reported by @JC5
- Fix an issue with default currency selection.

## 1.5.0 - 2024-04-18

### Added
- The data importer has a release flow just like Firefly III, dropping generated JS files and other dependencies from the repository.
- [Issue 8665](https://github.com/firefly-iii/firefly-iii/issues/8665) (API exception if transaction is deleted by rule) reported by @Medformatik
- [Issue 8691](https://github.com/firefly-iii/firefly-iii/issues/8691) (Import gocardless "merchantCategoryCode") reported by @tboby

### Changed
- Rewrote front-end layout to AlpineJS + Laravel Blade, moving away from Twig + Vue.
- Some rules in the PR template

### Fixed
- [Discussion 8709](https://github.com/orgs/firefly-iii/discussions/8709) (Data importer imports duplicates of deleted transactions when detection is set to external id) started by @kesot
- [Issue 8736](https://github.com/firefly-iii/firefly-iii/issues/8736) (Error importing CAMT.053 XML with data-importer) reported by @Gamesh
- [Issue 8771](https://github.com/firefly-iii/firefly-iii/issues/8771) (Typo on Data Importer log) reported by @jemtz-deleon
- [PR 575](https://github.com/firefly-iii/data-importer/pull/575) (Update `.env.example`, `ASSET_URL` moved, example given) by @stevewasiura

## 1.4.5 - 2024-03-21

### Added
- Added the option to add a "fallback" configuration file for directory imports, by @Call-Me-G-Now. See the [documentation](https://docs.firefly-iii.org/how-to/data-importer/advanced/post/#importing-a-local-directory) for more info.
- Client side file type validation by @Call-Me-G-Now

### Changed
- Better list of currencies in the configuration screen, by @patricksindelka

### Fixed
- [Issue 8574](https://github.com/firefly-iii/firefly-iii/issues/8574) (Importing camt.053 file fails during conversion step ) reported by @laeckerv
- [Issue 8582](https://github.com/firefly-iii/firefly-iii/issues/8582) (CSV importer uses "contains" to detect duplicates in External ID) reported by @RobinWeitzel
- [Issue 8583](https://github.com/firefly-iii/firefly-iii/issues/8583) (Importing camt.053 file fails during conversion step when Statement Account IBAN  is mapped) reported by @laeckerv
- [Issue 8607](https://github.com/firefly-iii/firefly-iii/issues/8607) (Data Importer Vanity URL with Docker not being shown) reported by @Jademalo
- Typo in debug logs by @Call-Me-G-Now

## v1.4.4 - 2024-02-18

### Fixed
- [Issue 8534](https://github.com/firefly-iii/firefly-iii/issues/8534) (Data Importer ignoring relevant Nordigen error.) reported by @miguelangel-nubla
- [Issue 8541](https://github.com/firefly-iii/firefly-iii/issues/8541) (new gocardless connection string, old string will be deprecated) reported by @siemdejong
- [PR 529](https://github.com/firefly-iii/data-importer/pull/529) (Fixed a small issue with a label targeting the wrong input) by @moritzgloeckl
- [PR 531](https://github.com/firefly-iii/data-importer/pull/531) (Update Nordigen base url) by @JcMinarro

## 1.4.3 - 2024-02-05

### Changed
- The data importer can connect to nightly builds of Firefly III, which self-report as version `develop/YYYY-MM-DD`.

### Fixed
- [Discussion 8482](https://github.com/orgs/firefly-iii/discussions/8482) (Why is the importer turning 25.00000 into 2,500,000?) started by @jerrac

## v1.4.2 - 2024-01-22

### Added
- [Issue 8391](https://github.com/firefly-iii/firefly-iii/issues/8391) (Add OCI Labels to Docker Images) reported by @mytlogos

### Fixed
- [Issue 8367](https://github.com/orgs/firefly-iii/discussions/8367) Bad type error, reported by @quotary
- [Issue 8389](https://github.com/firefly-iii/firefly-iii/issues/8389) (Data Importer stops Autoimport of multiple Nordigen/GoCardless files if one has zero transactions) reported by @rerobade
- [Issue 8404](https://github.com/firefly-iii/firefly-iii/issues/8404) (Data importer confuses thousands separator for decimal separator when decimal separator is missing.) reported by @nonsavant
- [Issue 8408](https://github.com/firefly-iii/firefly-iii/issues/8408) (Importing camt.053 file fails during conversion step) reported by @ltouroumov
- [Issue 8414](https://github.com/firefly-iii/firefly-iii/issues/8414) (Getting `Unexpected data found.` trying to import via GoCardless/Nordigen) reported by @dabeeeenster

## v1.4.1 - 2024-01-15

### Added
- Nordigen supports "bookingDateTime"

### Fixed
- [Issue 8267](https://github.com/firefly-iii/firefly-iii/issues/8267) Fix reference to variable
- [Issue 8294](https://github.com/firefly-iii/firefly-iii/issues/8294) [issue 8355](https://github.com/firefly-iii/firefly-iii/issues/8355) Null pointer fixed
- [Issue 8293](https://github.com/firefly-iii/firefly-iii/issues/8293) Don't stop importing when one connection has no transactions
- [Issue 8344](https://github.com/firefly-iii/firefly-iii/issues/8344) The UI will report progress again
- [Issue 8355](https://github.com/firefly-iii/firefly-iii/issues/8355) Foreign amounts would not be imported
- Fix log levels in papertrail configuration
- The UI will email reports again

## 1.4.0 - 2023-12-18

> ⚠️⚠️ This release required **PHP 8.3.0** and will not work on earlier releases of PHP ⚠️⚠️

>  ⚠️ Some changes in this release may unexpectedly lead to duplicate transactions. This is caused by changes in the data handling routines. This is unfortunate, but a result of new insights, changed APIs and other minor fixes. My apologies for any inconvenience. I try to avoid these kinds of changes, but it can't always be helped.

### Added
- GoCardless-users are forwarded to refresh their agreement if necessary.

### Changed
- ⚠️⚠️ Added support for PHP 8.3 ⚠️⚠️
- Requires Firefly III **v6.1.0** or higher

### Removed
- ⚠️⚠️ Removed support for PHP 8.2 ⚠️⚠️

### Fixed
- [Issue 8000](https://github.com/firefly-iii/firefly-iii/issues/8000) Inform user when there are no transactions found by the Nordigen Data Importer
- [Issue 6500](https://github.com/firefly-iii/firefly-iii/issues/6500) Transactions with 0.0 amount are not imported
- [Issue 6470](https://github.com/firefly-iii/firefly-iii/issues/6470) use the "ultimateCreditor"-field for Nordigen transactions
- [Issue 5460](https://github.com/firefly-iii/firefly-iii/issues/5460) Add balance info in configuration screen
- Access token from `Authorization`-header is forwarded to Firefly III if necessary.
- All documentation links are fixed again.
- Improve text in configuration screen.
- Expand notifications so logging can be more easily parsed.
- Nordigen / GoCardless now saves the booked / pending tags as well.

## 1.3.12 - 2023-11-23

### Added
- Support for Mint imports. Use [this configuration file](https://github.com/firefly-iii/import-configurations/blob/main/other-software/mint.json).

## 1.3.11 - 2023-11-23

### Changed
- Add some more explanation text to the date input

### Fixed
- [Issue 8184](https://github.com/firefly-iii/firefly-iii/issues/8184) I broke the importer 

## 1.3.10 - 2023-11-21

>  ⚠️ Some changes in this release may unexpectedly lead to duplicate transactions. This is caused by changes in the data handling routines. This is unfortunate, but a result of new insights, changed APIs and other minor fixes. My apologies for any inconvenience. I try to avoid these kinds of changes, but it can't always be helped.

### Added
- [Issue 7977](https://github.com/firefly-iii/firefly-iii/issues/7977) More details on accounts added

### Changed
- Renamed Nordigen to GoCardless

### Fixed
- [Issue 8130](https://github.com/firefly-iii/firefly-iii/issues/8130) External ID with space breaks duplicate check
- [Issue 8158](https://github.com/firefly-iii/firefly-iii/issues/8158) Several nullpointers


## 1.3.9 - 2023-11-04

>  ⚠️ Some changes in this release may unexpectedly lead to duplicate transactions. This is caused by changes in the data handling routines. This is unfortunate, but a result of new insights, changed APIs and other minor fixes. My apologies for any inconvenience. I try to avoid these kinds of changes, but it can't always be helped.

### Added
- Fancy new security.txt file.

### Changed
- Better safety catches for Nordigen and Spectre, in case they accidentally try to create deposits or withdrawals that should be transfers.

### Fixed
- [Issue 7948](https://github.com/firefly-iii/firefly-iii/issues/7948) Null pointer exception in CAMT processing.
- [Issue 8005](https://github.com/firefly-iii/firefly-iii/issues/8005) Sometimes the importer does not recognize the importer account
- [Issue 8045](https://github.com/firefly-iii/firefly-iii/issues/8045) Bad caching leads to unfixable authentication errors
- [Issue 8052](https://github.com/firefly-iii/firefly-iii/issues/8052) Credit card transactions after a certain date are not imported while all other transactions are
- [Issue 8207](https://github.com/firefly-iii/firefly-iii/issues/8207) Data Importer ignoring relevant Nordigen error.

## 1.3.8 - 2023-09-02

### Fixed
- [Issue 7918](https://github.com/firefly-iii/firefly-iii/issues/7918) Make sure XML file are recognized by the POST importer

## 1.3.7 - 2023-09-02

### Fixed
- The POST import command and the CLI import routine will also stop working if the connected Firefly III is too old.

## 1.3.6 - 2023-09-02

>  ⚠️ This release may create duplicate transactions. Don't import large batches!

### Fixed
- [Issue 7891](https://github.com/firefly-iii/firefly-iii/issues/7891) Importing from Spectre has improved, taking into account new API information. This may change the look and feel of your transactions.
- Some banks would return amount in scientific notation.
- Several CAMT fixes when the file is missing data.
- CAMT fix to verify the transaction type.
- Better flow for the onboarding of new users. Updated the documentation to match.

## 1.3.5 - 2023-08-23

### Fixed
- Error in CSV import would break imports for many users, thanks for reporting!
- Many null pointers removed from CAMT.053 import.

## 1.3.4 - 2023-08-21

>  ⚠️ This release may create duplicate transactions. Don't import large batches!

### Added
- Unmapped fields are now sorted at the top for CSV files.
- Spectre import will include IBAN and name, not just "name or IBAN".

### Fixed
- Notes are properly trimmed for import
- Account search will report on its results in the logs
- Fixed several camt.053 unstructured data import issues.
- [Issue 7816](https://github.com/firefly-iii/firefly-iii/issues/7816) Importer would break when there was no currency information in a camt file.

## 1.3.3 - 2021-07-17

### Fixed
- Formatting issue in debug log message broke the CAMT import

## 1.3.2 - 2023-07-16

### Added
- Better error handling for CAMT files.

### Changed
- New way of saving user info, should make it easier to change variables without running into old values.

### Fixed
- [Issue 7729](https://github.com/firefly-iii/firefly-iii/issues/7729) Make sure account type detection can't be overruled by later attempts.
- [Issue 7747](https://github.com/firefly-iii/firefly-iii/issues/7747) Nullpointer in CAMT import

## 1.3.1 - 2021-06-27=8

> ⚠️ This release may create duplicate transactions. Don't import large batches!

### Changed
- This version of the data importer requires Firefly III v6.0.16 or higher.

### Fixed
- Many small and large fixes in the new CAMT.053 import routine. Thanks again, @beatbesmer and bug testers!
- [Issue 7643](https://github.com/firefly-iii/firefly-iii/issues/7643) Bad redirect when trying to import using a config file. 

## v1.3.0 - 2023-06-05

- ⚠️ This release may create duplicate transactions. Don't import large batches!

### CAMT.053 support

GitHub user @beatbesmer has been working hard to make the data importer support CAMT.053 files. You can now upload 
these files like you would a CSV file and have them imported into Firefly III. Although this new feature has been
thorougly tested by Beat and myself, CAMT.053's XML standards are a very loose standard so expect bugs, issues and
crashes. We welcome your feedback in the form of [bug reports](https://github.com/firefly-iii/firefly-iii/issues/new?assignees=&labels=&projects=&template=bug.yml)

### Added
- [Issue 6108](https://github.com/firefly-iii/firefly-iii/issues/6108) You can customize the import tag used by the data importer

### Fixed
- [Issue 5996](https://github.com/firefly-iii/firefly-iii/issues/5996) Cannot send email using self-signed certificates
- [Issue 7512](https://github.com/firefly-iii/firefly-iii/issues/7512) Broken links in app and documentation
- [Issue 7264](https://github.com/firefly-iii/firefly-iii/issues/7264) Data Importer crashes when mail server auth fails

## v1.2.2 - 2023-03-19

- ⚠️ This release may create duplicate transactions. Don't import large batches!

### Changed
- Drop accuracy to 12 decimals. This fixes a lot of rounding issues.

### Fixed
- [Issue 7225](https://github.com/firefly-iii/firefly-iii/issues/7225) Fix email error when no vanity url is set

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
- Code to support the [cloud installation](https://docs.firefly-iii.org/references/data-importer/public/).
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

## v0.0.0 - 2021-xx-xx

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
