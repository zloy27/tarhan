import '../scss/entrance.scss';
import { initPageNavigation } from './components/page-navigation.js';

initPageNavigation();
const form = document.querySelector('.entrance__item--box');
const password = document.querySelector('#password');
const toggle = document.querySelector('.entrance__password--toggle');
const message = document.querySelector('.entrance__message');

toggle.addEventListener('click', () => {
    const showPassword = password.type === 'password';
    password.type = showPassword ? 'text' : 'password';
    toggle.setAttribute('aria-pressed', String(showPassword));
    toggle.setAttribute('aria-label', showPassword ? 'Скрыть пароль' : 'Показать пароль');
});

let submitting = false;
form.addEventListener('submit', async (event) => {
    event.preventDefault();
    message.hidden = false;
    const config = window.tarhanParticipant;
    if (!config) { message.textContent = 'Вход пока не подключён к серверу.'; return; }
    if (submitting || !form.reportValidity()) return;
    submitting = true;
    const button = form.querySelector('[type="submit"]');
    button.disabled = true;
    form.setAttribute('aria-busy', 'true');
    message.textContent = 'Входим…';
    try {
        const response = await fetch(config.endpoint + 'auth/login', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-Tarhan-Token': config.token },
            body: JSON.stringify({ email: form.elements.username.value, password: password.value })
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Не удалось войти. Повторите позже.');
        password.value = '';
        location.assign(data.redirect);
    } catch (error) { message.textContent = error.message; }
    finally { submitting = false; button.disabled = false; form.removeAttribute('aria-busy'); }
});
