import '../scss/registration.scss';
import { initPageNavigation } from './components/page-navigation.js';
import { initRegistrationAddress } from './components/registration-address.js';

initPageNavigation();
// WordPress supplies same-origin endpoints and nonce. Never put API keys here.
const config = window.tarhanRegistration || {};
const form = document.querySelector('.registration__form');
const fields = form.elements;
const submit = form.querySelector('[type="submit"]');
const message = form.querySelector('.registration__message');
const errors = form.querySelector('.registration__errors');
let submitting = false;

const localEndpoint = (value) => {
    if (!value) return null;
    try {
        const url = new URL(value, location.href);
        return url.origin === location.origin ? url.href : null;
    } catch { return null; }
};
const registerEndpoint = localEndpoint(config.registerEndpoint);

fields.birth_date.max = [
    new Date().getFullYear(), String(new Date().getMonth() + 1).padStart(2, '0'),
    String(new Date().getDate()).padStart(2, '0')
].join('-');

const isMinor = () => {
    if (!fields.birth_date.value || !fields.birth_date.validity.valid) return true;
    const birthday = new Date(fields.birth_date.value + 'T00:00:00');
    const today = new Date();
    let age = today.getFullYear() - birthday.getFullYear();
    if (today.getMonth() < birthday.getMonth() ||
        (today.getMonth() === birthday.getMonth() && today.getDate() < birthday.getDate())) age--;
    return age < 18;
};

const validate = () => {
    [...form.querySelectorAll('input, select')].forEach(input => input.setCustomValidity(''));
    ['first_name', 'last_name', 'middle_name', 'parent_first_name', 'parent_last_name', 'parent_middle_name', 'teacher_name'].forEach(name => {
        const input = fields[name];
        if (input.value && !/^[\p{L}\p{M}]+(?:[ '\u2019-][\p{L}\p{M}]+)*$/u.test(input.value.trim())) {
            input.setCustomValidity('Введите имя буквами, без цифр и специальных символов.');
        }
    });
    ['phone', 'parent_phone', 'teacher_phone'].forEach(name => {
        const value = fields[name].value.trim();
        if (value && (!/^\+?[\d\s()-]+$/.test(value) || !/^\d{10,15}$/.test(value.replace(/\D/g, '')))) {
            fields[name].setCustomValidity('Введите телефон: от 10 до 15 цифр.');
        }
    });
    if (fields.password?.value && !fields.password.value.trim()) fields.password.setCustomValidity('Пароль не может состоять только из пробелов.');
    if (fields.password_confirmation && fields.password_confirmation.value !== fields.password.value) {
        fields.password_confirmation.setCustomValidity('Пароли не совпадают.');
    }
    fields.consent_file.required = isMinor();
    form.querySelector('[data-file-required]').hidden = !isMinor();
    const file = fields.consent_file.files[0];
    if (file && (!/\.(pdf|jpe?g|png)$/i.test(file.name) ||
        !['application/pdf', 'image/jpeg', 'image/png'].includes(file.type) || file.size > 10 * 1024 * 1024 || file.size === 0)) {
        fields.consent_file.setCustomValidity('Выберите PDF, JPG или PNG размером до 10 МБ, не пустой.');
    }
    const addressValid = address.validate();
    const valid = addressValid && [...form.querySelectorAll('input, select')].every(input => input.validity.valid);
    form.querySelectorAll('[aria-invalid]').forEach(input => input.setAttribute('aria-invalid', String(!input.validity.valid)));
    submit.disabled = !registerEndpoint || submitting;
    const school = [fields.school_form.value, fields.school_type.value, fields.school_number.value ? '№ ' + fields.school_number.value : ''].filter(Boolean);
    form.querySelector('.registration__school').textContent = school.join(' ');
    if (!registerEndpoint) {
        message.textContent = 'Регистрация пока недоступна: ожидается подключение сервера.';
    } else if (!submitting) {
        message.textContent = valid ? 'Все обязательные поля заполнены.' : 'Заполните обязательные поля, выберите адрес и проверьте согласие.';
    }
    return valid;
};

const address = initRegistrationAddress({ form, config, onChange: () => validate() });

form.addEventListener('input', validate);
form.addEventListener('change', validate);
form.addEventListener('focusout', (event) => {
    const input = event.target;
    if (!input.matches('input, select')) return;
    validate();
    input.setAttribute('aria-invalid', String(!input.validity.valid));
});
fields.show_password?.addEventListener('change', () => {
    const type = fields.show_password.checked ? 'text' : 'password';
    fields.password.type = type;
    fields.password_confirmation.type = type;
});
form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (submitting) return;
    errors.hidden = true;
    if (!validate()) {
        const invalid = [...form.querySelectorAll('input, select')]
            .filter(input => input.willValidate && !input.validity.valid);
        invalid.forEach(input => input.setAttribute('aria-invalid', 'true'));
        errors.textContent = invalid.length
            ? invalid.map(input => {
                const label = input.closest('label')?.querySelector('span')?.textContent.replace(/\s*\*\s*/g, '').trim();
                return `${label || 'Поле формы'}: ${input.validationMessage}`;
            }).join('\n')
            : 'Выберите регион и населённый пункт из подсказок.';
        errors.hidden = false;
        errors.focus();
        form.reportValidity();
        return;
    }
    if (!registerEndpoint) return;
    submitting = true;
    submit.disabled = true;
    form.setAttribute('aria-busy', 'true');
    const payload = new FormData(form);
    payload.delete('show_password');
    address.append(payload);
    try {
        const response = await fetch(registerEndpoint, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-Tarhan-Token': config.token || '', ...(config.nonce ? { 'X-WP-Nonce': config.nonce } : {}) },
            body: payload
        });
        const data = await response.json();
        if (!response.ok || data.success !== true) throw new Error(data.message || 'Не удалось зарегистрироваться. Проверьте данные или обратитесь к организаторам.');
        message.textContent = 'Регистрация прошла успешно. Переходим на страницу теста…';
        if (fields.password) fields.password.value = '';
        if (fields.password_confirmation) fields.password_confirmation.value = '';
        if (localEndpoint(data.next_url)) { location.assign(localEndpoint(data.next_url)); }
        form.querySelectorAll('input, select, button').forEach(input => input.disabled = true);
    } catch (error) {
        submitting = false;
        validate();
        errors.textContent = error.message || 'Не удалось подтвердить регистрацию. Проверьте соединение или обратитесь к организаторам.';
        errors.hidden = false;
        errors.focus();
    } finally { form.removeAttribute('aria-busy'); }
});
validate();

