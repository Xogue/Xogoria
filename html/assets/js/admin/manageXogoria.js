(() => {
    'use strict';

    const app = document.getElementById('adminApp');
    if (!app) return;

    const definitions = window.XOG_ADMIN_RESOURCES || {};
    const csrfToken = document.querySelector('meta[name="admin-csrf-token"]')?.content || '';
    const notice = document.getElementById('adminNotice');
    const sidebar = document.querySelector('.adminSidebar');
    const main = document.querySelector('.adminMain');
    const contextTitle = document.getElementById('adminContextTitle');
    const contextSubtitle = document.getElementById('adminContextSubtitle');

    const showNotice = (message, type = 'success') => {
        notice.textContent = message;
        notice.dataset.type = type;
        notice.hidden = false;
        window.clearTimeout(showNotice.timer);
        showNotice.timer = window.setTimeout(() => { notice.hidden = true; }, 5000);
    };

    const request = async (payload) => {
        const response = await fetch('/api/admin.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({...payload, csrfToken})
        });
        const responseText = await response.text();
        let body;
        try {
            body = JSON.parse(responseText);
        } catch (_) {
            const looksLikeLogin = response.redirected || response.headers.get('content-type')?.includes('text/html');
            const sessionHint = looksLikeLogin || response.status === 401 || response.status === 403 || response.status === 419
                ? ' Your admin session may have expired; refresh the page and sign in again.'
                : '';
            throw new Error(`The server returned an unreadable response (HTTP ${response.status}).${sessionHint}`);
        }
        if (!response.ok || body.success === false) throw new Error(body.message || 'Admin request failed.');
        return body;
    };

    const openSection = (name) => {
        if (!document.querySelector(`[data-admin-section="${CSS.escape(name)}"]`)) name = 'users';
        const activeSection = document.querySelector(`[data-admin-section="${CSS.escape(name)}"]`);
        document.querySelectorAll('[data-admin-section]').forEach(section => { section.hidden = section.dataset.adminSection !== name; });
        document.querySelectorAll('[data-admin-target]').forEach(button => button.classList.toggle('active', button.dataset.adminTarget === name));
        const heading = activeSection?.querySelector('.adminSectionHeader h1')?.textContent?.trim() || 'Admin';
        const description = activeSection?.querySelector('.adminSectionHeader p')?.textContent?.trim() || '';
        contextTitle.textContent = heading;
        contextSubtitle.textContent = description;
        contextSubtitle.hidden = description === '';
        document.title = `${heading} · Xogoria Admin`;
        if (main) main.scrollTop = 0;
        history.replaceState(null, '', `#${encodeURIComponent(name)}`);
    };

    const desktopAdminLayout = window.matchMedia('(min-width: 851px)');
    const handOffWheel = (source, destination, event) => {
        if (!desktopAdminLayout.matches || !source || !destination) return;
        if (event.ctrlKey) return;
        if (Math.abs(event.deltaY) <= Math.abs(event.deltaX)) return;

        const atTop = source.scrollTop <= 0;
        const atBottom = Math.ceil(source.scrollTop + source.clientHeight) >= source.scrollHeight;
        const sourceBlocked = (event.deltaY < 0 && atTop) || (event.deltaY > 0 && atBottom);
        if (!sourceBlocked) return;

        const destinationAtTop = destination.scrollTop <= 0;
        const destinationAtBottom = Math.ceil(destination.scrollTop + destination.clientHeight) >= destination.scrollHeight;
        const destinationBlocked = (event.deltaY < 0 && destinationAtTop) ||
            (event.deltaY > 0 && destinationAtBottom);
        if (destinationBlocked) return;

        event.preventDefault();
        destination.scrollBy({top: event.deltaY, behavior: 'auto'});
    };

    sidebar?.addEventListener('wheel', event => handOffWheel(sidebar, main, event), {passive: false});
    main?.addEventListener('wheel', event => handOffWheel(main, sidebar, event), {passive: false});

    document.querySelectorAll('[data-admin-target]').forEach(button => {
        button.addEventListener('click', () => openSection(button.dataset.adminTarget));
    });
    openSection(decodeURIComponent(location.hash.slice(1)) || 'users');

    const updateBooleanControl = control => {
        const wrapper = control.closest('[data-boolean-control]');
        if (!wrapper) return;
        wrapper.classList.toggle('is-on', control.checked);
        const value = wrapper.querySelector('[data-boolean-value]');
        if (value) value.textContent = control.checked ? 'Yes' : 'No';
    };
    app.addEventListener('change', event => {
        if (event.target.matches('[data-boolean-control] input[type="checkbox"]')) {
            updateBooleanControl(event.target);
        }
    });

    const rowEditSnapshots = new WeakMap();
    const captureRowValues = row => [...row.querySelectorAll('[data-field] input, [data-field] textarea, [data-field] select')].map(control => ({
        control,
        value: control.value,
        checked: control.type === 'checkbox' ? control.checked : null
    }));
    const restoreRowValues = row => {
        const snapshot = rowEditSnapshots.get(row) || [];
        snapshot.forEach(({control, value, checked}) => {
            if (control.type === 'checkbox') {
                control.checked = checked;
                updateBooleanControl(control);
            }
            else control.value = value;
        });
    };

    const setEditing = (row, editing, definition) => {
        const isNew = row.dataset.originalKey === '';
        row.classList.toggle('editing', editing);
        row.querySelectorAll('input, textarea, select').forEach(control => {
            const fieldName = control.closest('[data-field]')?.dataset.field || '';
            const field = definition.fields[fieldName] || {};
            const fieldCanEdit = field.editable !== false &&
                (isNew ? field.editableOnAdd !== false : field.editableOnEdit !== false) &&
                !field.generated &&
                !(field.immutable && !isNew);
            control.disabled = !editing || !fieldCanEdit;
        });
        const editButton = row.querySelector('[data-edit-row]');
        editButton.textContent = editing ? 'Save' : 'Edit';
        editButton.dataset.editRow = editing ? 'save' : '';
        const cancelButton = row.querySelector('[data-cancel-edit]');
        if (cancelButton) cancelButton.hidden = !editing;
    };

    const rowData = (row, definition) => {
        const data = {_originalKey: row.dataset.originalKey || ''};
        Object.keys(definition.fields).forEach(name => {
            const control = row.querySelector(`[data-field="${CSS.escape(name)}"] input, [data-field="${CSS.escape(name)}"] textarea, [data-field="${CSS.escape(name)}"] select`);
            data[name] = control?.type === 'checkbox' ? Boolean(control.checked) : (control?.value ?? '');
        });
        return data;
    };

    const makeControl = (name, field) => {
        let control;
        if (field.type === 'textarea') control = document.createElement('textarea');
        else if (field.type === 'select') {
            control = document.createElement('select');
            (field.options || []).forEach(value => control.add(new Option(value, value)));
        } else {
            control = document.createElement('input');
            control.type = field.type === 'boolean' ? 'checkbox' : (field.type || 'text');
        }
        if (field.immutable) control.setAttribute('data-immutable', '');
        if (field.required) control.required = true;
        if (control.type === 'checkbox') control.setAttribute('aria-label', field.label || name);
        let defaultValue = field.defaultOnAdd;
        if (defaultValue === '@today') {
            const now = new Date();
            defaultValue = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
        }
        if (control.type === 'checkbox') control.checked = Boolean(defaultValue);
        else if (defaultValue !== undefined) control.value = String(defaultValue);
        if (control.type === 'checkbox') {
            const wrapper = document.createElement('label');
            wrapper.className = `adminBooleanControl${control.checked ? ' is-on' : ''}`;
            wrapper.dataset.booleanControl = '';
            const switchVisual = document.createElement('span');
            switchVisual.className = 'adminBooleanSwitch';
            switchVisual.setAttribute('aria-hidden', 'true');
            const displayValue = document.createElement('span');
            displayValue.className = 'adminBooleanValue';
            displayValue.dataset.booleanValue = '';
            displayValue.textContent = control.checked ? 'Yes' : 'No';
            wrapper.append(control, switchVisual, displayValue);
            return wrapper;
        }
        return control;
    };

    const refreshRowStripes = table => {
        const visibleRows = [...table.querySelectorAll('[data-resource-row]')].filter(row => !row.hidden);
        table.querySelectorAll('[data-resource-row]').forEach(row => row.classList.remove('adminRowOdd', 'adminRowEven'));
        visibleRows.forEach((row, index) => row.classList.add(index % 2 === 0 ? 'adminRowOdd' : 'adminRowEven'));
        const emptyRow = table.querySelector('[data-empty-row]');
        if (emptyRow) emptyRow.hidden = visibleRows.length !== 0;
    };

    const controlValue = (row, fieldName) => {
        const control = row.querySelector(`[data-field="${CSS.escape(fieldName)}"] input, [data-field="${CSS.escape(fieldName)}"] textarea, [data-field="${CSS.escape(fieldName)}"] select`);
        return control?.type === 'checkbox' ? (control.checked ? '1' : '0') : (control?.value || '');
    };

    document.querySelectorAll('[data-resource-table]').forEach(table => {
        const resource = table.dataset.resourceTable;
        const definition = definitions[resource];
        const search = document.querySelector(`[data-resource-search="${CSS.escape(resource)}"]`);
        const collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});

        const filterRows = () => {
            const query = (search?.value || '').trim().toLocaleLowerCase();
            table.querySelectorAll('[data-resource-row]').forEach(row => {
                const searchableText = [...row.querySelectorAll('[data-field]')]
                    .map(cell => controlValue(row, cell.dataset.field))
                    .join(' ')
                    .toLocaleLowerCase();
                row.hidden = query !== '' && !searchableText.includes(query);
            });
            refreshRowStripes(table);
        };

        search?.addEventListener('input', filterRows);
        table.querySelectorAll('[data-sort-column]').forEach(button => button.addEventListener('click', () => {
            const fieldName = button.dataset.sortColumn;
            const field = definition.fields[fieldName] || {};
            const header = button.closest('th');
            const ascending = header.getAttribute('aria-sort') !== 'ascending';
            table.querySelectorAll('th[aria-sort]').forEach(cell => cell.setAttribute('aria-sort', 'none'));
            header.setAttribute('aria-sort', ascending ? 'ascending' : 'descending');

            const rows = [...table.querySelectorAll('[data-resource-row]')];
            rows.sort((left, right) => {
                const leftValue = controlValue(left, fieldName);
                const rightValue = controlValue(right, fieldName);
                const comparison = ['integer', 'boolean'].includes(field.type)
                    ? Number(leftValue) - Number(rightValue)
                    : collator.compare(leftValue, rightValue);
                return ascending ? comparison : -comparison;
            });
            const tbody = table.tBodies[0];
            rows.forEach(row => tbody.append(row));
            tbody.append(table.querySelector('[data-empty-row]'));
            refreshRowStripes(table);
        }));

        refreshRowStripes(table);
    });

    const addRow = (resource) => {
        const definition = definitions[resource];
        const tbody = document.querySelector(`[data-resource-table="${CSS.escape(resource)}"] tbody`);
        const row = document.createElement('tr');
        row.dataset.resourceRow = '';
        row.dataset.originalKey = '';
        Object.entries(definition.fields).filter(([, field]) => !field.hidden).forEach(([name, field]) => {
            const cell = document.createElement('td');
            const fieldClass = field.type === 'textarea'
                ? 'long'
                : String(field.type || 'text').replace(/[^a-z0-9_-]/gi, '');
            cell.className = `adminField adminField--${fieldClass}`;
            cell.dataset.field = name;
            cell.append(makeControl(name, field));
            row.append(cell);
        });
        const actions = document.createElement('td');
        actions.className = 'adminRowActions';
        actions.innerHTML = '<button type="button" class="adminIconButton" data-edit-row="save">Save</button><button type="button" class="adminIconButton" data-cancel-edit>Cancel edit</button>';
        row.append(actions);
        tbody.prepend(row);
        setEditing(row, true, definition);
        row.querySelector('input:not([type="checkbox"]):not(:disabled), textarea:not(:disabled), select:not(:disabled)')?.focus();
        refreshRowStripes(tbody.closest('table'));
    };

    document.querySelectorAll('[data-add-resource]').forEach(button => button.addEventListener('click', () => addRow(button.dataset.addResource)));

    app.addEventListener('click', async event => {
        const edit = event.target.closest('[data-edit-row]');
        const cancel = event.target.closest('[data-cancel-edit]');
        const remove = event.target.closest('[data-delete-row]');
        if (!edit && !cancel && !remove) return;
        const row = event.target.closest('[data-resource-row]');
        const table = row.closest('[data-resource-table]');
        const resource = table.dataset.resourceTable;
        const definition = definitions[resource];

        if (cancel) {
            if (row.dataset.originalKey === '') {
                row.remove();
                refreshRowStripes(table);
                return;
            }
            restoreRowValues(row);
            rowEditSnapshots.delete(row);
            setEditing(row, false, definition);
            return;
        }

        if (edit) {
            if (!row.classList.contains('editing')) {
                rowEditSnapshots.set(row, captureRowValues(row));
                setEditing(row, true, definition);
                return;
            }
            try {
                const result = await request({domain: 'resource', action: 'save', resource, data: rowData(row, definition)});
                showNotice(`${definition.label} saved.`);
                location.reload();
            } catch (error) { showNotice(error.message, 'error'); }
        }

        if (remove) {
            if (row.dataset.originalKey === '') {
                const resourceTable = row.closest('[data-resource-table]');
                row.remove();
                refreshRowStripes(resourceTable);
                return;
            }
            if (!confirm(`Delete this ${definition.label.toLowerCase()} record? This cannot be undone.`)) return;
            try {
                await request({domain: 'resource', action: 'delete', resource, key: row.dataset.originalKey});
                row.remove();
                refreshRowStripes(table);
                showNotice(`${definition.label} record deleted.`);
            } catch (error) { showNotice(error.message, 'error'); }
        }
    });

    const configSelect = document.getElementById('adminConfigSelect');
    const selectConfigEditor = configKey => {
        document.querySelectorAll('[data-config-card]').forEach(card => {
            card.hidden = card.dataset.configCard !== configKey;
        });
    };
    if (configSelect) {
        configSelect.addEventListener('change', () => selectConfigEditor(configSelect.value));
        selectConfigEditor(configSelect.value);
    }

    document.querySelectorAll('[data-save-config]').forEach(button => button.addEventListener('click', async () => {
        const card = button.closest('[data-config-card]');
        const editor = card.querySelector('.adminJsonEditor');
        try {
            JSON.parse(editor.value);
            await request({domain: 'config', action: 'save', config: card.dataset.configCard, source: editor.value});
            showNotice(`${card.dataset.configCard} configuration saved. Restart long-running workers if needed.`);
        } catch (error) { showNotice(error.message, 'error'); }
    }));

    const communityEditor = document.getElementById('communityEditor');
    if (communityEditor) {
        const status = document.getElementById('communityEditorStatus');
        let savedSource = communityEditor.value;
        let savedRevision = communityEditor.dataset.revision || '';
        const editorSnapshot = () => ({
            value: communityEditor.value,
            start: communityEditor.selectionStart,
            end: communityEditor.selectionEnd
        });
        let editorHistory = [editorSnapshot()];
        let editorHistoryIndex = 0;
        let lastHistoryTime = 0;
        let lastInputType = '';

        const updateEditorStatus = () => {
            const changed = communityEditor.value !== savedSource;
            status.textContent = changed ? 'Unsaved changes' : 'All changes saved';
            status.classList.toggle('unsaved', changed);
        };

        const recordEditorHistory = (inputType = '', force = false) => {
            const state = editorSnapshot();
            if (state.value === editorHistory[editorHistoryIndex].value) return;
            const now = Date.now();
            const mergeTyping = !force && inputType === 'insertText' && lastInputType === 'insertText' && now - lastHistoryTime < 750;
            if (mergeTyping) {
                editorHistory[editorHistoryIndex] = state;
            } else {
                editorHistory = editorHistory.slice(0, editorHistoryIndex + 1);
                editorHistory.push(state);
                editorHistoryIndex++;
            }
            lastHistoryTime = now;
            lastInputType = inputType;
        };

        const restoreEditorHistory = index => {
            if (index < 0 || index >= editorHistory.length) return;
            editorHistoryIndex = index;
            const state = editorHistory[index];
            communityEditor.value = state.value;
            communityEditor.setSelectionRange(state.start, state.end);
            communityEditor.focus();
            lastInputType = '';
            updateEditorStatus();
        };
        const formats = {
            heading1: {line: '# '}, heading2: {line: '## '}, heading3: {line: '### '},
            toc: {block: '{toc: Short description}'},
            bold: {before: '**', after: '**', sample: 'bold text'},
            italic: {before: '*', after: '*', sample: 'italic text'},
            strike: {before: '~~', after: '~~', sample: 'strikethrough text'},
            link: {before: '[', after: '](https://example.com)', sample: 'link text'},
            button: {before: '[button: ', after: '](https://example.com)', sample: 'Button label'},
            bullets: {line: '- ', sample: 'List item'},
            numbers: {line: '1. ', sample: 'List item'},
            quote: {line: '> ', sample: 'Quote'},
            code: {before: '```\n', after: '\n```', sample: 'code'},
            table: {block: '| Name | Details |\n| --- | --- |\n| Item | Description |'},
            divider: {block: '\n---\n'},
            note: {before: ':::note Note title\n', after: '\n:::', sample: 'Helpful information'},
            tip: {before: ':::tip Tip title\n', after: '\n:::', sample: 'Helpful tip'},
            warning: {before: ':::warning Important\n', after: '\n:::', sample: 'Important information'},
            cards: {block: ':::cards Section title\n### First card\nFirst card details.\n+++\n### Second card\nSecond card details.\n:::'}
        };

        const replaceSelection = (format) => {
            const start = communityEditor.selectionStart;
            const end = communityEditor.selectionEnd;
            const selected = communityEditor.value.slice(start, end);
            let replacement;
            let selectStart;
            let selectEnd;
            if (format.line) {
                const lineStart = communityEditor.value.lastIndexOf('\n', start - 1) + 1;
                const nextBreak = communityEditor.value.indexOf('\n', end);
                const lineEnd = selected ? end : (nextBreak === -1 ? communityEditor.value.length : nextBreak);
                const content = selected || communityEditor.value.slice(lineStart, lineEnd) || format.sample || '';
                replacement = content.split('\n').map(line => format.line + line).join('\n');
                communityEditor.setRangeText(replacement, selected ? start : lineStart, lineEnd, 'end');
            } else if (format.block) {
                replacement = format.block;
                communityEditor.setRangeText(replacement, start, end, 'end');
            } else {
                const content = selected || format.sample || '';
                replacement = format.before + content + format.after;
                communityEditor.setRangeText(replacement, start, end, 'end');
                selectStart = start + format.before.length;
                selectEnd = selectStart + content.length;
                communityEditor.setSelectionRange(selectStart, selectEnd);
            }
            communityEditor.focus();
            recordEditorHistory('format', true);
            updateEditorStatus();
        };

        document.querySelectorAll('[data-format]').forEach(button => button.addEventListener('click', () => {
            const format = formats[button.dataset.format];
            if (format) replaceSelection(format);
        }));

        communityEditor.addEventListener('input', event => {
            recordEditorHistory(event.inputType || '', false);
            updateEditorStatus();
        });

        communityEditor.addEventListener('keydown', event => {
            const modifier = event.ctrlKey || event.metaKey;
            if (!modifier) return;
            const key = event.key.toLowerCase();
            const undo = key === 'z' && !event.shiftKey;
            const redo = key === 'y' || (key === 'z' && event.shiftKey);
            if (!undo && !redo) return;
            event.preventDefault();
            restoreEditorHistory(editorHistoryIndex + (undo ? -1 : 1));
        });

        document.querySelector('[data-preview-community]')?.addEventListener('click', async buttonEvent => {
            const button = buttonEvent.currentTarget;
            button.disabled = true;
            try {
                const result = await request({domain: 'community', action: 'preview', source: communityEditor.value});
                document.getElementById('communityPreview').innerHTML = result.html;
                document.getElementById('communityPreviewPanel').hidden = false;
                document.getElementById('communityPreviewPanel').scrollIntoView({behavior: 'smooth', block: 'start'});
            } catch (error) { showNotice(error.message, 'error'); }
            finally { button.disabled = false; }
        });

        document.querySelector('[data-save-community]')?.addEventListener('click', async buttonEvent => {
            const button = buttonEvent.currentTarget;
            button.disabled = true;
            try {
                const result = await request({domain: 'community', action: 'save', source: communityEditor.value, revision: savedRevision});
                savedSource = communityEditor.value;
                savedRevision = result.revision;
                communityEditor.dataset.revision = savedRevision;
                updateEditorStatus();
                document.getElementById('communityPreview').innerHTML = result.html;
                document.getElementById('communityPreviewPanel').hidden = false;
                showNotice('Community page saved and published.');
            } catch (error) { showNotice(error.message, 'error'); }
            finally { button.disabled = false; }
        });
    }
})();
