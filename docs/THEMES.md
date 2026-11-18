# Making an Agora theme

An Agora theme is **one CSS file**. Most themes only change colors.

## The quick way (no code)

1. Admin → **Appearance**: pick the theme closest to what you want.
2. Tick **Use my own colors**, choose the four main colors for light and dark mode, and save.
3. Click **Download as a theme file**. That file is your theme: share it, or upload it to another Agora forum.

## By hand

1. Copy `themes/agora.css` and rename it, e.g. `sunset.css` (lowercase letters, numbers, dashes).
2. Edit the header:

   ```css
   /* Agora theme
      Name: Sunset
      Author: Your name
      Description: Warm oranges and purples.
   */
   ```

3. Change the variables. `:root { … }` is light mode; `:root[data-theme="dark"] { … }` is dark mode.
4. Upload it in Admin → Appearance → **Install a theme**, or drop it in the `themes/` folder.

## Variables

| Variable | What it colors |
| --- | --- |
| `--bg` | Page background |
| `--surface` | Cards, header and posts |
| `--surface-2` | Subtle fills (hover, code blocks) |
| `--text` / `--text-muted` | Main text / dates and hints |
| `--border` | Lines around cards |
| `--accent` / `--accent-hover` | Buttons and links |
| `--accent-text` | Text on accent buttons |
| `--accent-soft` | Light accent fills (badges, selected items) |
| `--font` / `--font-heading` | Body and heading fonts |
| `--radius` | Corner roundness, e.g. `4px` or `16px` |
| `--shadow` | Card shadow, or `none` |

After the variables you can add any CSS, e.g. `.site-name { text-transform: uppercase; }`.

## Tips

- Keep text readable. The Appearance page warns you when text and cards don't contrast enough.
- Use fonts people already have (like Georgia), or font files hosted on your own forum. Outside font services are blocked for privacy.
