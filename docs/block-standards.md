# Block standards

The rules every custom block in this repository follows. Reviewers use this page as the checklist for any pull request that adds or changes a block.

## Where things live

| What | Where | Why |
| --- | --- | --- |
| Post types, taxonomies, queries | `web/app/mu-plugins/northwind-core/src` | Content must survive a theme change |
| ACF field groups | `web/app/mu-plugins/northwind-core/acf-json` | Versioned, reviewed, identical in every environment |
| Block definition, template, styles | `web/app/themes/northwind/blocks/<block>/` | Presentation belongs to the theme |
| Design tokens | `web/app/themes/northwind/theme.json` | One source for colour, type and spacing |

A block folder contains exactly three files:

```
blocks/stats/
  block.json    name, title, supports, "acf": { "blockVersion": 3, "renderTemplate": "render.php" }
  render.php    markup
  style.css     styles, loaded only on pages that use the block
```

`functions.php` registers every folder in `blocks/` automatically. There is no build step.

## block.json

- `name` is `northwind/<block>`, `category` is `northwind`, `textdomain` is `northwind`.
- `apiVersion` 3 and ACF `blockVersion` 3.
- Set `"html": false`. Turn on `align` and `anchor` only when the block needs them.
- `"customClassName": false`: styling comes from the block, not from ad hoc classes.
- Load styles with `"style": "file:./style.css"`, never with `wp_enqueue_style` in PHP.

## Field groups

- Create and edit field groups in the ACF admin in DDEV. ACF writes the JSON into `acf-json`; commit it.
- Field keys are readable and stable: `field_nw_<block>_<field>`. Never rename a key once it has shipped; data is stored against it.
- Field names are short and lower case (`heading`, `stats`). Read them with `get_field()` in the template.
- Set limits (`min`, `max`) where the design has them, so editors cannot build what the design cannot show.
- The ACF admin is hidden in production (`AcfJson::show_admin()`). Field changes travel through pull requests.

## render.php

The template receives `$block`, `$is_preview` and `$post_id` from ACF.

**Escaping.** Escape everything, as late as possible: `esc_html()` for text, `esc_url()` for links, `esc_attr()` for attributes. The only unescaped `echo` allowed is `block_wrapper_attributes()`, which escapes internally; mark it with a `phpcs:ignore` comment that says so.

**Wrapper.** Build the outer element with `Northwind\Theme\block_wrapper_attributes( $block, 'nw-<block>' )`. It adds the BEM base class, the alignment class and the anchor, and behaves the same in the editor and on the front end.

**Empty states.** A block with nothing to show renders nothing on the front end: no empty section, no stray heading. In the editor, call `Northwind\Theme\editor_hint()` with one sentence that says what is missing, so the block stays visible and clickable.

```php
if ( ! $nw_stats ) {
	if ( $is_preview ) {
		editor_hint( __( 'Stats: add at least one figure with a number and a label.', 'northwind' ) );
	}

	return;
}
```

**Queries** belong in `northwind-core` (see `Stories::for_sector()`), not in the template. Templates call one method and loop over the result.

**Strings** are translatable with the `northwind` text domain.

## Accessible markup

Target: WCAG 2.2 AA.

- A block with a heading is a `<section>` labelled by it: `aria-labelledby` points at the `<h2>`'s ID (`block_element_id( $block, 'heading' )`).
- Headings follow the page outline. Blocks start at `h2`; items inside them use `h3`.
- Lists of things are `<ul role="list">`. The `role` keeps list semantics in Safari once `list-style: none` is set.
- **Cards have one link.** Put the link on the title and stretch it over the card with `::after`. Screen readers then announce each card once, and keyboard users tab once per card. Show the focus ring on the card with `:focus-within`.
- Never rely on colour alone. The current region in the switcher has `aria-current`, a border and bold text.
- Interactive targets are at least 24 by 24 CSS pixels (WCAG 2.5.8).
- Prefer links and plain HTML over JavaScript. None of the current blocks needs a script.

## CSS

- Class names follow BEM with an `nw-` prefix: `.nw-story-card`, `.nw-story-card__title`, `.nw-story-card--featured`.
- Use theme.json tokens only: `var(--wp--preset--color--primary)`, `var(--wp--preset--spacing--50)`, `var(--wp--preset--font-size--large)`, `var(--wp--custom--radius)`. If a value is missing, add a token to theme.json first.
- No inline `style` attributes from PHP.
- Layout with grid or flex and intrinsic sizing (`repeat(auto-fill, minmax(min(100%, 18rem), 1fr))`), so blocks work at any width without breakpoints.
- Respect `prefers-reduced-motion` for any animation (base.css already caps transitions).

## Review checklist

- [ ] Folder contains `block.json`, `render.php`, `style.css` and nothing else
- [ ] Field group JSON committed, keys follow `field_nw_<block>_<field>`
- [ ] All output escaped; `ddev composer lint` passes
- [ ] Renders nothing when empty; shows an editor hint in the editor
- [ ] Section labelled by its heading; heading levels fit the page
- [ ] Cards have a single link; focus is visible on the card
- [ ] Only theme.json tokens in CSS; no inline styles
- [ ] Keyboard-only walk-through done; a page using the block added to `tests/a11y/a11y.spec.js`, and `scripts/a11y.sh` passes
- [ ] Editor guide updated if editors will use the block
