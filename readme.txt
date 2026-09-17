=== Table Reflow ===
Contributors: fyrins
Donate link: https://github.com/sponsors/Fyrins
Tags: accessibility, tables, responsive, wcag, a11y
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Stack table blocks into readable cards on small screens instead of scrolling sideways. Server rendered, no JavaScript, helps with WCAG 1.4.10.

== Description ==

A data table that is wider than a phone screen forces the reader to scroll sideways. Success criterion **1.4.10 Reflow** of WCAG 2.2 says content must be readable at 320 pixels wide without scrolling on two axes, which is exactly what a wide table does. The same criterion is what the French RGAA 4 checks under its own numbering.

Table Reflow adds one option to the table block of the WordPress editor: **Stack on small screens**. Below the width you pick, the table stops behaving like a table. Each row becomes a card, and each value is preceded by the name of its column, so the reader never has to remember which column they were in.

= Accessibility is the point, not a side effect =

Turning table elements into blocks is easy. Doing it without destroying the table for screen reader users is the part most snippets get wrong, because `display: block` silently removes the implicit ARIA role of every table element. This plugin writes those roles back: `table`, `rowgroup`, `row`, `columnheader` and `cell`. Without them the relationship between a header and its cells is gone, which fails success criterion 1.3.1 Info and Relationships.

The header row is hidden from sight, never from assistive technology. It is clipped, not set to `display: none`, so it stays in the accessibility tree. The visible label drawn next to each value comes from CSS generated content, which is not announced reliably by every screen reader, so it is treated as a visual convenience only. The header information is always carried by the roles and the hidden header row as well, never by the pseudo-element alone.

= What it does not do =

Not every table can be stacked honestly. A table with merged cells, with its headers in the first column, or with no header row at all has no one-to-one mapping between a value and a column name, and inventing one would mislabel the data. When the plugin detects any of those, it changes nothing and leaves the table scrolling horizontally. It then makes that scrolling container reachable with a keyboard and gives it an accessible name, which the block does not do on its own.

= How it works =

The transformation happens on the server, when the page is rendered. The plugin reads the block markup with the HTML API that ships with WordPress and writes attributes into it. Nothing is stored in the saved content, so your tables stay valid whether the plugin is active or not, and removing the plugin leaves no broken blocks behind.

* No JavaScript on the front end. None at all.
* No outbound network request, no tracking, no analytics.
* No database option is created.
* No external font, script or stylesheet is loaded.
* The stylesheet loads only on pages where a table was actually transformed.

= About compliance =

No plugin can make a site compliant with an accessibility standard, and this one does not claim to. Accessibility depends on your content, your theme, and every other plugin you run. Responsibility for meeting any standard stays with the site owner.

What this plugin does is remove one specific and very common failure: a data table that forces the reader to scroll sideways on a narrow screen. Within that scope it is built against WCAG 2.2 level AA:

* 1.3.1 Info and Relationships, by restoring the ARIA roles that `display: block` removes
* 1.3.2 Meaningful Sequence, by never reordering content
* 1.4.1 Use of Color, by telling labels apart from values with weight, not colour
* 1.4.3 Contrast, by inheriting the text colour your theme already uses instead of setting its own
* 1.4.4 Resize Text and 1.4.10 Reflow, by removing the horizontal scroll
* 2.1.1 Keyboard and 2.4.7 Focus Visible, on the container of tables that keep scrolling

The same criteria are what RGAA 4 checks under its own numbering. Testing with a real screen reader is still on you; the source repository lists the expected result for each table structure.

= Development =

Development happens in the open at https://github.com/Fyrins/table-reflow . The plugin ships no build step: the code you download is the code that runs.

== Installation ==

1. Install the plugin through **Plugins → Add New**, or upload the folder to `wp-content/plugins/`.
2. Activate it.
3. Edit a post, select a table block, and open **Small screen display** in the block sidebar.
4. Turn on **Stack on small screens** and pick the width below which the table should stack.

The table needs a header row for the option to have any effect. In the table block toolbar, use **Header section** to add one.

== Frequently Asked Questions ==

= My table still scrolls sideways. Why? =

The plugin only stacks tables it can label honestly. It leaves a table alone when it has no header section, when the headers are in the first column instead of the first row, when any cell uses `colspan` or `rowspan`, or when a row has a different number of cells than the header row. Add a header section and remove merged cells, and the option takes effect.

= Does it work with my theme? =

Yes. The plugin sets no colour of its own. Labels inherit the text colour of their cell, so whatever contrast your theme already provides is preserved, in light and dark schemes alike.

= Can I change the breakpoints? =

Yes, with the `table_reflow_breakpoint_values` and `table_reflow_default_breakpoint` filters. Any value you add needs matching rules in a stylesheet you enqueue yourself, because a CSS media query cannot read a custom property.

= Does it slow pages down? =

The stylesheet is about 13 KB, and it is enqueued only on pages that actually contain a transformed table. It compresses to under 2.5 KB, because the four breakpoint blocks are near identical: a media query condition cannot read a CSS custom property, so each breakpoint needs its own rules. There is no front-end JavaScript at all, and blocks other than the table block cost one string comparison.

= Will it break my existing tables? =

No. The plugin writes nothing into the content you save. Activating or deactivating it never changes the stored markup, so no block can be invalidated either way.

= Does it send anything anywhere? =

No. There is no outbound request of any kind, no telemetry, and no third party asset.

== Screenshots ==

1. The option in the block sidebar of the editor.
2. A table on a wide screen, unchanged.
3. The same table on a phone, each row stacked as a card with its column names.
4. A table that cannot be stacked, kept scrollable and reachable with a keyboard.

== Changelog ==

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.0.0 =
First release.
