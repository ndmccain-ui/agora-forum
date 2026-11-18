/* Agora Forum — light/dark theme switch.
   Loaded in <head> so the right colors apply before the page draws (no flash).
   Sets <html data-theme="light|dark"> so themes only need :root and :root[data-theme="dark"]. */
(function () {
  var KEY = 'agora-theme';
  var ORDER = ['auto', 'light', 'dark'];
  var LABELS = { auto: 'automatic', light: 'light', dark: 'dark' };
  var root = document.documentElement;
  var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

  function forumDefault() {
    var d = root.getAttribute('data-default-theme');
    return ORDER.indexOf(d) !== -1 ? d : 'auto';
  }
  function read() {
    var saved = null;
    try { saved = localStorage.getItem(KEY); } catch (e) { /* storage blocked */ }
    return ORDER.indexOf(saved) !== -1 ? saved : forumDefault();
  }
  function save(value) {
    try { localStorage.setItem(KEY, value); } catch (e) { /* private mode: ignore */ }
  }
  function apply(choice) {
    var resolved = choice === 'auto' ? (media && media.matches ? 'dark' : 'light') : choice;
    root.setAttribute('data-theme', resolved);
    root.setAttribute('data-theme-choice', choice);
  }

  apply(read());
  if (media) {
    var onChange = function () { if (read() === 'auto') apply('auto'); };
    if (media.addEventListener) media.addEventListener('change', onChange); else if (media.addListener) media.addListener(onChange);
  }

  document.addEventListener('DOMContentLoaded', function () {
    var buttons = document.querySelectorAll('[data-theme-toggle]');
    function label() {
      var current = read();
      buttons.forEach(function (b) { b.setAttribute('aria-label', 'Color theme: ' + LABELS[current]); });
    }
    buttons.forEach(function (button) {
      button.addEventListener('click', function () {
        var next = ORDER[(ORDER.indexOf(read()) + 1) % ORDER.length];
        save(next);
        apply(next);
        label();
      });
    });
    label();
  });
})();
