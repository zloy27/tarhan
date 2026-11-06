(() => {
    let serial = 0;
    const mount = (field) => {
        if (field.dataset.richMounted) return;
        field.dataset.richMounted = '1';
        const inline = field.dataset.tarhanRich === 'inline';
        wp.editor.initialize(field.id, {
            mediaButtons: false,
            quicktags: false,
            tinymce: {
                wpautop: false,
                forced_root_block: inline ? false : 'p',
                height: 180,
                menubar: false,
                plugins: 'lists paste textcolor link',
                toolbar1: 'bold italic underline strikethrough | forecolor backcolor | ' +
                    (inline ? '' : 'bullist numlist link unlink | ') + 'removeformat undo redo',
                toolbar2: '',
                paste_as_text: true,
                setup: (instance) => {
                    instance.on('change input undo redo', () => instance.save());
                }
            }
        });
    };
    const prepare = (root = document) => {
        root.querySelectorAll('textarea[data-tarhan-rich]').forEach((field) => {
            if (field.dataset.richPrepared) return;
            field.dataset.richPrepared = '1';
            const oldId = field.id;
            field.id = 'tarhan-rich-' + (++serial);
            field.closest('.tarhan-field, .tarhan-home-field')?.querySelectorAll('label').forEach((label) => {
                if (label.htmlFor === oldId) label.htmlFor = field.id;
            });
            field.addEventListener('focus', () => mount(field), { once: true });
        });
    };
    // TinyMCE iframes must be removed before a row is moved or deleted.
    window.tarhanRichEditor = {
        prepare,
        detach: (root) => root.querySelectorAll('textarea[data-rich-mounted]').forEach((field) => {
            window.tinymce?.get(field.id)?.save();
            wp.editor.remove(field.id);
            delete field.dataset.richMounted;
            field.addEventListener('focus', () => mount(field), { once: true });
        })
    };
    prepare();
    const first = document.querySelector('textarea[data-tarhan-rich]');
    if (first) mount(first);
    document.addEventListener('submit', () => window.tinymce?.triggerSave(), true);
})();
