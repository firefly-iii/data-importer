# Firefly III Data Importer

[![Packagist][packagist-shield]][packagist-url]
[![License][license-shield]][license-url]
[![Stargazers][stars-shield]][stars-url]
[![Donate][donate-shield]][donate-url]

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

The data importer does not connect to your bank directly. Instead, it uses [GoCardless](https://gocardless.com/) and [SaltEdge](https://www.saltedge.com/products/spectre/countries) to connect to over 6000 banks worldwide. These services are free for Firefly III users, but require registration. Keep in mind these services have their own privacy and data usage policies.

The data importer can also connect to your bank using `SimpleFIN`, and it can import CSV files you've downloaded from your bank.

You can run the data importer once, for a bulk import. You can also run it regularly to keep up with new transactions.

Eager to get started? Go to [the documentation](https://docs.firefly-iii.org/)!

## Features

* Import from over 6000 banks
* Import over the command line for easy automation
* Import over an API for easy automation
* Use rules and data mapping for transaction clarity

Many more features are listed in the [documentation](https://docs.firefly-iii.org/).

## Who's it for?

This application is for people who want to track their finances, keep an eye on their money **without having to upload their financial records to the cloud**. You're a bit tech-savvy, you like open source software, and you don't mind tinkering with (self-hosted) servers.

## Getting Started

Many more features are listed in the [documentation](https://docs.firefly-iii.org/).

## Contributing

You can contact me at [james@firefly-iii.org](mailto:james@firefly-iii.org), you may open an issue in the [main repository](https://github.com/firefly-iii/firefly-iii) or contact me through [gitter](https://gitter.im/firefly-iii/firefly-iii) and [Mastodon](https://fosstodon.org/@ff3).

Of course, there are some [contributing guidelines](https://github.com/firefly-iii/data-importer/blob/main/.github/contributing.md) and a [code of conduct](https://github.com/firefly-iii/data-importer/blob/main/.github/code_of_conduct.md), which I invite you to check out.

I can always use your help [squashing bugs](https://docs.firefly-iii.org/explanation/support/#contributing-code), thinking about [new features](https://docs.firefly-iii.org/explanation/support/#contributing-code) or [translating Firefly III](https://docs.firefly-iii.org/how-to/firefly-iii/development/translations/) into other languages.

There is also a [security policy](https://github.com/firefly-iii/data-importer/security/policy).

<!-- SPONSOR TEXT -->

## Support the development of Firefly III

If you like Firefly III and if it helps you save lots of money, why not send me a dime for every dollar saved! 🥳

OK that was a joke. If you feel Firefly III made your life better, please consider contributing as a sponsor. Please check out my [Patreon](https://www.patreon.com/jc5) and [GitHub Sponsors](https://github.com/sponsors/JC5) page for more information. You can also [buy me a ☕️ coffee at ko-fi.com](https://ko-fi.com/Q5Q5R4SH1). Thank you for your consideration.

<!-- END OF SPONSOR TEXT -->

## License

This work [is licensed](https://github.com/firefly-iii/data-importer/blob/main/LICENSE) under the [GNU Affero General Public License v3](https://www.gnu.org/licenses/agpl-3.0.html).

<!-- HELP TEXT -->

## Do you need help, or do you want to get in touch?

Do you want to contact me? You can email me at [james@firefly-iii.org](mailto:james@firefly-iii.org) or get in touch through one of the following support channels:

- [GitHub Discussions](https://github.com/firefly-iii/firefly-iii/discussions/) for questions and support
- [Gitter.im](https://gitter.im/firefly-iii/firefly-iii) for a good chat and a quick answer
- [GitHub Issues](https://github.com/firefly-iii/firefly-iii/issues) for bugs and issues. Issues are collected centrally, in the [Firefly III repository](https://github.com/firefly-iii/firefly-iii).
- <a rel="me" href="https://fosstodon.org/@ff3">Mastodon</a> for news and updates

<!-- END OF HELP TEXT -->

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
