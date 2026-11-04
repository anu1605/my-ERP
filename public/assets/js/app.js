(function () {
    'use strict';

    const body = document.body;
    const csrf = body.getAttribute('data-csrf') || '';
    const base = body.getAttribute('data-base') || '';

    function appUrl(path) {
        return base + '/' + String(path).replace(/^\/+/, '');
    }

    function formatDuration(totalSeconds) {
        totalSeconds = Math.max(0, Math.floor(totalSeconds));
        const h = Math.floor(totalSeconds / 3600);
        const m = Math.floor((totalSeconds % 3600) / 60);
        const s = totalSeconds % 60;
        const pad = (n) => String(n).padStart(2, '0');
        if (h > 0) {
            return h + 'h ' + pad(m) + 'm ' + pad(s) + 's';
        }
        if (m > 0) {
            return m + 'm ' + pad(s) + 's';
        }
        return s + 's';
    }

    const timers = [];

    function registerTimer(el) {
        const valueEl = el.querySelector('[data-timer-value]');
        if (!valueEl) {
            return null;
        }
        const timer = {
            el: el,
            valueEl: valueEl,
            base: parseInt(el.getAttribute('data-elapsed') || '0', 10),
            total: parseInt(el.getAttribute('data-total') || '0', 10),
            since: performance.now()
        };
        timers.push(timer);
        updateTimer(timer);
        return timer;
    }

    document.querySelectorAll('[data-elapsed]').forEach(registerTimer);

    function updateTimer(timer) {
        const elapsed = timer.base + (performance.now() - timer.since) / 1000;
        timer.valueEl.textContent = formatDuration(timer.total + elapsed);
    }

    function tickAll() {
        timers.forEach(updateTimer);
    }

    setInterval(tickAll, 1000);

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('form button[data-confirm]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            if (!window.confirm(button.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-copy-target]').forEach(function (button) {
        button.addEventListener('click', function () {
            const target = document.querySelector(button.getAttribute('data-copy-target'));
            if (!target) {
                return;
            }
            const text = target.value !== undefined ? target.value : target.textContent;
            navigator.clipboard.writeText(text).then(function () {
                const original = button.textContent;
                button.textContent = 'Copied!';
                setTimeout(function () { button.textContent = original; }, 1500);
            });
        });
    });

    document.querySelectorAll('[data-toggle-target]').forEach(function (button) {
        button.addEventListener('click', function () {
            const target = document.querySelector(button.getAttribute('data-toggle-target'));
            if (target) {
                target.hidden = !target.hidden;
            }
        });
    });

    function closeModal(modal) {
        if (modal) {
            modal.hidden = true;
        }
    }

    document.querySelectorAll('[data-modal-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            const modal = document.querySelector(button.getAttribute('data-modal-open'));
            if (modal) {
                modal.hidden = false;
            }
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach(function (el) {
        el.addEventListener('click', function () {
            closeModal(el.closest('.modal'));
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.modal:not([hidden])').forEach(closeModal);
        }
    });

    document.querySelectorAll('form[data-report-submit]').forEach(function (form) {        form.addEventListener('submit', function (event) {
            event.preventDefault();

            const button = form.querySelector('button[type=submit]');
            if (button) {
                button.disabled = true;
            }

            const body = new URLSearchParams(new FormData(form));

            // Carry over any report notes typed on the page (but not yet saved).
            const notes = document.querySelector('form[data-report-notes]');
            if (notes) {
                new FormData(notes).forEach(function (value, key) {
                    if (key !== '_csrf') {
                        body.set(key, value);
                    }
                });
            }

            const date = (form.querySelector('[name=report_date]') || {}).value || '';
            const base = form.getAttribute('data-export-url') || '';

            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: body,
                credentials: 'same-origin'
            })
                .then(function () {
                    const iframe = document.createElement('iframe');
                    iframe.style.display = 'none';
                    iframe.src = base + '?date=' + encodeURIComponent(date) + '&format=txt';
                    document.body.appendChild(iframe);
                    setTimeout(function () {
                        iframe.remove();
                        window.location.reload();
                    }, 1500);
                })
                .catch(function () {
                    window.location.reload();
                });
        });
    });

    function showToast(type, message) {
        const container = document.getElementById('toast-container');
        if (!container) {
            return;
        }
        const toast = document.createElement('div');
        toast.className = 'toast toast-' + (type || 'info');
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(function () {
            toast.classList.add('toast-hide');
            setTimeout(function () { toast.remove(); }, 320);
        }, 4000);
    }

    const taskRowsByTask = {};
    document.querySelectorAll('[data-task-row]').forEach(function (row) {
        taskRowsByTask[row.getAttribute('data-task-row')] = row;
    });

    function applyActiveTimer(timer) {
        for (let i = timers.length - 1; i >= 0; i--) {
            if (timers[i].el.closest('[data-task-row]')) {
                timers.splice(i, 1);
            }
        }
        document.querySelectorAll('[data-task-row]').forEach(function (row) {
            row.classList.remove('task-row-running');
            const value = row.querySelector('.tracked-value');
            if (value) {
                if (value.getAttribute('data-elapsed') !== null) {
                    value.setAttribute('data-rendered', value.textContent.trim());
                }
                    value.removeAttribute('data-elapsed');
                    value.textContent = value.getAttribute('data-rendered') || '';
            }
            const stopForm = row.querySelector('form[data-timer-action=stop]');
            if (stopForm) {
                const blocked = row.getAttribute('data-start-blocked');
                if (blocked) {
                    const span = document.createElement('span');
                    span.className = 'muted small';
                    span.textContent = blocked === 'deadline' ? 'deadline passed' : 'locked';
                    if (blocked === 'deadline') {
                        span.title = 'Update the deadline to a future date to start';
                    }
                    stopForm.replaceWith(span);
                } else {
                    stopForm.replaceWith(makeTimerForm('start', row.getAttribute('data-task-row')));
                }
            }
        });

        if (timer && timer.task_id) {
            const row = taskRowsByTask[String(timer.task_id)];
            if (row) {
                row.classList.add('task-row-running');
                const value = row.querySelector('.tracked-value');
                if (value) {
                    value.setAttribute('data-elapsed', '0');
                    const inner = document.createElement('span');
                    inner.className = 'timer-active-value';
                    inner.setAttribute('data-timer-value', '');
                    value.textContent = '';
                    value.appendChild(inner);
                    registerTimer(value);
                }
                const startForm = row.querySelector('form[data-timer-action=start]');
                if (startForm) {
                    startForm.replaceWith(makeTimerForm('stop', timer.id));
                }
            }
        }

        applyHeaderTimer(timer);
        document.querySelectorAll('form[data-timer-action]').forEach(bindTimerForm);
    }

    function applyHeaderTimer(timer) {
        const existing = document.getElementById('header-timer');
        if (!timer) {
            if (existing) {
                existing.remove();
            }
            return;
        }
        if (existing) {
            existing.setAttribute('data-session', String(timer.id));
            existing.setAttribute('data-elapsed', '0');
            const link = existing.querySelector('a');
            if (link) {
                link.href = appUrl('/tasks/' + timer.task_id);
                link.textContent = timer.task_title || 'task';
            }
            const valueEl = existing.querySelector('[data-timer-value]');
            if (valueEl) {
                const headerTimer = timers.find(function (t) { return t.el === existing; });
                if (headerTimer) {
                    headerTimer.base = 0;
                    headerTimer.total = 0;
                    headerTimer.since = performance.now();
                }
            }
            return;
        }
        const container = document.querySelector('.topbar-right');
        if (!container) {
            return;
        }
        const pill = document.createElement('span');
        pill.className = 'timer-pill';
        pill.id = 'header-timer';
        pill.setAttribute('data-active', '');
        pill.setAttribute('data-session', String(timer.id));
        pill.setAttribute('data-elapsed', '0');
        const dot = document.createElement('span');
        dot.className = 'dot';
        const link = document.createElement('a');
        link.href = appUrl('/tasks/' + timer.task_id);
        link.textContent = timer.task_title || 'task';
        const value = document.createElement('span');
        value.className = 'timer-value';
        value.setAttribute('data-timer-value', '');
        value.textContent = formatDuration(0);
        pill.appendChild(dot);
        pill.appendChild(link);
        pill.appendChild(value);
        container.insertBefore(pill, container.firstChild);
        registerTimer(pill);
    }

    function makeTimerForm(action, refId) {
        const form = document.createElement('form');
        form.method = 'post';
        form.setAttribute('data-timer-action', action);
        form.action = action === 'stop'
            ? appUrl('/timers/' + refId + '/stop')
            : appUrl('/tasks/' + refId + '/timer/start');

        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_csrf';
        csrfInput.value = csrf;

        const returnInput = document.createElement('input');
        returnInput.type = 'hidden';
        returnInput.name = 'return';
        returnInput.value = window.location.pathname + window.location.search;

        const btn = document.createElement('button');
        btn.type = 'submit';
        btn.className = action === 'stop' ? 'btn btn-stop btn-sm' : 'btn btn-start btn-sm';
        btn.textContent = action === 'stop' ? '\u25A0 Stop' : '\u25B6 Start';

        form.appendChild(csrfInput);
        form.appendChild(returnInput);
        form.appendChild(btn);
        return form;
    }

    function bindTimerForm(form) {
        if (form.getAttribute('data-timer-bound')) {
            return;
        }
        form.setAttribute('data-timer-bound', '1');
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const button = form.querySelector('button[type=submit]');
            if (button) {
                button.disabled = true;
            }
            const data = new URLSearchParams(new FormData(form));
            fetch(form.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: data,
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json().catch(function () { return null; });
                })
                .then(function (result) {
                    if (!result) {
                        window.location.reload();
                        return;
                    }
                    const flashes = result.flashes && result.flashes.length
                        ? result.flashes
                        : [{ type: result.ok ? 'success' : 'error', message: result.message }];
                    flashes.forEach(function (flash) { showToast(flash.type, flash.message); });
                    if (result.notice) {
                        showToast('info', result.notice);
                    }
                    applyActiveTimer(result.active_timer);
                    if (button) {
                        button.disabled = false;
                    }
                })
                .catch(function () {
                    window.location.reload();
                });
        });
    }

    document.querySelectorAll('form[data-timer-action]').forEach(bindTimerForm);

    function makeHiddenInput(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        return input;
    }

    const todoDragIcon = '<svg xmlns="http://www.w3.org/2000/svg" xml:space="preserve" x="0" y="0" fill="#000" viewBox="0 0 20 20"><circle cx="7.5" cy="5.5" r="1.5"/><circle cx="12.5" cy="5.5" r="1.5"/><circle cx="7.5" cy="10.5" r="1.5"/><circle cx="12.5" cy="10.5" r="1.5"/><circle cx="7.5" cy="15.5" r="1.5"/><circle cx="12.5" cy="15.5" r="1.5"/></svg>';

    function createTodoRow(item) {
        const row = document.createElement('li');
        row.className = 'todo-row';
        row.setAttribute('data-todo-id', String(item.id));

        const handle = document.createElement('span');
        handle.className = 'todo-drag';
        handle.draggable = true;
        handle.title = 'Drag to reorder';
        handle.setAttribute('aria-label', 'Drag to reorder');
        handle.innerHTML = todoDragIcon;

        const toggleForm = document.createElement('form');
        toggleForm.method = 'post';
        toggleForm.className = 'todo-toggle-form';
        toggleForm.setAttribute('data-todo-toggle-form', '');
        toggleForm.action = appUrl('/todo/' + item.id + '/toggle');
        toggleForm.appendChild(makeHiddenInput('_csrf', csrf));
        toggleForm.appendChild(makeHiddenInput('done', '1'));
        const check = document.createElement('button');
        check.type = 'submit';
        check.className = 'todo-check-btn';
        check.setAttribute('aria-label', 'Mark as done');
        check.title = 'Mark as done';
        toggleForm.appendChild(check);

        const text = document.createElement('span');
        text.className = 'todo-text';
        text.textContent = item.body;

        const deleteForm = document.createElement('form');
        deleteForm.method = 'post';
        deleteForm.className = 'todo-delete-form';
        deleteForm.action = appUrl('/todo/' + item.id + '/delete');
        deleteForm.appendChild(makeHiddenInput('_csrf', csrf));
        const del = document.createElement('button');
        del.type = 'submit';
        del.className = 'todo-delete';
        del.setAttribute('aria-label', 'Delete item');
        del.title = 'Delete item';
        del.textContent = '\u00D7';
        deleteForm.appendChild(del);

        const content = document.createElement('div');
        content.className = 'todo-content';
        content.appendChild(text);
        content.appendChild(deleteForm);

        row.appendChild(handle);
        row.appendChild(toggleForm);
        row.appendChild(content);

        return row;
    }

    function appendTodoItems(list, items) {
        items.forEach(function (item) {
            list.appendChild(createTodoRow(item));
        });

        const empty = list.parentElement ? list.parentElement.querySelector('.todo-empty') : null;
        if (empty) {
            empty.hidden = list.querySelector('.todo-row') !== null;
        }
    }

    function sendTodoBody(form, list, input, text) {
        const data = new URLSearchParams();
        data.set('_csrf', csrf);
        data.set('body', text);

        fetch(form.action, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: data,
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.json().catch(function () { return null; });
            })
            .then(function (result) {
                if (!result || !result.ok) {
                    window.location.reload();
                    return;
                }
                appendTodoItems(list, result.items || []);
                input.value = '';
                input.style.height = '';
                input.focus();
            })
            .catch(function () {
                form.submit();
            });
    }

    document.querySelectorAll('form[data-todo-add]').forEach(function (form) {
        const input = form.querySelector('.todo-add-input');
        const list = document.querySelector('[data-todo-list]');
        if (!input || !list) {
            return;
        }

        function submitTodo() {
            const value = input.value;
            if (value.trim() === '') {
                return;
            }
            sendTodoBody(form, list, input, value);
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            submitTodo();
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                submitTodo();
            }
        });

        input.addEventListener('paste', function (event) {
            const clipboard = event.clipboardData || window.clipboardData;
            if (!clipboard) {
                return;
            }
            const lines = clipboard.getData('text').split(/\r?\n/).map(function (line) {
                return line.trim();
            }).filter(function (line) {
                return line !== '';
            });

            if (lines.length < 2) {
                return;
            }

            event.preventDefault();
            sendTodoBody(form, list, input, lines.join('\n'));
        });

        input.addEventListener('input', function () {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 160) + 'px';
        });
    });

    function getDragAfterElement(list, y) {
        const rows = Array.from(list.querySelectorAll('.todo-row:not(.dragging)'));

        return rows.reduce(function (closest, child) {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;

            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            }

            return closest;
        }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
    }

    document.querySelectorAll('[data-todo-list]').forEach(function (list) {
        let dragged = null;

        list.addEventListener('dragstart', function (event) {
            const handle = event.target.closest('.todo-drag');
            const row = handle ? handle.closest('.todo-row') : null;

            if (!row || handle.classList.contains('todo-drag-disabled')) {
                event.preventDefault();
                return;
            }

            dragged = row;
            row.classList.add('dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', row.getAttribute('data-todo-id') || '');

            if (typeof event.dataTransfer.setDragImage === 'function') {
                event.dataTransfer.setDragImage(row, 12, 12);
            }
        });

        list.addEventListener('dragover', function (event) {
            if (!dragged) {
                return;
            }

            event.preventDefault();
            const after = getDragAfterElement(list, event.clientY);

            if (after === null) {
                list.appendChild(dragged);
            } else if (after !== dragged) {
                list.insertBefore(dragged, after);
            }
        });

        list.addEventListener('drop', function (event) {
            event.preventDefault();
        });

        list.addEventListener('dragend', function () {
            if (!dragged) {
                return;
            }

            dragged.classList.remove('dragging');
            dragged = null;

            const data = new URLSearchParams();
            data.set('_csrf', csrf);
            Array.from(list.querySelectorAll('.todo-row')).forEach(function (row) {
                data.append('order[]', row.getAttribute('data-todo-id') || '');
            });

            fetch(list.getAttribute('data-reorder-url'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: data,
                credentials: 'same-origin'
            }).catch(function () {
                window.location.reload();
            });
        });
    });
})();
