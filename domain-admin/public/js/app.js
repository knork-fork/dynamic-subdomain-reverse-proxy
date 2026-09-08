(() => {
    const page = document.querySelector('.page');
    const csrfToken = page.dataset.csrfToken;
    const tbody = document.getElementById('domains-body');
    const addForm = document.getElementById('add-form');
    const addDomain = document.getElementById('add-domain');
    const addPort = document.getElementById('add-port');
    const flash = document.getElementById('flash');
    let isEditing = false;

    function showFlash(message, type) {
        flash.textContent = message;
        flash.className = `flash ${type}`;
        flash.hidden = false;
    }

    function hideFlash() {
        flash.hidden = true;
    }

    async function api(path, options = {}) {
        const response = await fetch(path, {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
                ...(options.headers || {}),
            },
        });

        const body = await response.json().catch(() => null);

        if (!response.ok) {
            throw new Error((body && body.error) || `Request failed (${response.status})`);
        }

        return body;
    }

    function renderRows(items) {
        tbody.innerHTML = '';

        if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="empty">No domains configured yet.</td></tr>';
            return;
        }

        for (const item of items) {
            const row = document.createElement('tr');

            const domainCell = document.createElement('td');
            domainCell.className = 'domain-cell';
            domainCell.textContent = item.domain;

            const portCell = document.createElement('td');
            portCell.textContent = item.port;

            const statusCell = document.createElement('td');
            const dot = document.createElement('span');
            dot.className = `status-dot ${item.online ? 'online' : 'offline'}`;
            statusCell.appendChild(dot);
            statusCell.appendChild(document.createTextNode(item.online ? 'Online' : 'Offline'));

            const actionsCell = document.createElement('td');
            actionsCell.className = 'actions-col';
            const actions = document.createElement('div');
            actions.className = 'actions';

            const editBtn = document.createElement('button');
            editBtn.className = 'icon-btn edit';
            editBtn.title = 'Edit';
            editBtn.textContent = '✏️';
            editBtn.addEventListener('click', () => startEdit(row, item, actions));

            const deleteBtn = document.createElement('button');
            deleteBtn.className = 'icon-btn delete';
            deleteBtn.title = 'Remove';
            deleteBtn.textContent = '🗑️';
            deleteBtn.addEventListener('click', () => removeDomain(item.domain));

            actions.appendChild(editBtn);
            actions.appendChild(deleteBtn);
            actionsCell.appendChild(actions);

            row.appendChild(domainCell);
            row.appendChild(portCell);
            row.appendChild(statusCell);
            row.appendChild(actionsCell);

            tbody.appendChild(row);
        }
    }

    function startEdit(row, item, actions) {
        isEditing = true;

        const domainCell = row.children[0];
        const portCell = row.children[1];

        domainCell.innerHTML = '';
        portCell.innerHTML = '';
        actions.innerHTML = '';

        const domainInput = document.createElement('input');
        domainInput.type = 'text';
        domainInput.className = 'domain-edit-input';
        domainInput.value = item.domain;

        const portInput = document.createElement('input');
        portInput.type = 'number';
        portInput.className = 'port-edit-input';
        portInput.min = '1';
        portInput.max = '65535';
        portInput.value = item.port;

        const stopEditing = () => {
            isEditing = false;
        };

        const save = async () => {
            const newDomain = domainInput.value.trim();
            const newPort = parseInt(portInput.value, 10);

            if (newDomain === '') {
                showFlash('Domain cannot be empty.', 'error');
                return;
            }
            if (!Number.isInteger(newPort) || newPort < 1 || newPort > 65535) {
                showFlash('Port must be between 1 and 65535.', 'error');
                return;
            }

            try {
                const items = await api(`/api/domains/${encodeURIComponent(item.domain)}`, {
                    method: 'PUT',
                    body: JSON.stringify({ domain: newDomain, port: newPort }),
                });
                hideFlash();
                stopEditing();
                renderRows(items);
            } catch (err) {
                showFlash(err.message, 'error');
            }
        };

        const cancel = () => {
            stopEditing();
            loadDomains();
        };

        const saveBtn = document.createElement('button');
        saveBtn.className = 'icon-btn save';
        saveBtn.title = 'Save';
        saveBtn.textContent = '✅';
        saveBtn.addEventListener('click', save);

        const cancelBtn = document.createElement('button');
        cancelBtn.className = 'icon-btn cancel';
        cancelBtn.title = 'Cancel';
        cancelBtn.textContent = '✖️';
        cancelBtn.addEventListener('click', cancel);

        for (const input of [domainInput, portInput]) {
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') save();
                if (e.key === 'Escape') cancel();
            });
        }

        actions.appendChild(saveBtn);
        actions.appendChild(cancelBtn);
        domainCell.appendChild(domainInput);
        portCell.appendChild(portInput);

        domainInput.focus();
        domainInput.select();
    }

    async function removeDomain(domain) {
        if (!confirm(`Remove ${domain}?`)) {
            return;
        }

        try {
            const items = await api(`/api/domains/${encodeURIComponent(domain)}`, { method: 'DELETE' });
            hideFlash();
            renderRows(items);
        } catch (err) {
            showFlash(err.message, 'error');
        }
    }

    async function loadDomains() {
        if (isEditing) {
            return;
        }

        try {
            const items = await api('/api/domains');
            renderRows(items);
        } catch (err) {
            showFlash(err.message, 'error');
        }
    }

    addForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const domain = addDomain.value.trim();
        const port = parseInt(addPort.value, 10);

        try {
            const items = await api('/api/domains', {
                method: 'POST',
                body: JSON.stringify({ domain, port }),
            });
            hideFlash();
            addDomain.value = '';
            addPort.value = '';
            renderRows(items);
        } catch (err) {
            showFlash(err.message, 'error');
        }
    });

    loadDomains();
    setInterval(loadDomains, 10000);
})();
