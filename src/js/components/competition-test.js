const testRoot = document.querySelector('.competition-test');

if (testRoot) {
    const duration = 40 * 60 * 1000;
    const maximumAttempts = 3;
    const questionCount = 5; // UI placeholders only, not the final question count.
    const answers = Array(questionCount).fill(null);
    const screens = [...testRoot.querySelectorAll('[data-test-screen]')];
    const dialog = testRoot.querySelector('dialog');
    const answerBox = testRoot.querySelector('[data-test-answers]');
    const navigation = testRoot.querySelector('[data-test-navigation]');
    const timer = testRoot.querySelector('[data-test-timer]');
    const live = testRoot.querySelector('[data-test-live]');
    let currentQuestion = 0;
    let usedAttempts = 0;
    let deadline = 0;
    let interval = null;
    let running = false;
    let timeWarning = false;

    const remaining = () => maximumAttempts - usedAttempts;
    const answeredCount = () => answers.filter((answer) => answer !== null).length;

    function updateAttempts() {
        testRoot.querySelectorAll('[data-test-remaining]').forEach((element) => {
            element.textContent = remaining();
        });
        testRoot.querySelector('[data-test-action="retry"]').disabled = remaining() === 0;
    }

    function showScreen(name) {
        screens.forEach((screen) => {
            screen.hidden = screen.dataset.testScreen !== name;
        });
        const heading = testRoot.querySelector('[data-test-screen="' + name + '"] [tabindex="-1"]');
        heading?.focus({ preventScroll: true });
    }

    function renderQuestion() {
        testRoot.querySelector('[data-test-counter]').textContent =
            'Демонстрационный вопрос ' + (currentQuestion + 1) + ' из ' + questionCount;
        answerBox.replaceChildren();

        for (let option = 0; option < 4; option += 1) {
            const label = document.createElement('label');
            label.className = 'competition-test__answer';
            const input = document.createElement('input');
            input.type = 'radio';
            input.name = 'demo-answer';
            input.value = option;
            input.checked = answers[currentQuestion] === option;
            const caption = document.createElement('span');
            caption.textContent = 'Вариант ответа ' + (option + 1);
            label.append(input, caption);
            answerBox.append(label);
        }

        navigation.replaceChildren();
        for (let question = 0; question < questionCount; question += 1) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'competition-test__number';
            button.textContent = question + 1;
            button.dataset.testQuestion = question;
            const filled = answers[question] !== null;
            button.classList.toggle('competition-test__number--answered', filled);
            button.setAttribute('aria-label', 'Вопрос ' + (question + 1) + (filled ? ', заполнен' : ', не заполнен'));
            if (question === currentQuestion) button.setAttribute('aria-current', 'step');
            navigation.append(button);
        }

        const count = answeredCount();
        testRoot.querySelector('progress').value = count;
        testRoot.querySelector('[data-test-answered]').textContent = 'Заполнено ' + count + ' из ' + questionCount;
        testRoot.querySelector('[data-test-action="previous"]').disabled = currentQuestion === 0;
        testRoot.querySelector('[data-test-action="next"]').hidden = currentQuestion === questionCount - 1;
    }

    function finishAttempt(expired = false) {
        if (!running) return;
        running = false;
        clearInterval(interval);
        interval = null;
        if (dialog.open) dialog.close();
        testRoot.querySelector('[data-test-reason]').textContent = expired
            ? '40 минут истекли. Демо-попытка завершена автоматически.'
            : 'Заполнено ' + answeredCount() + ' из ' + questionCount + '. Это демонстрация: данные никуда не отправлены.';
        testRoot.querySelector('[data-test-result-title]').textContent = 'Демо-тест завершён';
        testRoot.querySelector('[data-test-result-status]').textContent =
            'Оценивание подключим после добавления вопросов и серверной части.';
        testRoot.querySelector('[data-test-action="essay"]').disabled = true;
        testRoot.querySelector('[data-test-action="essay"]').hidden = true;
        testRoot.querySelector('[data-test-action="retry"]').hidden = false;
        testRoot.querySelector('[data-test-result-attempts]').hidden = false;
        testRoot.querySelector('[data-test-message]').textContent = '';
        testRoot.querySelectorAll('[data-test-preview]').forEach((button) => button.setAttribute('aria-pressed', 'false'));
        updateAttempts();
        showScreen('result');
        live.textContent = expired ? 'Время истекло. Демо-тест завершён.' : 'Демо-тест завершён.';
    }

    function updateTimer() {
        if (!running) return;
        const milliseconds = Math.max(0, deadline - Date.now());
        const seconds = Math.ceil(milliseconds / 1000);
        timer.textContent = String(Math.floor(seconds / 60)).padStart(2, '0') + ':' +
            String(seconds % 60).padStart(2, '0');
        timer.closest('.competition-test__timer').classList.toggle('competition-test__timer--urgent', seconds <= 60);
        if (seconds <= 60 && !timeWarning) {
            timeWarning = true;
            live.textContent = 'Осталась одна минута. Попытка завершится автоматически.';
        }
        if (milliseconds === 0) finishAttempt(true);
    }

    function startAttempt() {
        if (running || remaining() === 0) return;
        usedAttempts += 1;
        answers.fill(null);
        currentQuestion = 0;
        timeWarning = false;
        running = true;
        deadline = Date.now() + duration;
        updateAttempts();
        renderQuestion();
        showScreen('question');
        updateTimer();
        interval = window.setInterval(updateTimer, 1000);
        live.textContent = 'Начата демонстрационная попытка ' + usedAttempts + ' из ' + maximumAttempts + '.';
    }

    function previewResult(passed) {
        if (running) return;
        testRoot.querySelector('[data-test-result-title]').textContent = passed ? 'Тест пройден' : 'Тест не пройден';
        testRoot.querySelector('[data-test-result-status]').textContent = passed
            ? 'Пример: вы допущены к следующему этапу — эссе.'
            : 'Пример: вы можете пройти тест ещё раз, если остались попытки.';
        testRoot.querySelector('[data-test-action="essay"]').disabled = !passed;
        testRoot.querySelector('[data-test-action="essay"]').hidden = !passed;
        testRoot.querySelector('[data-test-action="retry"]').hidden = passed;
        testRoot.querySelector('[data-test-result-attempts]').hidden = passed;
        testRoot.querySelector('[data-test-message]').textContent = '';
        testRoot.querySelectorAll('[data-test-preview]').forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.testPreview === (passed ? 'passed' : 'failed')));
        });
        live.textContent = passed ? 'Показан пример: тест пройден.' : 'Показан пример: тест не пройден.';
    }

    answerBox.addEventListener('change', (event) => {
        if (!running || !(event.target instanceof HTMLInputElement)) return;
        if (Date.now() >= deadline) {
            updateTimer();
            return;
        }
        answers[currentQuestion] = Number(event.target.value);
        const focusedOption = event.target.value;
        renderQuestion();
        answerBox.querySelector('input[value="' + focusedOption + '"]')?.focus({ preventScroll: true });
    });

    testRoot.addEventListener('click', (event) => {
        const questionButton = event.target.closest('[data-test-question]');
        if (questionButton && running) {
            updateTimer();
            if (!running) return;
            currentQuestion = Number(questionButton.dataset.testQuestion);
            renderQuestion();
            testRoot.querySelector('.competition-test__question--title').focus({ preventScroll: true });
            return;
        }
        const previewButton = event.target.closest('[data-test-preview]');
        if (previewButton) {
            previewResult(previewButton.dataset.testPreview === 'passed');
            return;
        }
        const action = event.target.closest('[data-test-action]')?.dataset.testAction;
        if (!action) return;
        if (running) {
            updateTimer();
            if (!running) return;
        }
        switch (action) {
            case 'start':
            case 'retry':
                startAttempt();
                break;
            case 'previous':
            case 'next':
                if (!running) return;
                currentQuestion = Math.max(0, Math.min(questionCount - 1, currentQuestion + (action === 'next' ? 1 : -1)));
                renderQuestion();
                testRoot.querySelector('.competition-test__question--title').focus({ preventScroll: true });
                break;
            case 'confirm':
                if (!running) return;
                testRoot.querySelector('[data-test-summary]').textContent = 'Заполнено ' + answeredCount() + ' из ' + questionCount + '. Ответы не отправляются на сервер.';
                dialog.showModal();
                break;
            case 'cancel':
                dialog.close();
                break;
            case 'submit':
                finishAttempt();
                break;
            case 'essay':
                testRoot.querySelector('[data-test-message]').textContent =
                    'Это предпросмотр допуска. Страница эссе и серверная проверка будут подключены отдельно.';
                break;
        }
    });

    testRoot.querySelector('form').addEventListener('submit', (event) => event.preventDefault());
    document.addEventListener('visibilitychange', updateTimer);
    window.addEventListener('pageshow', updateTimer);
    window.addEventListener('beforeunload', (event) => {
        if (!running) return;
        event.preventDefault();
        event.returnValue = '';
    });

    testRoot.querySelector('[data-test-action="start"]').disabled = false;
    updateAttempts();
}

