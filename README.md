# Municipal Phonebook

Searchable telephone directory for municipalities and organisations. Visitors browse departments and sub-departments, search without Greek accents, and call or email from the page. The directory can be embedded with the `[municipal_phonebook]` shortcode, a block, or an Elementor widget.

Τηλεφωνικός κατάλογος για δήμους και οργανισμούς: διευθύνσεις, τμήματα, αναζήτηση χωρίς τόνους, shortcode, block και widget Elementor.

## Requirements

- WordPress 6.2 or newer
- PHP 7.4 or newer

## Install

1. Download `municipal-phonebook.zip` from the [GitHub Releases](https://github.com/digitalbtc81-oss/municipal-phonebook/releases) page.
2. In WordPress, go to Plugins → Add New → Upload Plugin and install the zip.
3. Activate **Municipal Phonebook**. The plugin directory must stay `wp-content/plugins/municipal-phonebook/`.

## Updates

From 1.3.0 onward the plugin checks this repository's GitHub Releases. When a newer release exists, **Plugins** and **Dashboard → Updates** show that a new version is available. Update installs with one click, and **View details** shows the release notes.

The check runs when WordPress itself checks for plugin updates. A successful lookup is cached for 12 hours. Errors and rate limits are cached for 6 hours and do not show an error to visitors. No token is required.

Only `https://api.github.com/repos/digitalbtc81-oss/municipal-phonebook/releases/latest` is requested. The download is accepted only when the release asset is named `municipal-phonebook.zip` and its URL is `https://github.com/digitalbtc81-oss/municipal-phonebook/releases/download/vX.Y.Z/municipal-phonebook.zip`. The `Update URI` header points at this repository so WordPress does not fetch a same-named plugin from wordpress.org.

Sites that still run 1.2.0 do not contain this updater. Install 1.3.0 once by uploading the zip. Later versions can then update from GitHub.

## Cut a release

1. On `main`, bump all of these to the same version (example `1.4.0`):
   - `Version` in `municipal-phonebook.php`
   - `MUNIPB_VERSION` in `municipal-phonebook.php`
   - `Stable tag` in `readme.txt`
   - a new `= 1.4.0 =` section at the top of the changelog in `readme.txt`
   - `Tested up to` in `readme.txt` and `Munipb_Updater::TESTED_WP` when the tested WordPress version changes
2. Merge that change to `main`.
3. Tag the merge commit and push the tag. The tag must be exactly `v` plus the version:

```bash
git checkout main
git pull origin main
git tag v1.4.0
git push origin v1.4.0
```

Pushing the tag runs `.github/workflows/release.yml`. The workflow stops if the tag, plugin header, `MUNIPB_VERSION`, and Stable tag disagree. Otherwise it runs `php -l` and PHPCS, builds `municipal-phonebook.zip` (the archive unpacks to `municipal-phonebook/` and omits `.github`, `vendor`, Composer files, PHPCS config, and this README), and publishes a GitHub Release whose notes are the changelog section. The attached asset must stay named `municipal-phonebook.zip`.

## First release

After this branch is merged:

```bash
git checkout main
git pull origin main
git tag v1.3.0
git push origin v1.3.0
```

Confirm the Actions run is green and the release `v1.3.0` has `municipal-phonebook.zip` attached. That is the package sites install.

## Development

Coding standards are PHPCS with WordPress Coding Standards and PHPCompatibilityWP (PHP 7.4+, WordPress 6.2+). `vendor/` is not part of the plugin.

```bash
composer install
composer lint
composer phpcs
```
