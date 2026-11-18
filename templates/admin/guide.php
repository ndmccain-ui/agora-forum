<?php
defined('AGORA') or exit;
?>
<section class="card post-body guide">
    <p>An Agora theme is <strong>one CSS file</strong>. Most themes only change colors, which takes about five minutes.</p>

    <h2>The quick way (no code)</h2>
    <ol>
        <li>Go to <a href="<?= e(url('/admin/appearance')) ?>">Appearance</a>, pick the theme closest to what you want.</li>
        <li>Tick <em>Use my own colors</em> and choose the four main colors for light and dark mode. Save.</li>
        <li>Click <em>Download as a theme file</em>. That file is your theme — share it, or upload it to another Agora forum.</li>
    </ol>

    <h2>Writing a theme by hand</h2>
    <ol>
        <li>Download a theme (or copy <code>themes/agora.css</code> from Agora's files).</li>
        <li>Rename it, e.g. <code>sunset.css</code> — lowercase letters, numbers and dashes.</li>
        <li>Edit the header so Agora knows what it's called:
<pre><code>/* Agora theme
   Name: Sunset
   Author: Your name
   Description: Warm oranges and purples.
*/</code></pre></li>
        <li>Change the colors. The <code>:root { … }</code> block is light mode; <code>:root[data-theme="dark"] { … }</code> is dark mode.</li>
        <li>Upload it on the Appearance page (or put it in the <code>themes/</code> folder), then choose it.</li>
    </ol>

    <h2>What you can change</h2>
    <table class="guide-table">
        <tr><td><code>--bg</code></td><td>Page background</td></tr>
        <tr><td><code>--surface</code></td><td>Cards, header and posts</td></tr>
        <tr><td><code>--surface-2</code></td><td>Subtle fills (hover, code blocks)</td></tr>
        <tr><td><code>--text</code> / <code>--text-muted</code></td><td>Main text / dates and hints</td></tr>
        <tr><td><code>--border</code></td><td>Lines around cards</td></tr>
        <tr><td><code>--accent</code> / <code>--accent-hover</code></td><td>Buttons and links</td></tr>
        <tr><td><code>--accent-text</code></td><td>Text on accent buttons</td></tr>
        <tr><td><code>--accent-soft</code></td><td>Light accent fills (badges, selected items)</td></tr>
        <tr><td><code>--font</code> / <code>--font-heading</code></td><td>Body and heading fonts</td></tr>
        <tr><td><code>--radius</code></td><td>How rounded corners are (e.g. <code>4px</code> or <code>16px</code>)</td></tr>
        <tr><td><code>--shadow</code></td><td>Card shadow (or <code>none</code>)</td></tr>
    </table>
    <p>After the variables you can add any CSS you like — for example <code>.site-name { text-transform: uppercase; }</code>.</p>

    <h2>Tips</h2>
    <ul>
        <li>Keep text readable: the Appearance page warns you if text and cards are too similar.</li>
        <li>Fonts must be ones people already have (like Georgia) or files hosted on your own forum — outside font services are blocked for privacy.</li>
    </ul>
</section>
