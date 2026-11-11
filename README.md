# Personal data block

A TYPO3 14.3 content element showing the current authenticated user's profile:

1. Frontend login: first name, last name and email from `fe_users`.
2. Otherwise, backend login: real name and email from `be_users`.
3. No login: nothing is rendered.

Frontend login takes priority when both sessions exist. The backend login is the
TYPO3 editor session in the same browser; it is not a frontend membership account.

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
choose **Personal data block** and save. No personal-data fields need to be entered.
The site package must render the chosen content column. No dependency on Camino
or the Events extension is required. Each visitor sees only their own profile.

## Learn the files

- `Configuration/TCA/Overrides/tt_content.php`: registers the content type.
- `Classes/DataProcessing/CurrentUserProcessor.php`: resolves the authenticated user through the Context API and reads only the profile fields.
- `Configuration/Services.yaml`: dependency injection and data processor registration.
- `Configuration/Sets/PersonalDataBlock/`: frontend rendering registration.
- `Resources/Private/Templates/PersonalDataBlock.fluid.html`: escaped HTML output.
- `Resources/Private/Language/locallang_db.xlf`: backend labels.
- `Resources/Public/Css/personal-data-block.css`: block styling.

`COA_INT` renders the block on each request, outside the shared page cache.
The processor receives Context and ConnectionPool through constructor injection.
Fluid escapes the profile values. No user ID can be selected by the editor or URL.

Upgrading from the initial version removes the manual fields from the form and
stops reading them. Existing blocks keep working. Old database columns are left
untouched; no destructive database cleanup is performed.

## Quality and tests

From the DDEV project root:

```sh
ddev exec composer --working-dir=packages/personal_data_block install
ddev exec composer --working-dir=packages/personal_data_block check
```

`check` runs PHPCS, PHP-CS-Fixer (dry run), PHPStan and PHPUnit functional tests.
Tests use TYPO3's Testing Framework with an isolated SQLite database and a minimal
test site, never your local site's data. They cover anonymous visitors, frontend
and backend sessions, frontend priority, HTML escaping and switching users with
real database page caching enabled.
GitHub Actions runs each tool separately on PHP 8.2 and 8.4 after a push or PR.

[TYPO3 content element documentation](https://docs.typo3.org/m/typo3/reference-coreapi/14.3/en-us/ApiOverview/ContentElements/Index.html)
