function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
if (window.jQuery) {
  jQuery.ajaxPrefilter(function (options, original, xhr) {
    if (!options.crossDomain && !/^(GET|HEAD|OPTIONS)$/i.test(options.type)) {
      xhr.setRequestHeader('X-CSRF-Token', document.querySelector('meta[name="csrf-token"]')?.content || '');
    }
  });
  jQuery(document).ajaxError(function (event, xhr) {
    alert(xhr.responseJSON?.message || xhr.responseJSON?.error || 'Request se nepodařilo dokončit. Obnovte stránku a zkuste to znovu.');
  });
}
document.addEventListener('click', function (event) {
  const button = event.target.closest('[data-copy]');
  if (button) navigator.clipboard.writeText(button.dataset.copy).catch(() => alert('Copying failed.'));
});
