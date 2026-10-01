import '../scss/registration.scss';
import { initPageNavigation } from './components/page-navigation.js';

initPageNavigation();
// WordPress supplies same-origin endpoints and nonce. Never put API keys here.
const config = window.tarhanRegistration || {};
const form = document.querySelector('.registration__form');
const fields = form.elements;
const submit = form.querySelector('[type="submit"]');
const message = form.querySelector('.registration__message');
const errors = form.querySelector('.registration__errors');
const results = document.querySelector('#address-results');
const addressStatus = document.querySelector('#address-status');
const addressNames = ['region', 'district', 'locality', 'street', 'house'];
let selectedAddress = null;
let queryVersion = 0;
let queryTimer;
let controller;
let submitting = false;

const localEndpoint = (value) => {
    if (!value) return null;
    try {
        const url = new URL(value, location.href);
        return url.origin === location.origin ? url.href : null;
    } catch { return null; }
};
const addressEndpoint = localEndpoint(config.addressEndpoint);
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
    ['first_name', 'last_name', 'middle_name', 'parent_first_name', 'parent_last_name', 'parent_middle_name'].forEach(name => {
        const input = fields[name];
        if (input.value && !/^[\p{L}\p{M}]+(?:[ '\u2019-][\p{L}\p{M}]+)*$/u.test(input.value.trim())) {
            input.setCustomValidity('Введите имя буквами, без цифр и специальных символов.');
        }
    });
    ['phone', 'parent_phone'].forEach(name => {
        const value = fields[name].value.trim();
        if (value && (!/^[+\d\s()-]+$/.test(value) || !/^\d{10,15}$/.test(value.replace(/\D/g, '')))) {
            fields[name].setCustomValidity('Введите телефон: от 10 до 15 цифр.');
        }
    });
    if (fields.password.value && !fields.password.value.trim()) fields.password.setCustomValidity('Пароль не может состоять только из пробелов.');
    if (fields.password_confirmation.value !== fields.password.value) {
        fields.password_confirmation.setCustomValidity('Пароли не совпадают.');
    }
    if (!selectedAddress || fields.address_query.value !== selectedAddress.value) {
        fields.address_query.setCustomValidity('Выберите адрес из справочника.');
    }
    fields.consent_file.required = isMinor();
    form.querySelector('[data-file-required]').hidden = !isMinor();
    const file = fields.consent_file.files[0];
    if (file && (!/\.(pdf|jpe?g|png)$/i.test(file.name) ||
        !['application/pdf', 'image/jpeg', 'image/png'].includes(file.type) || file.size > 10 * 1024 * 1024 || file.size === 0)) {
        fields.consent_file.setCustomValidity('Выберите PDF, JPG или PNG размером до 10 МБ, не пустой.');
    }
    const valid = [...form.querySelectorAll('input, select')].every(input => input.validity.valid);
    submit.disabled = !valid || !registerEndpoint || !addressEndpoint || submitting;
    const school = [fields.school_form.value, fields.school_type.value, fields.school_number.value ? '№ ' + fields.school_number.value : ''].filter(Boolean);
    form.querySelector('.registration__school').textContent = school.join(' ');
    if (!registerEndpoint || !addressEndpoint) {
        message.textContent = 'Регистрация пока недоступна: ожидается подключение сервера и адресного справочника.';
    } else if (!submitting) {
        message.textContent = valid ? 'Все обязательные поля заполнены.' : 'Заполните обязательные поля, выберите адрес и проверьте согласие.';
    }
    return valid;
};

addressStatus.textContent = addressEndpoint
    ? 'Введите не менее трёх символов и выберите адрес из результатов.'
    : 'Адресный справочник пока не подключён. Произвольные названия не принимаются.';

fields.address_query.addEventListener('input', () => {
    selectedAddress = null;
    addressNames.forEach(name => fields[name].value = '');
    clearTimeout(queryTimer);
    controller?.abort();
    const version = ++queryVersion;
    results.replaceChildren();
    results.hidden = true;
    validate();
    const query = fields.address_query.value.trim();
    if (!addressEndpoint || query.length < 3) return;
    queryTimer = setTimeout(async () => {
        controller = new AbortController();
        addressStatus.textContent = 'Ищем адрес…';
        try {
            const response = await fetch(addressEndpoint, {
                method: 'POST', credentials: 'same-origin', signal: controller.signal,
                headers: { 'Content-Type': 'application/json', ...(config.nonce ? { 'X-WP-Nonce': config.nonce } : {}) },
                body: JSON.stringify({ query })
            });
            if (!response.ok) throw new Error();
            const data = await response.json();
            if (version !== queryVersion) return;
            const suggestions = Array.isArray(data.suggestions) ? data.suggestions : [];
            const usable = suggestions.filter(item => item.value && item.data?.house_fias_id);
            results.replaceChildren();
            usable.forEach(item => {
                const li = document.createElement('li');
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = item.value;
                button.addEventListener('click', () => {
                    selectedAddress = item;
                    fields.address_query.value = item.value;
                    const d = item.data;
                    fields.region.value = d.region_with_type || '';
                    fields.district.value = d.area_with_type || d.city_district_with_type || 'Не указан в справочнике';
                    fields.locality.value = d.settlement_with_type || d.city_with_type || '';
                    fields.street.value = d.street_with_type || 'Без улицы';
                    fields.house.value = [d.house_type, d.house, d.block_type, d.block].filter(Boolean).join(' ');
                    results.hidden = true;
                    addressStatus.textContent = 'Адрес выбран из справочника.';
                    validate();
                    fields.apartment.focus();
                });
                li.append(button);
                results.append(li);
            });
            results.hidden = !usable.length;
            addressStatus.textContent = usable.length ? 'Выберите свой адрес.' : 'Уточните адрес до дома. Подтверждённых адресов не найдено.';
        } catch (error) {
            if (version !== queryVersion || error.name === 'AbortError') return;
            addressStatus.textContent = 'Не удалось загрузить справочник. Попробуйте ещё раз.';
        }
    }, 350);
});

form.addEventListener('input', validate);
form.addEventListener('change', validate);
form.addEventListener('focusout', (event) => {
    const input = event.target;
    if (!input.matches('input, select')) return;
    validate();
    input.setAttribute('aria-invalid', String(!input.validity.valid));
});
fields.show_password.addEventListener('change', () => {
    const type = fields.show_password.checked ? 'text' : 'password';
    fields.password.type = type;
    fields.password_confirmation.type = type;
});
form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (submitting) return;
    errors.hidden = true;
    if (!validate()) { form.reportValidity(); return; }
    if (!registerEndpoint || !addressEndpoint) return;
    submitting = true;
    submit.disabled = true;
    form.setAttribute('aria-busy', 'true');
    const payload = new FormData(form);
    payload.delete('show_password');
    payload.set('address_fias_id', selectedAddress.data.house_fias_id);
    try {
        const response = await fetch(registerEndpoint, {
            method: 'POST', credentials: 'same-origin',
            headers: config.nonce ? { 'X-WP-Nonce': config.nonce } : {},
            body: payload
        });
        const data = await response.json();
        if (!response.ok || data.success !== true) throw new Error('Не удалось зарегистрироваться. Проверьте данные или обратитесь к организаторам.');
        message.textContent = 'Регистрация подтверждена сервером. Теперь можно войти.';
        form.querySelectorAll('input, select, button').forEach(input => input.disabled = true);
    } catch (error) {
        submitting = false;
        validate();
        errors.textContent = 'Не удалось подтвердить регистрацию. Проверьте соединение или обратитесь к организаторам.';
        errors.hidden = false;
        errors.focus();
    } finally { form.removeAttribute('aria-busy'); }
});
validate();

