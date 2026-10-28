(() => {
    const banner = document.querySelector('#tarhan-cookies');
    if (!banner) return;
    const key = 'tarhan-cookie-choice-v1';
    const duration = 180 * 24 * 60 * 60 * 1000;
    const settings = document.querySelector('[data-cookie-settings]');
    let returnFocus = false;
    const read = () => {
        try {
            const value = JSON.parse(localStorage.getItem(key));
            return value && ['accepted', 'rejected'].includes(value.choice) &&
                Number.isFinite(value.expires) && value.expires > Date.now() ? value : null;
        } catch { return null; }
    };
    banner.hidden = Boolean(read());
    settings?.addEventListener('click', () => {
        banner.hidden = false;
        returnFocus = true;
        banner.querySelector('button').focus();
    });
    banner.querySelectorAll('[data-cookie-choice]').forEach(button => {
        button.addEventListener('click', () => {
            const choice = button.dataset.cookieChoice;
            try { localStorage.setItem(key, JSON.stringify({ choice, expires: Date.now() + duration })); } catch { /* Site remains usable if storage is blocked. */ }
            banner.hidden = true;
            if (returnFocus) { settings?.focus(); returnFocus = false; }
        });
    });
    window.addEventListener('storage', event => {
        if (event.key === key || event.key === null) banner.hidden = Boolean(read());
    });
})();
