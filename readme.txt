=== Municipal Phonebook ===
Contributors: dev
Tags: phonebook, directory, contacts, municipality, greek
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.5.1
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
* `row_odd="#ffffff"` and `row_even="#f3f6fc"` override the odd and even contact-row colors for this directory. Invalid values are ignored. The same colors can be set for every directory under Phonebook → Settings. Even rows default to a light tint so the stripes are visible before you pick colors.

Several directories can be placed on the same page; each one has its own search and category filter.

= Is the Elementor widget required? =

No. The widget is only registered when Elementor is active. It uses the same renderer as the shortcode.

= How do I enter several phone numbers? =

One line per group, numbers in the same group separated by `|`. In CSV use `;` between groups.

= What is the CSV format? =

Header: `department,subdepartment,name,role,phones,email,order`, plus one column for each custom column slug. A row with a sub-department and an empty name sets the sub-department's own phones/email. A file column cell is an attachment ID or the file URL.

= Can each department have different columns? =

Yes. Phonebook → Settings → Columns sets the columns for every department. On a department you can keep that list or choose another order. A child department uses its parent's columns unless you choose its own. File columns use the media library and are shown as a download link.

= How do sub-departments appear? =

By default a child department is a row inside the parent table. Phonebook → Settings can switch back to the previous row, which shows that department's own phones and email. Each department can override the choice.

= Is the internal note public? =

No. It is never rendered, never exported, and never included in the REST API.

= Does uninstalling remove my data? =

Options and caches are always removed. Contacts and departments are removed only if you enable "Delete all phonebook data" in Phonebook → Settings.

= Can I add my own CSS? =

Yes. Phonebook → Settings → Custom CSS applies to every directory, and only on pages that show one. The Elementor widget has its own Custom CSS field on the Advanced tab: the word `selector` means that widget's directory (`.munipb-directory`). CSS with HTML, a style tag, unbalanced braces, or an unclosed comment is rejected.

== Changelog ==

= 1.5.1 =
* The department heading sits flush on its table, with square bottom corners. The table and its cells have no border, including under the Hello Elementor reset. Odd and even row colors still separate the contacts. Card layout keeps a gap under the heading.

= 1.5.0 =
* Child departments can be a sub-header row inside the parent table. The sub-header color is configurable in Settings and in the Elementor widget. Search hides a sub-header when none of its contacts match, and row stripes start again after each sub-header.
* Settings → Columns defines the directory columns. Built-in columns can be reordered or hidden. Custom columns can be text, phone, email, a link, or a file.
* Each department can use the global columns or its own set. A child department inherits its parent unless it has its own set.
* A file column stores a media library attachment. The public page shows a download link with the contact's name.
* Visible text, phone, email and link columns are included in the directory search. The REST search includes text columns. CSV import and export use the column slug. A file cell is an attachment ID or URL, and the REST response exposes the file URL.

= 1.4.2 =
* Update checks stay cached for 3 hours. A failed lookup is cached for 1 hour.
* Dashboard → Updates → Check again clears that cache, as does clearing the plugin update cache.
* The Plugins row has "Check for updates". It checks GitHub immediately and reports whether a new version is available.

= 1.4.1 =
* Settings include a custom CSS editor with live CSSLint checks. Invalid CSS is rejected and the previous CSS is kept.
* The Elementor widget has a Custom CSS control. The selector keyword targets that widget's directory.
* Custom CSS is printed only when a directory is shown.
* The Plugins screen no longer links to the plugin site. Updates still come from the GitHub Update URI.

= 1.4.0 =
* New toolbar above the directory: Categories (multi-select department filter), Search, and Clear. The table of contents list is no longer shown at the top.
* `toc` now shows or hides the Categories menu. `search` shows or hides the Search button. Department headings stay in the list, and empty departments hide after a filter or search.
* Toolbar controls keep their own border, color, and focus ring so theme button styles (Hello Elementor, WoodMart) do not restyle them.
* Odd and even contact rows can be colored from Settings, the shortcode (`row_odd`, `row_even`), the block, or the Elementor style tab. Even rows default to #f3f6fc. Stripes follow the rows still visible after a filter or search, including the card layout. Theme table colors do not paint over them.
* Search opens inside the toolbar, between Categories and Clear. On a narrow directory it wraps to its own row inside the same band.

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
