document.addEventListener('click', (event) => {
  const button = event.target.closest('[data-tarhan-media]');
  if (!button || typeof wp === 'undefined' || !wp.media) return;
  const input = button.parentElement.querySelector('input');
  const frame = wp.media({ title: 'Выберите файл', button: { text: 'Использовать файл' }, multiple: false });
  frame.on('select', () => {
    const file = frame.state().get('selection').first().toJSON();
    input.value = file.url || '';
    input.dispatchEvent(new Event('change', { bubbles: true }));
  });
  frame.open();
});
