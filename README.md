# Personal data block

A small TYPO3 14.3 extension providing an editable content element with name,
surname and email. Details are entered by an editor, not read from a logged-in user.

## Install and use

Install the package with Composer (add this Git repository as a VCS repository
if it is not available through your project's path repository):

```sh
composer require study/personal-data-block:@dev
vendor/bin/typo3 extension:setup --extension=personal_data_block
```

Add `study/personal-data-block` to your site's `dependencies` in
`config/sites/<site>/config.yaml`, then flush TYPO3 caches. The set depends on
`typo3/fluid-styled-content`.

In **Content → Layout**, select a page, create content in Main content or Sidebar,
choose **Personal data block**, fill in the fields and save.
The site package must render the chosen content column. No dependency on Camino
or the Events extension is required. The email address is visible publicly.

## Learn the files

- `Configuration/TCA/Overrides/tt_content.php`: backend fields and content type.
- `Configuration/Sets/PersonalDataBlock/`: frontend rendering registration.
- `Resources/Private/Templates/PersonalDataBlock.fluid.html`: escaped HTML output.
- `Resources/Private/Language/locallang_db.xlf`: backend labels.
- `Resources/Public/Css/personal-data-block.css`: block styling.

TYPO3 generates the database columns from the input/email TCA definitions.
The three values are stored on the content record in `tt_content`.

## Quality and tests

From the DDEV project root:

```sh
ddev exec composer --working-dir=packages/personal_data_block install
ddev exec composer --working-dir=packages/personal_data_block check
```

`check` runs PHPCS, PHP-CS-Fixer (dry run), PHPStan and PHPUnit functional tests.
Tests use TYPO3's Testing Framework with an isolated SQLite database and a minimal
test site, never your local site's data. They cover saving fields, rendering,
email links, HTML escaping, hidden records and page/column isolation.
GitHub Actions runs each tool separately on PHP 8.2 and 8.4 after a push or PR.

[TYPO3 content element documentation](https://docs.typo3.org/m/typo3/reference-coreapi/14.3/en-us/ApiOverview/ContentElements/Index.html)
