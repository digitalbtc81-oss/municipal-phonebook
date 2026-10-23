=== Municipal Phonebook ===
Contributors: dev
Tags: phonebook, directory, contacts, municipality, greek
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Searchable phonebook for municipalities and organisations, grouped by department, with accent-insensitive Greek search and an Elementor widget.

== Description ==

Municipal Phonebook lets you publish an organisation's telephone directory (departments, sub-departments, people, roles, phones and emails) and lets visitors find the right contact in seconds.

* Departments as a hierarchical taxonomy (top level = section, child = sub-department row with its own phones/email).
* Contacts as a private custom post type: name, role, one or more phone groups, email, and an internal note that is never published.
* Live search-as-you-type with keyboard navigation (arrow keys, Enter, Escape), result highlighting and jump-to-row.
* Accent-insensitive search for Greek (τόνοι, διαλυτικά, final sigma) and phone search that ignores spaces and dashes.
* Category filter: a "Categories" menu filters the directory by department. The old contents list is no longer shown above the entries.
* Responsive: the table becomes cards on mobile. Click-to-call (tel:) and mailto links; emails are obfuscated against harvesters.
* Works without JavaScript (server-side search fallback).
* Shortcode `[municipal_phonebook]` (use it in the Elementor "Shortcode" widget, WoodMart HTML blocks, classic editor) a "Municipal Phonebook" block and a native Elementor widget ("Municipal Phonebook" category) with content and style controls.
* CSV import / export (UTF-8, comma or semicolon, Excel friendly).
* Optional read-only REST endpoint with rate limiting (off by default).
* Optional Google Analytics "click_to_call" event.
* Translation ready; Greek translation included.
* Assets load only on pages that show the directory. No jQuery.

== Installation ==

1. Upload the `municipal-phonebook` folder to `/wp-content/plugins/` or install the zip via Plugins → Add New → Upload.
2. Activate the plugin.
3. Add departments under Phonebook → Departments (use "Parent department" for sub-departments and "Display order" for ordering).
4. Add contacts under Phonebook → Add contact, or import a CSV under Phonebook → Import / Export.
5. Add `[municipal_phonebook]` or the "Municipal Phonebook" block to a page.

== Frequently Asked Questions ==

= Which shortcode attributes are available? =

* `search="0"` hides the search button (default `1`).
* `toc="0"` hides the Categories filter (default `1`).
* `departments="slug-1,slug-2"` shows only these top-level departments.
* `breakpoint="720"` container width under which rows turn into cards: `never`, `480`, `720`, `960` or `always`.
* `row_odd="#ffffff"` and `row_even="#ffffff"` override the odd and even contact-row colors for this directory. Invalid values are ignored. The same colors can be set for every directory under Phonebook → Settings.

Several directories can be placed on the same page; each one has its own search and category filter.

= Is the Elementor widget required? =

No. The widget is only registered when Elementor is active. It uses the same renderer as the shortcode.

= How do I enter several phone numbers? =

One line per group, numbers in the same group separated by `|`. In CSV use `;` between groups.

= What is the CSV format? =

Header: `department,subdepartment,name,role,phones,email,order`. A row with a sub-department and an empty name sets the sub-department's own phones/email.

= Is the internal note public? =

No. It is never rendered, never exported, and never included in the REST API.

= Does uninstalling remove my data? =

Options and caches are always removed. Contacts and departments are removed only if you enable "Delete all phonebook data" in Phonebook → Settings.

== Changelog ==

= 1.4.0 =
* New toolbar above the directory: Categories (multi-select department filter), Search, and Clear. The table of contents list is no longer shown at the top.
* `toc` now shows or hides the Categories menu. `search` shows or hides the Search button. Department headings stay in the list, and empty departments hide after a filter or search.
* Toolbar controls keep their own border, color, and focus ring so theme button styles (Hello Elementor, WoodMart) do not restyle them.
* Odd and even contact rows can be colored from Settings, the shortcode (`row_odd`, `row_even`), the block, or the Elementor style tab. Stripes follow the rows still visible after a filter or search. Theme table colors do not paint over them.

= 1.3.0 =
* GitHub release updates. When a newer GitHub Release exists, WordPress shows that a new version is available and can install it in one click. "View details" shows the release notes.
* Plugin URI and Update URI point at the GitHub repository so WordPress does not fetch a same-named plugin from wordpress.org.
* Update packages are installed into the `municipal-phonebook` folder.

= 1.2.0 =
* Removed pagination: the `per_page` attribute, the block and Elementor controls, the "Contacts per page" setting, the page/search REST endpoints and their rate limiting. The full list is always shown and searched in the browser.
* The orphan `per_page` setting is removed automatically on upgrade.
* The optional `/entries` REST endpoint is unchanged.

= 1.1.0 =
* AJAX pagination with accessible navigation, history support and a no-JavaScript fallback.
* Server-side search across all pages, with rate limiting.
* Native Elementor widget with style controls.
* New `per_page` and `breakpoint` attributes; "Contacts per page" setting.
* Table of contents skips empty departments.

= 1.0.0 =
* Initial release.
