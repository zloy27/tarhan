export const initRegistrationAddress = ({ form, config, onChange }) => {
    const region = form.elements.region_id;
    const locality = form.elements.locality;
    const district = form.elements.district;
    const results = form.querySelector('#address-results');
    const status = form.querySelector('#address-status');
    const retry = form.querySelector('[data-geography-retry]');
    const localityBox = locality.closest('.registration__locality');
    let endpoint = null;
    try {
        const url = new URL(config.geographyEndpoint, location.href);
        if (config.geographyEndpoint && url.origin === location.origin) endpoint = url.href;
    } catch { /* Static previews have no server directory. */ }
    let selected = null;
    let loaded = false;
    let version = 0;
    let timer;
    let controller;
    const headers = config.nonce ? { 'X-WP-Nonce': config.nonce } : {};
    const hideResults = () => { results.hidden = true; locality.setAttribute('aria-expanded', 'false'); };
    const closeResults = () => { clearTimeout(timer); controller?.abort(); version++; hideResults(); };
    const invalidate = () => {
        selected = null;
        district.value = '';
        clearTimeout(timer);
        controller?.abort();
        version++;
        results.replaceChildren();
        hideResults();
    };
    const fetchJson = async (url, options = {}) => {
        const response = await fetch(url, { credentials: 'same-origin', headers, ...options });
        const data = await response.json().catch(() => null);
        if (!response.ok || !data) throw new Error(data?.message || 'Не удалось загрузить справочник. Попробуйте ещё раз.');
        return data;
    };
    const loadRegions = async () => {
        retry.hidden = true;
        region.disabled = true;
        loaded = false;
        invalidate();
        locality.disabled = true;
        locality.value = '';
        if (!endpoint) {
            status.textContent = 'Выбор региона и населённого пункта доступен на рабочем сайте регистрации.';
            return;
        }
        status.textContent = 'Загружаем регионы…';
        try {
            const data = await fetchJson(endpoint + 'regions');
            if (!Array.isArray(data.regions) || !data.regions.length) throw new Error('Справочник регионов пуст. Обратитесь к организаторам.');
            const fragment = document.createDocumentFragment();
            const empty = document.createElement('option');
            empty.value = ''; empty.textContent = 'Выберите регион'; fragment.append(empty);
            data.regions.sort((a, b) => a.name.localeCompare(b.name, 'ru')).forEach(item => {
                const option = document.createElement('option');
                option.value = item.id; option.textContent = item.name; fragment.append(option);
            });
            region.replaceChildren(fragment);
            region.disabled = false;
            loaded = true;
            status.textContent = 'Сначала выберите регион, затем начните вводить название города, села или деревни.';
        } catch (error) {
            status.textContent = error.message;
            retry.hidden = false;
        }
        onChange();
    };
    region.addEventListener('change', () => {
        invalidate();
        locality.value = '';
        locality.disabled = !region.value;
        locality.placeholder = region.value ? 'Начните вводить название' : 'Сначала выберите регион';
        status.textContent = region.value ? 'Введите не менее двух символов и выберите населённый пункт из подсказок.' : 'Выберите регион.';
        onChange();
    });
    locality.addEventListener('input', () => {
        invalidate();
        onChange();
        const query = locality.value.trim();
        if (!region.value || query.length < 2) {
            status.textContent = 'Введите не менее двух символов названия населённого пункта.';
            return;
        }
        const currentVersion = version;
        const currentRegion = region.value;
        timer = setTimeout(async () => {
            controller = new AbortController();
            status.textContent = 'Ищем населённые пункты…';
            const url = new URL(endpoint + 'localities');
            url.searchParams.set('region_id', currentRegion);
            url.searchParams.set('query', query);
            try {
                const data = await fetchJson(url, { signal: controller.signal });
                if (currentVersion !== version || currentRegion !== region.value) return;
                const items = Array.isArray(data.localities) ? data.localities : [];
                results.replaceChildren();
                items.forEach(item => {
                    const li = document.createElement('li');
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.setAttribute('aria-label', [item.name, item.district].filter(Boolean).join(' — '));
                    const name = document.createElement('span');
                    name.className = 'registration__suggestion-name'; name.textContent = item.name; button.append(name);
                    if (item.district) {
                        const detail = document.createElement('small');
                        detail.className = 'registration__suggestion-district'; detail.textContent = item.district; button.append(detail);
                    }
                    button.addEventListener('click', () => {
                        selected = item;
                        locality.value = item.name;
                        district.value = item.district || '';
                        hideResults();
                        status.textContent = 'Населённый пункт выбран. Укажите улицу и дом.';
                        onChange();
                        form.elements.street.focus();
                    });
                    li.append(button); results.append(li);
                });
                results.hidden = !items.length;
                results.scrollTop = 0;
                locality.setAttribute('aria-expanded', String(!!items.length));
                status.textContent = items.length
                    ? (data.more ? 'Показаны первые 20 результатов. Уточните название или район.' : 'Выберите свой населённый пункт из списка.')
                    : 'В выбранном регионе ничего не найдено. Проверьте название или обратитесь к организаторам.';
            } catch (error) {
                if (currentVersion !== version || error.name === 'AbortError') return;
                status.textContent = error.message;
            }
        }, 250);
    });
    locality.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeResults();
        if (event.key === 'ArrowDown' && results.childElementCount && !selected) {
            results.hidden = false; locality.setAttribute('aria-expanded', 'true');
            event.preventDefault(); results.querySelector('button')?.focus();
        }
        if (event.key === 'Enter' && !selected) event.preventDefault();
    });
    results.addEventListener('keydown', event => {
        const buttons = [...results.querySelectorAll('button')];
        if (event.key === 'Escape') { closeResults(); locality.focus(); }
        if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
            event.preventDefault();
            const step = event.key === 'ArrowDown' ? 1 : -1;
            buttons[(buttons.indexOf(event.target) + step + buttons.length) % buttons.length]?.focus();
        }
    });
    document.addEventListener('pointerdown', event => { if (!localityBox.contains(event.target)) closeResults(); });
    localityBox.addEventListener('focusout', event => {
        if (!localityBox.contains(event.relatedTarget)) closeResults();
    });
    retry.addEventListener('click', loadRegions);
    loadRegions();
    return {
        validate: () => {
            const valid = loaded && !!selected && selected.region_id === region.value && selected.name === locality.value;
            locality.setCustomValidity(valid ? '' : 'Выберите населённый пункт из подсказок для выбранного региона.');
            return valid;
        },
        append: payload => {
            payload.set('address_mode', 'directory');
            payload.set('region_id', region.value);
            payload.set('locality_id', selected?.id || '');
        }
    };
};
