(() => {
    const editor = document.querySelector('[data-tarhan-editor]');
    if (!editor) return;
    const status = editor.querySelector('[data-editor-status]');
    const groups = [...editor.querySelectorAll('[data-group]')];
    const renumber = (group) => {
        [...group.querySelector('[data-rows]').children].forEach((row, index, rows) => {
            row.querySelector('[data-row-number]').textContent = index + 1;
            row.querySelectorAll('[name]').forEach((field) => {
                const oldId = field.id;
                field.name = field.name.replace(/\[[^\]]+\]\[[^\]]+\](?=\[)/, `[${group.dataset.group}][${index}]`);
                field.id = 'tarhan-' + field.name.replace(/[^a-zA-Z0-9_-]/g, '-');
                [...row.querySelectorAll('label')].filter(label => label.htmlFor === oldId).forEach(label => { label.htmlFor = field.id; });
            });
            row.querySelector('[data-move="up"]').disabled = index === 0;
            row.querySelector('[data-move="down"]').disabled = index === rows.length - 1;
        });
    };
    groups.forEach(renumber);
    editor.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) return;
        const group = button.closest('[data-group]');
        if (button.hasAttribute('data-add-row')) {
            const fragment = group.querySelector('template').content.cloneNode(true);
            group.querySelector('[data-rows]').append(fragment);
            renumber(group);
            group.querySelector('[data-rows]').lastElementChild.querySelector('input:not([type="hidden"]), textarea, button')?.focus();
            status.textContent = 'Запись добавлена. Сохраните страницу, чтобы применить изменения.';
        } else if (button.hasAttribute('data-remove-row')) {
            const row = button.closest('.tarhan-row');
            if (!window.confirm('Удалить эту запись? Изменение применится после сохранения страницы.')) return;
            row.remove();
            renumber(group);
            group.querySelector('[data-add-row]').focus();
            status.textContent = 'Запись удалена из формы. Сохраните страницу, чтобы применить изменение.';
        } else if (button.dataset.move) {
            const row = button.closest('.tarhan-row');
            if (button.dataset.move === 'up' && row.previousElementSibling) row.previousElementSibling.before(row);
            if (button.dataset.move === 'down' && row.nextElementSibling) row.nextElementSibling.after(row);
            renumber(group);
            status.textContent = 'Порядок изменён. Сохраните страницу.';
        } else if (button.hasAttribute('data-clear-image')) {
            const box = button.closest('.tarhan-image');
            box.querySelector('input').value = '';
            box.querySelector('img').removeAttribute('src');
            box.querySelector('img').hidden = true;
        } else if (button.hasAttribute('data-pick-image')) {
            const box = button.closest('.tarhan-image');
            const picker = wp.media({title: 'Фотография', library: {type: 'image'}, button: {text: 'Использовать фотографию'}, multiple: false});
            picker.on('select', () => {
                const photo = picker.state().get('selection').first().toJSON();
                box.querySelector('input').value = photo.id;
                box.querySelector('img').src = photo.sizes?.thumbnail?.url || photo.url;
                box.querySelector('img').hidden = false;
            });
            picker.open();
        }
    });
})();
