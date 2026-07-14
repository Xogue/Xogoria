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
        const body = await response.json().catch(() => ({success: false, message: 'Invalid server response.'}));
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
})();
