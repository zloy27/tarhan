import '../scss/password.scss';
import { initPageNavigation } from './components/page-navigation.js';

initPageNavigation();

const form = document.querySelector('[data-password-form]');
if (form) {
    const config = window.tarhanParticipant;
    const submit = form.querySelector('[type="submit"]');
    const message = form.querySelector('.password__message');
    const mode = form.dataset.passwordForm;
    const params = new URLSearchParams(location.search);
    const key = params.get('key');
    const login = params.get('login');
    if (mode === 'reset' && config) history.replaceState(null, '', location.pathname);
    if (config && (mode !== 'reset' || key && login)) {
        submit.disabled = false;
        message.textContent = '';
    } else if (mode === 'reset' && config) message.textContent = 'Откройте ссылку из письма или запросите новую.';
    let busy = false;
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!config) { message.textContent = 'Сервис ещё не подключён.'; return; }
        if (busy || !form.reportValidity()) return;
        if (mode === 'reset' && (!key || !login)) return;
        busy = true;
        submit.disabled = true;
        form.setAttribute('aria-busy', 'true');
        const payload = Object.fromEntries(new FormData(form));
        if (mode === 'reset') Object.assign(payload, { key, login });
        message.textContent = 'Обрабатываем запрос…';
        try {
            const response = await fetch(config.endpoint + 'auth/' + mode, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-Tarhan-Token': config.token, ...(config.nonce ? { 'X-WP-Nonce': config.nonce } : {}) },
                body: JSON.stringify(payload)
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Не удалось выполнить запрос.');
            if (mode === 'reset') {
                form.reset();
                location.assign(data.redirect);
            } else message.textContent = data.message;
        } catch (error) { message.textContent = error.message; }
        finally { busy = false; submit.disabled = false; form.removeAttribute('aria-busy'); }
    });
    form.querySelectorAll('.password__toggle').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.getAttribute('aria-controls'));
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(show));
            button.setAttribute('aria-label', show ? 'Скрыть пароль' : 'Показать пароль');
        });
    });
    const password = form.elements.password;
    const confirmation = form.elements.password_confirmation;
    if (password && confirmation) {
        password.setAttribute('aria-describedby', 'password-hint');
        form.addEventListener('input', () => {
            confirmation.setCustomValidity(confirmation.value && confirmation.value !== password.value ? 'Пароли не совпадают.' : '');
        });
    }
}

