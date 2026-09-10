# Noto Sans JP

The documentation site and regenerated Archify figures use the self-hosted
Noto Sans JP variable font at `/fonts/NotoSansJP.woff2`. The face is declared
in `../design-tokens.css`; the Archify generation patch embeds the same bytes
in standalone SVG exports so PNG and SVG rendering do not depend on host fonts.

The source is pinned to the official Google Fonts repository revision
`baa2e5561af8a4873b058859dcfe158bdd033942`:

```text
https://raw.githubusercontent.com/google/fonts/baa2e5561af8a4873b058859dcfe158bdd033942/ofl/notosansjp/NotoSansJP%5Bwght%5D.ttf
```

Measured hashes:

| file | bytes | SHA-256 |
| --- | ---: | --- |
| upstream `NotoSansJP[wght].ttf` | 9,589,900 | `c2f3b4d463500a2ddcd3849cded1fceeb9fd6d1c32e6cbecd568453ba50fc68f` |
| upstream `OFL.txt` | 4,388 | `1c05c68c34f9708415aada51f17e1b0092d2cea709bf4a94cd38114f9e73d7d9` |
| `public/fonts/NotoSansJP.woff2` | 298,584 | `f7ed72f468ec54f8fb3025f460018685c9f00102179fc91e4cbec5f3f5eda5eb` |

The subset contains 801 printable code points from the current guide, website
content, diagram JSON, and Archify viewer controls. The extraction script is
stdlib-only and writes both the sorted `U+XXXX` input and a source-hash
sidecar:

```bash
python3 docs/website/fonts/generate-notosansjp.py \
  --repo-root . \
  --output /tmp/notosansjp.unicodes
```

Generate the WOFF2 with temporary fontTools (4.64.0) and Brotli (1.2.0):

```bash
PYTHONPATH=/tmp/fonttools-lib python3 -m fontTools.subset \
  /tmp/NotoSansJP-wght.ttf \
  --unicodes-file=/tmp/notosansjp.unicodes \
  --flavor=woff2 \
  --layout-features='*' \
  --name-IDs='*' \
  --name-legacy \
  --name-languages='*' \
  --recommended-glyphs \
  --output-file=docs/website/public/fonts/NotoSansJP.woff2
```

The license is retained verbatim as
`public/fonts/NotoSansJP-OFL.txt` (SIL Open Font License 1.1). After
regeneration, verify the output with `sha256sum` and update the provenance
record only when the pinned source or glyph input changes.

## Ubuntu Sans and Ubuntu Mono

The local Ubuntu variable fonts remain available as TTF sources and are also
served as lossless WOFF2 files. Blume uses the WOFF2 variants from
`public/fonts/`; no glyph subsetting or weight-range change was applied.

The conversion used FontTools 4.64.0 and Brotli 1.2.0 with all WOFF2 table
transformations disabled:

```bash
PYTHONPATH=/tmp/blackops-font-compression python3.12 - <<'PY'
from pathlib import Path
from fontTools.ttLib import woff2

font_dir = Path('docs/website/public/fonts')
for stem in ('UbuntuSans', 'UbuntuMono'):
    woff2.compress(
        font_dir / f'{stem}.ttf',
        font_dir / f'{stem}.woff2',
        transform_tables=[],
    )
PY
```

Measured hashes:

| file | bytes | SHA-256 |
| --- | ---: | --- |
| `public/fonts/UbuntuSans.ttf` | 1,072,960 | `28c4c189a44803b1986fd16074187034dc6d94ad35f5e87de13dd0e786b70b73` |
| `public/fonts/UbuntuSans.woff2` | 325,352 | `b1de97dd36b02b2c5125d8df6b99c76053b6c43b871c9f2dfb923b3218623bc1` |
| `public/fonts/UbuntuMono.ttf` | 172,232 | `fbf1e748836994f730e602f7dcf2525564d6d78aa336080cbb73af909d0e08ee` |
| `public/fonts/UbuntuMono.woff2` | 80,480 | `1417c472ce2c5449cc427f2ceb92dedbbd28eb1bcd44fd9b7efb6cb0978d0e80` |
| `public/licenses/Ubuntu-Font-License-1.0.txt` | 28,095 | `bca346a561b9668925ff55af1fcf0e10e65e07b1b40dd057bb4f3ded848ef8cf` |

FontTools verification after loading each WOFF2 reports the same table tags,
glyph order and glyph count as its TTF source: Ubuntu Sans has 1,843 glyphs
and `wdth` (75–100, default 100) plus `wght` (100–800, default 400) axes;
Ubuntu Mono has 1,313 glyphs and `wght` (400–700, default 400). `head`,
`hhea`, and `hmtx` metrics and all 48 Sans / 36 Mono name records compare
equal. The Ubuntu Font Licence 1.0 file is retained verbatim.
