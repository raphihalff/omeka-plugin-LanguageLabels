# Language Labels

An [Omeka Classic](https://omeka.org/classic/) plugin that shows language labels
(`EN`, `FR`, or full names) over item thumbnails on browse pages.

- Reads Dublin Core *Language* by default, or any other element.
- Recognises ISO 639 codes and names: `fre`, `fr` and `French` are one language.
- Gives each language its own colour, optionally derived from your theme's colour.
- Works with right-to-left pages and inherits the theme's font.
- Leaves the theme's thumbnail layout untouched. Without JavaScript, or for items
  without an image, labels appear inline.

## Requirements

Omeka Classic 2.0 or later.

## Installation

1. Download the latest release ZIP from the
   [releases page](https://github.com/raphihalff/plugin-LanguageLabels/releases).
2. Unzip it into your Omeka `plugins/` folder. The folder must be named `LanguageLabels`.
3. In the admin, go to **Plugins** and click **Install** next to Language Labels.

## Configuration

Under **Plugins → Language Labels → Configure**:

| Setting | Description |
|---|---|
| Language element | Element to read. Separate several languages with separate values, or with `;` `,` or `\|`. |
| Show as | Short code (`EN`), language name (`English`), or the value as entered. |
| Position | A fixed corner, or *automatic* top/bottom, which follows the page's reading direction. |
| Maximum labels | Further languages collapse into `+N`. `0` for no limit. |
| Colour by language | One colour per language. When off, all labels use the label colours. |
| Base colour | Hex colour, or a theme variable with a hex fallback, e.g. `var(--primary-color, #76D7C4)`. |
| Base language | Optional language shown in exactly the base colour. |
| Colour priority | Languages listed first get the most distinct colours. |
| Custom labels | `value = label` lines. An empty label hides the value, e.g. `zxx =`. |
| Show in admin | Also label thumbnails on the admin Browse Items page. |

Region subtags are kept: `en-GB` shows as `EN-GB` or "English (GB)". About 60
common languages are built in; add others with custom labels.

## Theming

- **Markup:** copy `views/shared/common/language-labels.php` to your theme's
  `common/` folder. Keep the `language-labels` and `language-labels--inline`
  classes, and `dir="auto"` on each label.
- **Styles:** target `.language-label`, or set `--language-label-bg` and
  `--language-label-fg`.
- **Thumbnail detection:** the script looks for the nearest `.item`, `.record`,
  `.hentry`, `tr`, `article` or `li`, then the first image in it. Extend these with
  the `language_labels_containers` and `language_labels_images` filters.
- **Themes without the browse hook:** call `<?php echo language_labels($item); ?>`
  in the item markup. Don't combine it with the hook on the same page.
- **Dynamically loaded items:** call `window.LanguageLabels.refresh()` after
  adding them.

## How it works

Labels are printed through the `public_items_browse_each` hook (and
`admin_items_browse_simple_each`). A script then positions each list over its
item's thumbnail, measuring the image instead of wrapping it.

Colours rotate the base colour's hue. Modern browsers compute this from the live
theme colour; older ones use values precomputed from the hex.

## Support

Report issues at
[github.com/raphihalff/plugin-LanguageLabels/issues](https://github.com/raphihalff/plugin-LanguageLabels/issues).

## License

[GNU General Public License v3.0](LICENSE)
