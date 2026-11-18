// Navigation, preferences, and form feedback shared by every workspace.
(() => {
    const navigationToggle = document.querySelector('.nav-toggle');
    const navigation = document.querySelector('.nav-links');
    if (navigationToggle && navigation) {
        document.documentElement.classList.add('has-js');
        navigationToggle.addEventListener('click', () => {
            const expanded = navigationToggle.getAttribute('aria-expanded') === 'true';
            navigationToggle.setAttribute('aria-expanded', String(!expanded));
            navigation.classList.toggle('is-open', !expanded);
        });
    }

    const themeButton = document.querySelector('.theme-toggle');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    let theme = prefersDark ? 'night' : 'day';
    try {
        const storedTheme = localStorage.getItem('cstbs-theme');
        if (storedTheme === 'night' || storedTheme === 'day') theme = storedTheme;
    } catch (_) {
        // Theme selection works for this page when storage is unavailable.
    }
    const applyTheme = () => {
        document.documentElement.dataset.theme = theme;
        if (!themeButton) return;
        themeButton.textContent = theme === 'night' ? '☀' : '☾';
        themeButton.setAttribute('aria-pressed', String(theme === 'night'));
        themeButton.setAttribute('aria-label', theme === 'night' ? 'Switch to light theme' : 'Switch to dark theme');
    };
    applyTheme();
    themeButton?.addEventListener('click', () => {
        theme = theme === 'night' ? 'day' : 'night';
        applyTheme();
        try { localStorage.setItem('cstbs-theme', theme); } catch (_) {}
    });

    const approvedForms = new WeakSet();
    const busyForms = new WeakSet();
    const originalLabels = new Map();
    let pendingConfirmation = null;
    const confirmation = document.createElement('dialog');
    confirmation.className = 'confirmation-dialog';
    confirmation.setAttribute('aria-labelledby', 'confirmation-heading');
    confirmation.setAttribute('aria-describedby', 'confirmation-message');
    const heading = document.createElement('h2');
    heading.id = 'confirmation-heading';
    const message = document.createElement('p');
    message.id = 'confirmation-message';
    const actions = document.createElement('div');
    actions.className = 'confirmation-actions';
    const cancel = document.createElement('button');
    cancel.type = 'button';
    cancel.className = 'button button-secondary';
    cancel.textContent = 'Go back';
    const confirm = document.createElement('button');
    confirm.type = 'button';
    confirm.className = 'button button-danger';
    actions.append(cancel, confirm);
    confirmation.append(heading, message, actions);
    document.body.append(confirmation);

    const dismissConfirmation = () => confirmation.close('cancel');
    cancel.addEventListener('click', dismissConfirmation);
    confirmation.addEventListener('click', (event) => {
        if (event.target !== confirmation) return;
        const bounds = confirmation.getBoundingClientRect();
        const outside = event.clientX < bounds.left || event.clientX > bounds.right
            || event.clientY < bounds.top || event.clientY > bounds.bottom;
        if (outside) dismissConfirmation();
    });
    confirmation.addEventListener('keydown', (event) => {
        if (event.key !== 'Tab') return;
        if (event.shiftKey && document.activeElement === cancel) {
            event.preventDefault();
            confirm.focus();
        } else if (!event.shiftKey && document.activeElement === confirm) {
            event.preventDefault();
            cancel.focus();
        }
    });
    confirmation.addEventListener('close', () => {
        if (confirmation.returnValue === 'confirm') return;
        pendingConfirmation?.submitter?.focus();
        pendingConfirmation = null;
    });
    confirm.addEventListener('click', () => {
        if (!pendingConfirmation) return;
        const { form, submitter } = pendingConfirmation;
        pendingConfirmation = null;
        approvedForms.add(form);
        confirmation.close('confirm');
        if (submitter) form.requestSubmit(submitter);
        else form.requestSubmit();
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post') return;
        if (event.defaultPrevented) return;
        if (busyForms.has(form)) {
            event.preventDefault();
            return;
        }
        const submitter = event.submitter;
        if (form.dataset.confirm && !approvedForms.has(form)) {
            event.preventDefault();
            const action = form.dataset.confirmLabel || submitter?.textContent.trim() || 'Confirm';
            if (typeof confirmation.showModal !== 'function') {
                if (window.confirm(form.dataset.confirm)) {
                    approvedForms.add(form);
                    if (submitter) form.requestSubmit(submitter);
                    else form.requestSubmit();
                }
                return;
            }
            heading.textContent = action + '?';
            message.textContent = form.dataset.confirm;
            confirm.textContent = action;
            confirm.className = /delete|cancel|close|remove/i.test(action) ? 'button button-danger' : 'button button-primary';
            pendingConfirmation = { form, submitter };
            confirmation.showModal();
            cancel.focus();
            return;
        }
        approvedForms.delete(form);
        if (!form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
            return;
        }
        // Run after other submit listeners can reject a missing gate or input.
        queueMicrotask(() => {
            if (event.defaultPrevented) return;
            busyForms.add(form);
            form.setAttribute('aria-busy', 'true');
            if (submitter) {
                originalLabels.set(submitter, submitter.textContent);
                submitter.textContent = 'Please wait…';
                submitter.setAttribute('aria-disabled', 'true');
            }
        });
    });

    window.addEventListener('pageshow', () => {
        for (const form of document.forms) {
            busyForms.delete(form);
            form.removeAttribute('aria-busy');
        }
        for (const [button, label] of originalLabels) {
            button.textContent = label;
            button.removeAttribute('aria-disabled');
        }
        originalLabels.clear();
    });
    for (const button of document.querySelectorAll('[data-print-ticket]')) {
        button.addEventListener('click', () => window.print());
    }

    const video = document.getElementById('gate-video');
    video?.addEventListener('playing', () => document.body.classList.add('is-scanning'));
    const stopScanningFeedback = () => document.body.classList.remove('is-scanning');
    video?.addEventListener('emptied', stopScanningFeedback);
    video?.addEventListener('pause', stopScanningFeedback);
    window.addEventListener('pagehide', stopScanningFeedback);
})();
