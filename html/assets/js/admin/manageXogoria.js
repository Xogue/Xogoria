(() => {
    'use strict';

    const app = document.getElementById('adminApp');
    if (!app) return;

    const definitions = window.XOG_ADMIN_RESOURCES || {};
    const csrfToken = document.querySelector('meta[name="admin-csrf-token"]')?.content || '';
    const notice = document.getElementById('adminNotice');

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
        document.querySelectorAll('[data-admin-section]').forEach(section => { section.hidden = section.dataset.adminSection !== name; });
        document.querySelectorAll('[data-admin-target]').forEach(button => button.classList.toggle('active', button.dataset.adminTarget === name));
        history.replaceState(null, '', `#${encodeURIComponent(name)}`);
    };

    document.querySelectorAll('[data-admin-target]').forEach(button => {
        button.addEventListener('click', () => openSection(button.dataset.adminTarget));
    });
    openSection(decodeURIComponent(location.hash.slice(1)) || 'users');

    const setEditing = (row, editing) => {
        row.classList.toggle('editing', editing);
        row.querySelectorAll('input, textarea, select').forEach(control => {
            const immutableExisting = control.hasAttribute('data-immutable') && row.dataset.originalKey !== '';
            control.disabled = !editing || immutableExisting;
        });
        const editButton = row.querySelector('[data-edit-row]');
        editButton.textContent = editing ? 'Save' : 'Edit';
        editButton.dataset.editRow = editing ? 'save' : '';
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
        return control;
    };

    const addRow = (resource) => {
        const definition = definitions[resource];
        const tbody = document.querySelector(`[data-resource-table="${CSS.escape(resource)}"] tbody`);
        const row = document.createElement('tr');
        row.dataset.resourceRow = '';
        row.dataset.originalKey = '';
        Object.entries(definition.fields).forEach(([name, field]) => {
            const cell = document.createElement('td');
            cell.dataset.field = name;
            cell.append(makeControl(name, field));
            row.append(cell);
        });
        const actions = document.createElement('td');
        actions.className = 'adminRowActions';
        actions.innerHTML = '<button type="button" class="adminIconButton" data-edit-row="save">Save</button><button type="button" class="adminIconButton adminDangerText" data-delete-row>Cancel</button>';
        row.append(actions);
        tbody.prepend(row);
        setEditing(row, true);
        row.querySelector('input:not([type="checkbox"]), textarea, select')?.focus();
    };

    document.querySelectorAll('[data-add-resource]').forEach(button => button.addEventListener('click', () => addRow(button.dataset.addResource)));

    app.addEventListener('click', async event => {
        const edit = event.target.closest('[data-edit-row]');
        const remove = event.target.closest('[data-delete-row]');
        if (!edit && !remove) return;
        const row = event.target.closest('[data-resource-row]');
        const table = row.closest('[data-resource-table]');
        const resource = table.dataset.resourceTable;
        const definition = definitions[resource];

        if (edit) {
            if (!row.classList.contains('editing')) { setEditing(row, true); return; }
            try {
                const result = await request({domain: 'resource', action: 'save', resource, data: rowData(row, definition)});
                showNotice(`${definition.label} saved.`);
                location.reload();
            } catch (error) { showNotice(error.message, 'error'); }
        }

        if (remove) {
            if (row.dataset.originalKey === '') { row.remove(); return; }
            if (!confirm(`Delete this ${definition.label.toLowerCase()} record? This cannot be undone.`)) return;
            try {
                await request({domain: 'resource', action: 'delete', resource, key: row.dataset.originalKey});
                row.remove();
                showNotice(`${definition.label} record deleted.`);
            } catch (error) { showNotice(error.message, 'error'); }
        }
    });

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
