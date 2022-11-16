# Firefly III Data Importer

[![Packagist][packagist-shield]][packagist-url]
[![License][license-shield]][license-url]
[![Stargazers][stars-shield]][stars-url]
[![Donate][donate-shield]][donate-url]
[![huntr][hack-shield]][hack-url]

<!-- PROJECT LOGO -->
<br />
<p align="center">
  <a href="https://firefly-iii.org/">
    <img src="https://raw.githubusercontent.com/firefly-iii/firefly-iii/develop/.github/assets/img/logo-small.png" alt="Firefly III" width="120" height="178">
  </a>
</p>
  <h1 align="center">Firefly III Data Importer</h1>

  <p align="center">
    Import your transactions into Firefly III
    <br />
    <a href="https://docs.firefly-iii.org/"><strong>Explore the documentation</strong></a>
    <br />
    <br />
    <a href="https://github.com/firefly-iii/firefly-iii/issues">Report a bug</a>
    ·
    <a href="https://github.com/firefly-iii/firefly-iii/issues">Request a feature</a>
    ·
    <a href="https://github.com/firefly-iii/firefly-iii/discussions">Ask questions</a>
  </p>


## About this data importer

"Firefly III" is a (self-hosted) manager for your personal finances. It can help you keep track of your expenses and income, so you can spend less and save more. The **data importer** is built to help you import transactions into Firefly III. It is separated from Firefly III for security and maintenance reasons.

The data importer does not connect to your bank directly. Instead, it uses [Nordigen](https://nordigen.com/en/coverage/) and [SaltEdge](https://www.saltedge.com/products/spectre/countries) to connect to over 6000 banks worldwide. These services are free for Firefly III users, but require registration. Keep in mind these services have their own privacy and data usage policies.

The data importer can import CSV files you've downloaded from your bank.

You can run the data importer once, for a bulk import. You can also run it regularly to keep up with new transactions.

Eager to get started? Go to [the documentation](https://docs.firefly-iii.org/data-importer)!

<!-- HELP TEXT -->
## Need help?

If you need support using Firefly III or the associated tools, come find us!

- [GitHub Discussions for questions and support](https://github.com/firefly-iii/firefly-iii/discussions/)
- [Gitter.im for a good chat and a quick answer](https://gitter.im/firefly-iii/firefly-iii)
- [GitHub Issues for bugs and issues](https://github.com/firefly-iii/firefly-iii/issues)
- [Follow me around for news and updates on Mastodon](https://fosstodon.org/@ff3)

<!-- END OF HELP TEXT -->

## Features

* Import from over 6000 banks
* Import over the command line for easy automation
* Import over an API for easy automation
* Use rules and data mapping for transaction clarity

Many more features are listed in the [documentation](https://docs.firefly-iii.org/data-importer).

## Who's it for?

This application is for people who want to track their finances, keep an eye on their money **without having to upload their financial records to the cloud**. You're a bit tech-savvy, you like open source software, and you don't mind tinkering with (self-hosted) servers.

## Getting Started

Many more features are listed in the [documentation](https://docs.firefly-iii.org/data-importer).

## Contributing

You can contact me at [james@firefly-iii.org](mailto:james@firefly-iii.org), you may open an issue in the [main repository](https://github.com/firefly-iii/firefly-iii) or contact me through [gitter](https://gitter.im/firefly-iii/firefly-iii) and [Mastodon](https://fosstodon.org/@ff3).

Of course, there are some [contributing guidelines](https://github.com/firefly-iii/data-importer/blob/main/.github/contributing.md) and a [code of conduct](https://github.com/firefly-iii/data-importer/blob/main/.github/code_of_conduct.md), which I invite you to check out.

I can always use your help [squashing bugs](https://docs.firefly-iii.org/support/contribute#bugs), thinking about [new features](https://docs.firefly-iii.org/support/contribute#feature-requests) or [translating Firefly III](https://docs.firefly-iii.org/support/contribute#translations) into other languages.

[Sonarcloud][sc-project-url] scans the code of Firefly III. If you want to help improve Firefly III, check out the latest reports and take your pick!

[![Quality Gate Status][sc-gate-shield]][sc-project-url] [![Bugs][sc-bugs-shield]][sc-project-url] [![Code Smells][sc-smells-shield]][sc-project-url] [![Vulnerabilities][sc-vuln-shield]][sc-project-url]

There is also a [security policy](https://github.com/firefly-iii/data-importer/security/policy).

### Support the development of Firefly III

If you like Firefly III and if it helps you save lots of money, why not send me a dime for every dollar saved! :tada:

OK that was a joke. If you feel Firefly III made your life better, consider contributing as a sponsor. Please check out my [Patreon](https://www.patreon.com/jc5) and [GitHub Sponsors](https://github.com/sponsors/JC5) page for more information. Thank you for considering donating to Firefly III!

## License

This work [is licensed](https://github.com/firefly-iii/data-importer/blob/main/LICENSE) under the [GNU Affero General Public License v3](https://www.gnu.org/licenses/agpl-3.0.html).

## Contact

You can contact me at [james@firefly-iii.org](mailto:james@firefly-iii.org), you may open an issue or contact me through the support channels:

- [GitHub Discussions for questions and support](https://github.com/firefly-iii/firefly-iii/discussions/)
- [Gitter.im for a good chat and a quick answer](https://gitter.im/firefly-iii/firefly-iii)
- [GitHub Issues for bugs and issues](https://github.com/firefly-iii/firefly-iii/issues)
- [Follow me around for news and updates on Mastodon](https://fosstodon.org/@ff3)

## Acknowledgements

The Firefly III logo is made by the excellent Cherie Woo.

[packagist-shield]: https://img.shields.io/packagist/v/firefly-iii/data-importer.svg?style=flat-square
[packagist-url]: https://packagist.org/packages/firefly-iii/data-importer
[license-shield]: https://img.shields.io/github/license/firefly-iii/data-importer.svg?style=flat-square
[license-url]: https://www.gnu.org/licenses/agpl-3.0.html
[stars-shield]: https://img.shields.io/github/stars/firefly-iii/data-importer.svg?style=flat-square
[stars-url]: https://github.com/firefly-iii/data-importer/stargazers
[donate-shield]: https://img.shields.io/badge/donate-%24%20%E2%82%AC-brightgreen?style=flat-square
[donate-url]: #support-the-development-of-firefly-iii
[hack-shield]: https://cdn.huntr.dev/huntr_security_badge_mono.svg
[hack-url]: https://huntr.dev/bounties/disclose

[sc-gate-shield]: https://sonarcloud.io/api/project_badges/measure?project=firefly-iii_data-importer&metric=alert_status
[sc-bugs-shield]: https://sonarcloud.io/api/project_badges/measure?project=firefly-iii_data-importer&metric=bugs
[sc-smells-shield]: https://sonarcloud.io/api/project_badges/measure?project=firefly-iii_data-importer&metric=code_smells
[sc-vuln-shield]: https://sonarcloud.io/api/project_badges/measure?project=firefly-iii_data-importer&metric=vulnerabilities
[sc-project-url]: https://sonarcloud.io/dashboard?id=firefly-iii_data-importer
