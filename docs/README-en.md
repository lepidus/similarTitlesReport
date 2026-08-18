**English** | [Português Brasileiro](/README.md) | [Español](/docs/README-es.md)

# Similar titles report

**generic** plugin for OJS that adds a page under `Statistics` > `Similar titles`, listing pairs of active submissions whose titles are similar.

## Compatibility

This plugin is compatible with OJS 3.5.0.x.

## Installation

For a production installation, use the `.tar.gz` package published on the plugin's releases page.

In OJS, go to `Settings` > `Website` > `Plugins` > `Upload a new plugin`, select the plugin package and confirm the installation.

## Usage

After enabling the plugin, the page is available under `Statistics` > `Similar titles`.

### Access

Journal managers and site administrators query active submissions in the context. Section editors query only submissions for which they have a workflow stage assignment as a section editor, following the OJS editorial workflow access scope.

### Similarity criterion

The listing compares titles with `similar_text` and shows pairs with similarity greater than or equal to 70%. The only pre-filter compares title lengths and discards only pairs that mathematically cannot reach the threshold.

To keep processing time and memory predictable, the report compares at most the 200 most recent accessible active submissions and displays at most the 1,000 pairs with the highest similarity. The page displays a notice whenever either limit is reached.

The computed similarity is stored in the application cache for 30 days. The key includes the submission IDs and the `sha256` of the normalized titles; if a title changes, the comparison is recomputed automatically.

## Development

Clone the plugin into `plugins/generic/similarTitlesReport` in a local OJS 3.5 installation.

### PHPUnit tests

Run from the OJS root:

```bash
TESTS=$(find -L plugins/generic/similarTitlesReport -name tests -type d -maxdepth 1) \
  && lib/pkp/lib/vendor/bin/phpunit --no-coverage --configuration lib/pkp/tests/phpunit.xml $TESTS
```

### PHP CS Fixer

Run from the OJS root before considering PHP changes ready:

```bash
php lib/pkp/lib/vendor/bin/php-cs-fixer fix \
  --config .php-cs-fixer.php --allow-risky=yes \
  plugins/generic/similarTitlesReport/
```

### Cypress tests

Run from the OJS root:

```bash
npx cypress run \
  --config 'baseUrl=http://localhost:8000,specPattern=plugins/generic/similarTitlesReport/cypress/tests/**/*.cy.js' \
  --browser chrome
```

The plugin's Cypress tests are not idempotent. Run the full suite against a fresh database, in the order of the existing specs.

## License

This plugin is licensed under the GNU General Public License v3.0.

_Copyright (c) 2026 Lepidus Tecnologia_
