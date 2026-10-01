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

form.addEventListener('submit', (event) => {
    event.preventDefault();
    message.hidden = false;
    message.textContent = 'Вход пока недоступен. Обратитесь к организаторам через раздел «Контакты».';
});
