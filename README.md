# XML Validator by Imagination Media

XML Validator by [Imagination Media] is a development tool for Magento Open
Source and Adobe Commerce that adds a console command for validating XML files
against their configured schema.

## Features & Benefits

- Ensure your XML files are valid according to their corresponding core schema
thereby reducing deployment and runtime errors

## Requirements

- Magento Open Source or Adobe Commerce version 2.4.4 or greater (_version
  2.4.5 or greater recommended_)
- PHP version 7.4.32 or greater, or PHP 8.1.12 or greater (_recommended_)

## Installation

The XML Validator extension is available for installation via Composer by
entering the following commands into your terminal or command prompt:

    cd /path/to/your/store
    composer require --dev imaginationmedia/module-xml-validator

### Post-Installation

After installation of the extension, you **must** run the following command to
patch your `setup/src/Magento/Setup/Console/CommandList.php` file. This will
allow the tool to run with only the core Magento files installed and no
database.

    patch -p1 < vendor/imaginationmedia/module-xml-validator/patches/Add-validate-XML-command-to-Setup-Command-List.patch

## Updating

To update the XML Validator extension using Composer, run these commands from
your terminal or command prompt:

    cd /path/to/your/store
    composer update imaginationmedia/module-xml-validator

## Post-Install or Post-Update

To complete the installation or update process, please run these commands:

    cd /path/to/your/store
    php bin/magento module:enable ImaginationMedia_XmlValidator
    php bin/magento setup:upgrade
    php bin/magento setup:di:compile
    php bin/magento setup:static-content:deploy

## Usage

### Command Line

Run this tool in your local environment with the following commands:

    cd /path/to/your/store
    bin/magento dev:xml:validate path/to/your/code

#### Arguments

| Argument | Description                                | Is Required |
|----------|--------------------------------------------|-------------|
| paths    | One or more paths to validate XML files in | Yes         |

### CI/CD with GitHub Actions

This tool will automatically detect if it is being run in a Continuous
Integration and Continuous Deployment (CI/CD) pipeline with GitHub Actions and
output the statements needed to show warnings and errors inline with the
affected code. Support for other CI/CD pipelines will be added in future
releases.

## Support

If you experience any issues or errors while using this extension, please
[open an issue] in the GitHub repository. Be sure to include all relevant
information, including a description of the issue or error, what you were doing
when it occurred, what versions of Magento Open Source or Adobe Commerce and PHP
are installed and any other pertinent details. Our support staff will do their
best to respond to your request in a timely manner, typically within 24-48
business hours (Monday through Friday from 9:00AM—5:00PM U.S. Eastern Time,
excluding holidays).

## License

The source code contained in this extension is licensed under the Open Software
License version 3.0 (OSL-3.0) license. A copy of this license can be found in
the [LICENSE] file included with the source code or online at
https://opensource.org/licenses/OSL-3.0.

Copyright for the included source code is exclusively held by [Imagination
Media], all rights reserved.

## History

A full history of the extension can be found in the [CHANGELOG.md] file.

## Contributing

We welcome and value your contribution. For more details on how you can help us
improve and maintain this tool, please see the [CONTRIBUTING.md] file.

[Imagination Media]: https://www.imaginationmedia.com/
[open an issue]: https://github.com/Imagination-Media/magento-module-xml-validator/issues/new
[LICENSE]: ./LICENSE
[CHANGELOG.md]: ./CHANGELOG.md
[CONTRIBUTING.md]: ./CONTRIBUTING.md