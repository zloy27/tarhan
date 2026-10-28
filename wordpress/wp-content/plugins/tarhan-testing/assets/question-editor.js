(() => {
    const options = document.getElementById('tarhan-question-options');
    const correct = document.getElementById('tarhan-question-correct');
    const preview = document.querySelector('[data-question-preview]');
    if (!options || !correct || !preview) return;
    const list = preview.querySelector('[data-question-options-preview]');
    const status = preview.querySelector('[data-question-answer-preview]');
    const update = () => {
        const text = options.value.trim();
        const variants = text ? text.split(/\r\n|[\n\r\v\f\u0085\u2028\u2029]/).map(value => value.trim()) : [];
        list.replaceChildren();
        variants.forEach(value => {
            const item = document.createElement('li');
            item.textContent = value || 'Пустая строка — удалите её или впишите вариант.';
            list.append(item);
        });
        correct.max = String(Math.max(1, variants.length));
        const number = Number(correct.value);
        const valid = /^[1-9][0-9]*$/.test(correct.value) && number <= variants.length && variants[number - 1] !== '';
        if (variants.includes('')) {
            status.textContent = 'Между вариантами есть пустая строка. Исправьте её перед сохранением.';
        } else if (variants.length < 2) {
            status.textContent = 'Добавьте как минимум два варианта ответа.';
        } else if (valid) {
            status.textContent = `Правильный ответ № ${number}: ${variants[number - 1]}`;
        } else {
            status.textContent = `Введите номер правильного ответа от 1 до ${variants.length}.`;
        }
        preview.hidden = false;
    };
    options.addEventListener('input', update);
    correct.addEventListener('input', update);
    update();
})();
