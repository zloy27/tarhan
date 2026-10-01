import '../scss/password.scss';
import { initPageNavigation } from './components/page-navigation.js';

initPageNavigation();

const form = document.querySelector('[data-password-form]');
if (form) {
    // Реальная отправка и проверка ссылки будут подключены на стороне WordPress.
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        form.querySelector('.password__message').textContent = 'Сервис ещё не подключён. Обратитесь к организаторам.';
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

