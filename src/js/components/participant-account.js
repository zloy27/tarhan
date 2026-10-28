import { initPageNavigation } from './page-navigation.js';

const config = window.tarhanParticipant;
if (config) {
    // The separate form entries initialize navigation on guest form pages.
    if (config.page === 'essay' || config.registrationClosed || config.loggedIn && !config.renewalForm && ['entrance', 'registration'].includes(config.page)) initPageNavigation();
    const headers = { ...(config.nonce ? { 'X-WP-Nonce': config.nonce } : {}), 'X-Tarhan-Token': config.token };
    const request = async (path, options = {}) => {
        const response = await fetch(config.endpoint + path, { credentials: 'same-origin', ...options, headers: { ...headers, ...options.headers } });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Запрос не выполнен. Повторите позже.');
        return data;
    };
    document.querySelectorAll('[data-participant-logout]').forEach(button => button.addEventListener('click', async () => {
        button.disabled = true;
        try { const data = await request('auth/logout', { method: 'POST' }); location.assign(data.redirect); }
        catch (error) {
            const message = document.querySelector('[data-account-message], [data-essay-message]');
            if (message) message.textContent = error.message;
            button.disabled = false;
        }
    }));
    const root = document.querySelector('[data-essay]');
    if (root) {
        const form = root.querySelector('[data-essay-form]');
        const message = root.querySelector('[data-essay-message]');
        const text = form.elements.text;
        let state;
        let busy = false;
        const render = data => {
            state = data;
            const topic = root.querySelector('[data-essay-topic]');
            topic.textContent = data.rules.topic;
            form.hidden = !data.can_submit;
            const success = root.querySelector('[data-essay-submission]');
            const navigation = root.querySelector('[data-essay-navigation]');
            success.hidden = !data.submission;
            navigation.hidden = !!data.submission;
            topic.hidden = !!data.submission;
            message.hidden = !!data.submission;
            if (data.submission) {
                success.focus({ preventScroll: true });
            } else {
                message.hidden = false;
                message.textContent = !data.allowed ? 'Для сдачи эссе сначала успешно пройдите тест.' : data.can_submit ? 'У вас есть допуск к эссе.' : 'Приём эссе пока закрыт. Сохраните работу у себя и вернитесь позже.';
                root.querySelector('[data-essay-file-hint]').textContent = 'PDF, DOC, DOCX или TXT (UTF-8), не больше ' + (data.rules.max_bytes / 1048576).toLocaleString('ru-RU') + ' МБ.';
            }
            updateCounter();
        };
        const updateCounter = () => { root.querySelector('[data-essay-counter]').textContent = Array.from(text.value.trim()).length + ' / ' + (state?.rules.max_length || 0) + ' символов'; };
        text.addEventListener('input', updateCounter);
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (busy || !state?.can_submit || !form.reportValidity()) return;
            const file = form.elements.essay_file.files[0];
            if ((!!text.value.trim()) === !!file) { message.textContent = 'Отправьте текст или файл — выберите один способ.'; return; }
            if (file && (file.size === 0 || file.size > state.rules.max_bytes)) { message.textContent = 'Проверьте размер файла.'; return; }
            if (!file && Array.from(text.value.trim()).length > state.rules.max_length) { message.textContent = 'Текст превышает допустимую длину.'; return; }
            busy = true;
            form.querySelector('button[type="submit"]').disabled = true;
            form.setAttribute('aria-busy', 'true');
            message.textContent = 'Сохраняем работу…';
            try {
                const payload = new FormData(form);
                payload.set('season_id', config.seasonId || '');
                render(await request('essay/submit', { method: 'POST', body: payload }));
            }
            catch (error) {
                // An interrupted response may still have saved the submission.
                try {
                    const data = await request('essay/status');
                    render(data);
                    if (!data.submission) message.textContent = error.message;
                } catch { message.textContent = 'Не удалось подтвердить сохранение. Проверьте соединение и обновите страницу перед повторной отправкой.'; }
            } finally { busy = false; form.querySelector('button[type="submit"]').disabled = false; form.removeAttribute('aria-busy'); }
        });
        request('essay/status').then(render).catch(error => { message.textContent = error.message; });
    }
}
