// WordPress supplies a REST endpoint and nonce. The static mockup stays separate.
const root = document.querySelector('.competition-test[data-test-config]');

if (root) {
    const config = JSON.parse(root.dataset.testConfig);
    const find = (selector) => root.querySelector(selector);
    const dialog = find('dialog');
    const notice = find('[data-test-status]');
    const live = find('[data-test-live]');
    const answersBox = find('[data-test-answers]');
    let state = null;
    let current = 0;
    let busy = false;
    let clockOrigin = performance.now();
    let serverTime = 0;
    let lastSync = performance.now();
    let lastTimeoutAttempt = null;
    let requestKey = null;

    const active = () => state?.attempt?.status === 'in_progress' ? state.attempt : null;
    const questions = () => active()?.questions || [];
    const filled = () => questions().filter((question) => active().answers[question.id] != null).length;
    const serverNow = () => serverTime + (performance.now() - clockOrigin) / 1000;

    function message(text) {
        notice.textContent = text;
        notice.hidden = !text;
        live.textContent = text;
    }

    function showScreen(name) {
        root.querySelectorAll('[data-test-screen]').forEach((screen) => {
            screen.hidden = screen.dataset.testScreen !== name;
        });
    }

    function updateControls() {
        const attempt = active();
        root.querySelectorAll('[data-test-action]').forEach((button) => {
            const action = button.dataset.testAction;
            button.disabled = busy || !state;
            if (action === 'start' || action === 'retry') button.disabled ||= !state?.can_start;
            if (action === 'previous') button.disabled ||= !attempt || current === 0;
            if (action === 'next' || action === 'confirm') button.disabled ||= !attempt;
            if (action === 'essay') button.disabled = busy || !state?.essay_allowed || !config.essayUrl;
        });
        root.querySelectorAll('[data-test-question], input[type="radio"]').forEach((element) => {
            element.disabled = busy || !attempt || serverNow() >= attempt.expires_at;
        });
        root.setAttribute('aria-busy', String(busy));
    }

    function renderQuestion() {
        const attempt = active();
        if (!attempt) return;
        current = Math.min(current, questions().length - 1);
        const question = questions()[current];
        find('[data-test-counter]').textContent = 'Вопрос ' + (current + 1) + ' из ' + questions().length;
        find('.competition-test__question--title').textContent = question.text;
        answersBox.replaceChildren();
        question.options.forEach((option, index) => {
            const label = document.createElement('label');
            label.className = 'competition-test__answer';
            const input = document.createElement('input');
            input.type = 'radio';
            input.name = 'test-answer';
            input.value = index + 1;
            input.checked = attempt.answers[question.id] === index + 1;
            const caption = document.createElement('span');
            caption.textContent = option;
            label.append(input, caption);
            answersBox.append(label);
        });
        const navigation = find('[data-test-navigation]');
        navigation.replaceChildren();
        questions().forEach((item, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'competition-test__number';
            button.dataset.testQuestion = index;
            button.textContent = index + 1;
            const answered = attempt.answers[item.id] != null;
            button.classList.toggle('competition-test__number--answered', answered);
            button.setAttribute('aria-label', 'Вопрос ' + (index + 1) + (answered ? ', заполнен' : ', не заполнен'));
            if (index === current) button.setAttribute('aria-current', 'step');
            navigation.append(button);
        });
        const progress = find('progress');
        progress.max = questions().length;
        progress.value = filled();
        find('[data-test-answered]').textContent = 'Сохранено ответов: ' + filled() + ' из ' + questions().length;
        find('[data-test-action="next"]').hidden = current === questions().length - 1;
        if (dialog.open) find('[data-test-summary]').textContent = 'Сохранено ответов: ' + filled() + ' из ' + questions().length + '. Пропущенные вопросы принесут 0 баллов.';
    }

    function render() {
        root.querySelectorAll('[data-test-remaining]').forEach((element) => {
            element.textContent = state.attempts_remaining;
        });
        if (active()) {
            showScreen('question');
            renderQuestion();
        } else {
            if (dialog.open) dialog.close();
            const result = state.latest_result;
            showScreen(result ? 'result' : 'start');
            if (result) {
                find('[data-test-result-title]').textContent = state.essay_allowed ? 'Тест пройден' : 'Тест не пройден';
                find('[data-test-reason]').textContent = result.finish_reason === 'timeout'
                    ? 'Время истекло. Попытка завершена автоматически.' : 'Ответы сохранены. Попытка завершена.';
                find('[data-test-result-status]').textContent = state.essay_allowed
                    ? 'Вы допущены к следующему этапу — эссе.'
                    : (state.attempts_remaining > 0 ? 'Вы можете пройти тест ещё раз.' : 'Доступные попытки использованы.');
                find('[data-test-action="retry"]').hidden = state.essay_allowed;
                find('[data-test-action="essay"]').hidden = !state.essay_allowed;
                find('[data-test-result-attempts]').hidden = state.essay_allowed;
                find('[data-test-message]').textContent = state.essay_allowed && !config.essayUrl ? 'Приём эссе пока не открыт.' : '';
            }
        }
        updateControls();
        updateTimer();
    }

    async function request(command, payload = null) {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 15000);
        try {
            const response = await fetch(config.endpoint + command, {
                method: payload === null ? 'GET' : 'POST',
                credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
                headers: { 'X-WP-Nonce': config.nonce, ...(payload === null ? {} : { 'Content-Type': 'application/json' }) },
                ...(payload === null ? {} : { body: JSON.stringify({ ...payload, season_id: config.seasonId || '' }) }),
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Не удалось выполнить запрос. Повторите действие.');
            return result;
        } catch (error) {
            if (error instanceof SyntaxError || error.name === 'AbortError' || error instanceof TypeError) {
                throw new Error('Нет ответа от сервера. Проверьте соединение и повторите действие.');
            }
            throw error;
        } finally {
            clearTimeout(timeout);
        }
    }

    function accept(result) {
        state = result;
        serverTime = result.server_time;
        clockOrigin = performance.now();
        lastSync = clockOrigin;
        render();
        if (state.essay_allowed && !active() && config.essayUrl) {
            location.replace(config.essayUrl);
        }
    }

    async function run(command, payload = null) {
        if (busy) return false;
        busy = true;
        lastSync = performance.now();
        updateControls();
        message(command === 'answer' ? 'Сохраняем ответ…' : 'Проверяем состояние теста…');
        try {
            const result = await request(command, payload);
            accept(result);
            message(active() ? (command === 'answer' ? 'Ответ сохранён.' : 'Все отмеченные ответы сохранены на сервере.') : (!state.open ? 'Новые попытки сейчас закрыты.' : ''));
            return true;
        } catch (error) {
            // Reconcile a response lost after the server committed a mutation.
            if (command !== 'status') {
                try { accept(await request('status')); } catch { /* Keep the last confirmed state. */ }
            }
            if (active()) renderQuestion();
            message(error.message + (command === 'answer' ? ' Если ответ не отмечен, выберите его снова.' : ''));
            return false;
        } finally {
            busy = false;
            updateControls();
        }
    }

    function updateTimer() {
        const attempt = active();
        if (!attempt) return;
        const seconds = Math.max(0, Math.ceil(attempt.expires_at - serverNow()));
        find('[data-test-timer]').textContent = String(Math.floor(seconds / 60)).padStart(2, '0') + ':' + String(seconds % 60).padStart(2, '0');
        find('.competition-test__timer').classList.toggle('competition-test__timer--urgent', seconds <= 60);
        if (seconds === 0 && !busy) {
            updateControls();
            if (lastTimeoutAttempt !== attempt.id || performance.now() - lastSync > 10000) {
                lastTimeoutAttempt = attempt.id;
                lastSync = performance.now();
                void run('status');
            }
        }
    }

    function startKey() {
        // Reuse the key if a start response was lost, including after a reload.
        try { requestKey ||= sessionStorage.getItem('tarhan-test-start-key'); } catch { /* Storage may be disabled. */ }
        requestKey ||= Array.from(crypto.getRandomValues(new Uint8Array(16)), (value) => value.toString(16).padStart(2, '0')).join('');
        try { sessionStorage.setItem('tarhan-test-start-key', requestKey); } catch { /* In-memory retry still works. */ }
        return requestKey;
    }

    answersBox.addEventListener('change', (event) => {
        if (busy || !active() || !(event.target instanceof HTMLInputElement)) return;
        void run('answer', { attempt_id: active().id, question_id: questions()[current].id, answer: Number(event.target.value) });
    });

    root.addEventListener('click', async (event) => {
        const button = event.target.closest('button');
        if (!button || button.disabled || busy) return;
        if (button.hasAttribute('data-test-question') && active()) {
            current = Number(button.dataset.testQuestion);
            renderQuestion();
            updateControls();
            find('.competition-test__question--title').focus({ preventScroll: true });
            return;
        }
        switch (button.dataset.testAction) {
            case 'start':
            case 'retry':
                current = 0;
                if (await run('start', { request_key: startKey() })) {
                    requestKey = null;
                    try { sessionStorage.removeItem('tarhan-test-start-key'); } catch { /* Storage may be disabled. */ }
                }
                break;
            case 'previous':
            case 'next':
                if (!active()) return;
                current = Math.max(0, Math.min(questions().length - 1, current + (button.dataset.testAction === 'next' ? 1 : -1)));
                renderQuestion();
                updateControls();
                find('.competition-test__question--title').focus({ preventScroll: true });
                break;
            case 'confirm':
                if (!active()) return;
                find('[data-test-summary]').textContent = 'Сохранено ответов: ' + filled() + ' из ' + questions().length + '. Пропущенные вопросы принесут 0 баллов.';
                dialog.showModal();
                break;
            case 'cancel':
                dialog.close();
                break;
            case 'submit':
                if (active()) await run('finish', { attempt_id: active().id });
                break;
            case 'essay':
                if (state?.essay_allowed && config.essayUrl) window.location.assign(config.essayUrl);
                break;
        }
    });

    find('form').addEventListener('submit', (event) => event.preventDefault());
    window.addEventListener('online', () => { if (config.loggedIn && !busy) void run('status'); });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && config.loggedIn && !busy) void run('status');
    });
    window.addEventListener('pageshow', (event) => { if (event.persisted && config.loggedIn && !busy) void run('status'); });
    window.addEventListener('beforeunload', (event) => {
        if (!busy) return;
        event.preventDefault();
        event.returnValue = '';
    });
    setInterval(() => {
        updateTimer();
        if (active() && !busy && performance.now() - lastSync > 30000) void run('status');
    }, 1000);

    updateControls();
    if (!config.available) {
        message('Тестирование временно недоступно. Обратитесь к организаторам.');
    } else if (!config.loggedIn) {
        message('Для прохождения теста войдите в аккаунт.');
        const login = document.createElement('a');
        login.className = 'competition-test__button';
        login.href = config.loginUrl;
        login.textContent = 'Войти';
        find('[data-test-screen="start"] .competition-test__actions').append(login);
    } else {
        void run('status');
    }
}
