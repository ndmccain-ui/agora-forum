/* Agora Forum — small enhancements. Every page works without this file. */
(function () {
  'use strict';

  function csrfToken(form) {
    var input = form && form.querySelector('input[name="_token"]');
    return input ? input.value : '';
  }

  /* ----- Write / Preview tabs on post editors ----- */
  document.querySelectorAll('[data-editor]').forEach(function (editor) {
    var tabs = editor.querySelector('.editor__tabs');
    var textarea = editor.querySelector('textarea');
    var preview = editor.querySelector('[data-editor-preview]');
    var form = editor.closest('form');
    if (!tabs || !textarea || !preview || !form || !window.fetch) return;
    tabs.hidden = false;
    var previewUrl = document.body.getAttribute('data-preview-url');

    function select(name) {
      tabs.querySelectorAll('[data-editor-tab]').forEach(function (b) {
        b.setAttribute('aria-selected', String(b.getAttribute('data-editor-tab') === name));
      });
      var previewing = name === 'preview';
      textarea.hidden = previewing;
      preview.hidden = !previewing;
      if (!previewing) { textarea.focus(); return; }

      preview.style.minHeight = textarea.offsetHeight + 'px';
      preview.innerHTML = '<p class="lede">Loading preview…</p>';
      var body = new URLSearchParams();
      body.set('_token', csrfToken(form));
      body.set('body', textarea.value);
      fetch(previewUrl, { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
        .then(function (html) { preview.innerHTML = html; })
        .catch(function () { preview.innerHTML = '<p class="lede">Preview isn\'t available right now.</p>'; });
    }

    tabs.addEventListener('click', function (event) {
      var button = event.target.closest('[data-editor-tab]');
      if (button) select(button.getAttribute('data-editor-tab'));
    });
  });

  /* ----- Click a tag to add it to the tags box ----- */
  document.querySelectorAll('[data-tag-picker]').forEach(function (picker) {
    var input = document.getElementById('f-tags');
    if (!input) return;
    picker.hidden = false;

    function current() {
      return input.value.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
    }
    function refresh() {
      var chosen = current().map(function (s) { return s.toLowerCase(); });
      picker.querySelectorAll('[data-tag]').forEach(function (b) {
        b.setAttribute('aria-pressed', String(chosen.indexOf(b.getAttribute('data-tag').toLowerCase()) !== -1));
      });
    }
    picker.addEventListener('click', function (event) {
      var button = event.target.closest('[data-tag]');
      if (!button) return;
      var name = button.getAttribute('data-tag');
      var tags = current();
      var at = tags.map(function (s) { return s.toLowerCase(); }).indexOf(name.toLowerCase());
      if (at === -1) tags.push(name); else tags.splice(at, 1);
      input.value = tags.join(', ');
      refresh();
    });
    input.addEventListener('input', refresh);
    refresh();
  });

  /* ----- Quote: add the post to the reply box on this page instead of reloading ----- */
  document.addEventListener('click', function (event) {
    var link = event.target.closest('[data-quote]');
    var box = document.querySelector('.reply textarea');
    if (!link || !box || !window.fetch) return;
    event.preventDefault();
    fetch(link.getAttribute('data-quote'), { credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
      .then(function (text) {
        var editor = box.closest('[data-editor]');
        var writeTab = editor && editor.querySelector('[data-editor-tab="write"]');
        if (writeTab && box.hidden) writeTab.click();
        var before = box.value.replace(/\s+$/, '');
        box.value = (before ? before + '\n\n' : '') + text;
        box.focus();
        box.setSelectionRange(box.value.length, box.value.length);
        box.scrollIntoView({ block: 'center', behavior: 'smooth' });
      })
      .catch(function () { window.location.href = link.href; });
  });

  /* ----- "Are you sure?" for dangerous buttons ----- */
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.getAttribute('data-confirm'))) event.preventDefault();
    });
  });

  document.querySelectorAll('button[data-confirm-click]').forEach(function (button) {
    button.addEventListener('click', function (event) {
      if (!window.confirm(button.getAttribute('data-confirm-click'))) event.preventDefault();
    });
  });

  /* ----- Checkboxes that show/hide a section ----- */
  document.querySelectorAll('[data-toggle-target]').forEach(function (checkbox) {
    var target = document.querySelector(checkbox.getAttribute('data-toggle-target'));
    if (!target) return;
    function sync() { target.hidden = !checkbox.checked; }
    checkbox.addEventListener('change', sync);
    sync();
  });

  /* ----- Don't lose a half-written post by accident ----- */
  document.querySelectorAll('[data-editor] textarea').forEach(function (textarea) {
    var form = textarea.closest('form');
    var original = textarea.value;
    var submitting = false;
    if (form) form.addEventListener('submit', function () { submitting = true; });
    window.addEventListener('beforeunload', function (event) {
      if (!submitting && textarea.value.trim() !== '' && textarea.value !== original) {
        event.preventDefault();
        event.returnValue = '';
      }
    });
  });
})();
