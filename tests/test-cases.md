# Test cases

Every table structure the plugin can meet, what it is expected to do with it, and
what a screen reader is expected to say. Add a row here before changing anything
in `Table_Reflow_Renderer`.

All cases assume the block option **Stack on small screens** is on, and the
default breakpoint of 600px unless stated otherwise. "Narrow" means a viewport at
or below the breakpoint; "wide" means above it.

## How to run these by hand

1. Create a post, add the table described by the case, turn the option on.
2. View the post and check the rendered markup with the browser inspector.
3. Resize to 320px wide, then set the browser zoom to 400% at 1280px wide. In
   both, the page must scroll in one direction only (WCAG 1.4.10 and 1.4.4).
4. Walk the table with a screen reader and compare with the expected column.

Screen reader pairs used as the reference: **NVDA 2024.x with Firefox** on
Windows, and **VoiceOver with Safari** on macOS. Wording differs slightly between
versions; what matters is the information announced, not the exact phrasing.

## Transformed

These structures are stacked.

### T1. Canonical table

One `thead` row of `th`, a `tbody` whose rows all have as many `td` as there are
headers.

```html
<figure class="wp-block-table">
  <table>
    <thead><tr><th>Name</th><th>Role</th><th>City</th></tr></thead>
    <tbody>
      <tr><td>Ada</td><td>Engineer</td><td>London</td></tr>
      <tr><td>Grace</td><td>Admiral</td><td>New York</td></tr>
    </tbody>
  </table>
</figure>
```

Expected rendered markup:

```html
<table class="table-reflow table-reflow--bp-600" role="table">
  <thead role="rowgroup" class="table-reflow__head">
    <tr role="row">
      <th scope="col" role="columnheader">Name</th>
      …
    </tr>
  </thead>
  <tbody role="rowgroup">
    <tr role="row">
      <td role="cell" data-label="Name">Ada</td>
      …
```

| Context | NVDA + Firefox | VoiceOver + Safari |
| --- | --- | --- |
| Wide | "table with 3 rows and 3 columns". Table navigation with <kbd>Ctrl</kbd>+<kbd>Alt</kbd>+arrows announces the column header on entering each column. | "table 3 rows 3 columns". <kbd>VO</kbd>+arrows announce "Name, Ada, row 2 of 3, column 1 of 3". |
| Narrow | Identical to wide. The roles restore what `display: block` removed, and the clipped `thead` keeps the header text in the tree. The visible label drawn by CSS is **not** announced: its alternative text is empty. | Identical to wide, same reason. |

The check that matters here is the absence of duplication. Hearing "Name Name
Ada" means the alternative text syntax of `content` is not supported by the
browser under test, or the second `content` declaration was dropped.

### T2. Table with a caption

Same as T1, plus a block caption. The caption is a `figcaption` outside the
table, so it is untouched and read as ordinary text before or after the table,
depending on browse mode position. No change to the stacking.

### T3. Table with a footer row

Same as T1, plus a `tfoot` whose row has as many `td` as there are headers. The
`tfoot` receives `role="rowgroup"` and its cells are labelled like body cells.
A summary footer using `colspan` falls under F3 instead.

### T4. Cells containing inline markup

A cell holding `<a>`, `<strong>` or `<em>`. Only attributes are written, never
content, so links stay links and remain in the tab order. Header labels are
collected from the text nodes only, so a header reading `<em>Name</em>` produces
`data-label="Name"`.

### T5. Empty body cell

An empty `td` still counts as a cell, so the row count still matches. It receives
`role="cell"` and its `data-label`, so the label is drawn with no value under it.
Screen readers announce the column header and then an empty cell, which is the
same behaviour as an ordinary table.

### T6. Empty header cell

A `th` with no text produces no `data-label` for that column, since an empty
attribute would draw an empty line. `role="cell"` is still written on the cells
of that column, and the column is still reachable through table navigation.

### T7. Long unbreakable content

A cell holding a long URL or a long word. `overflow-wrap: break-word` lets it
break, so it does not reintroduce the horizontal scrolling the plugin exists to
remove. Check this one at 320px specifically.

## Falls back to scrolling

These structures are left exactly as they were, and their container is made
reachable and named. Expected markup on the wrapper:

```html
<figure class="wp-block-table table-reflow-scroll" tabindex="0" role="region"
        aria-label="Table, scrollable sideways">
```

When the block has a caption, the caption text is used as the accessible name
instead of the generic one.

| Case | Structure | Why it cannot be stacked |
| --- | --- | --- |
| F1 | No `thead`, or a `thead` with no cell | No column name exists to put next to a value |
| F2 | A `th` inside `tbody` or `tfoot` | Headers are in the first column, so the header of a given cell is not a column header |
| F3 | Any `colspan` on any cell | One cell spans several columns, so it has several labels |
| F4 | Any `rowspan` on any cell | Rows below would shift by one column and be mislabelled |
| F5 | A body row with more or fewer cells than the header row | The label to cell mapping would drift down the row |
| F6 | More than one `tr` in `thead` | A cell would map to several stacked header levels |
| F7 | A table nested inside a cell | Two tables share one column mapping |
| F8 | No explicit `<tbody>` tag in the markup | An implied `tbody` has no tag to carry `role="rowgroup"` |
| F9 | A `td` used in the header row | It carries no header semantics to borrow a label from |

| Context | NVDA + Firefox | VoiceOver + Safari |
| --- | --- | --- |
| Reaching the container | <kbd>Tab</kbd> reaches the figure and announces the accessible name followed by "region". A visible focus outline is drawn. | <kbd>Tab</kbd> reaches it and announces "region, <name>". |
| Inside | The table keeps its native semantics untouched, so table navigation behaves exactly as it would without the plugin. | Same. |
| Scrolling | Arrow keys scroll the region horizontally once it holds focus. | Same. |

Why the container has to be focusable: a region that scrolls but cannot receive
focus is unreachable for anyone navigating by keyboard, which fails 2.1.1
Keyboard. The failure mode is silent, which is what makes it common.

## Left completely alone

| Case | Structure | Expected |
| --- | --- | --- |
| N1 | Option off | Markup returned untouched, stylesheet not enqueued |
| N2 | Any block other than `core/table` | Returned after one string comparison |
| N3 | WordPress below 6.7 | `WP_HTML_Processor` cannot parse table elements, so the plugin is a no-op. The plugin header declares 6.7, so this only happens on a forced install |
| N4 | Block attribute holding an unknown breakpoint | Sanitised back to the default, so a hand-edited attribute cannot inject a class name |

## Known limitations

**The scrollable container is always a tab stop.** The plugin cannot measure
whether a table actually overflows, because that needs JavaScript and there is
none on the front end. A fallback table that happens to fit on screen still adds
one stop to the tab order. A stop that announces a named region is a smaller
problem than content nobody can reach, so the trade is deliberate. Recent Chrome
and Firefox make scroll containers focusable on their own, which will make the
attribute redundant rather than wrong.

**Older browsers announce the label twice.** The alternative text syntax of
`content` landed in Chrome 77, Firefox 127 and Safari 17.4. Below those, the
generated label is exposed to the accessibility tree and is announced in addition
to the column header. The information is still correct, just repeated.

**`role="cell"`, not `role="gridcell"`.** These are static data tables, not
interactive grids. `gridcell` would promise keyboard behaviour the table does not
implement.

**Header detection is structural, not semantic.** A table whose first row holds
`th` elements but is not wrapped in `thead` falls under F1. The block editor
creates the `thead` for you through the **Header section** button, so this mostly
affects tables pasted as raw HTML.
