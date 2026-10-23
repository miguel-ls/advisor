/**
 * ADVISOR-ERP Single Page Application Client
 */

const API_BASE = '../backend/api';

// Application State
const state = {
    token: localStorage.getItem('advisor_token') || null,
    user: JSON.parse(localStorage.getItem('advisor_user') || 'null'),
    preToken: null,
    pendingUserId: null,
    selectedEmpresa: JSON.parse(localStorage.getItem('advisor_empresa') || 'null'),
    selectedAnio: localStorage.getItem('advisor_anio') || null,
    activeMenu: null,
    theme: localStorage.getItem('advisor_theme') || 'rojo-granate',
    menuTree: []
};

// Initialize App
document.addEventListener('DOMContentLoaded', () => {
    applyTheme(state.theme);
    checkAuthAndInit();
});

function applyTheme(themeName) {
    document.body.className = '';
    document.body.classList.add(`theme-${themeName}`);
    state.theme = themeName;
    localStorage.setItem('advisor_theme', themeName);
}

function checkAuthAndInit() {
    if (!state.token || !state.user) {
        showScreen('login-screen');
        return;
    }

    if (!state.selectedEmpresa || !state.selectedAnio) {
        loadCompaniesScreen();
        return;
    }

    // Fully authenticated and context selected
    showMainApp();
}

function showScreen(screenId) {
    document.querySelectorAll('.app-screen').forEach(el => el.classList.add('hidden'));
    const target = document.getElementById(screenId);
    if (target) target.classList.remove('hidden');
}

// ---------------------------------------------------------------------
// Auth Handlers (Login & 2FA)
// ---------------------------------------------------------------------
async function handleLogin(e) {
    e.preventDefault();
    const username = document.getElementById('login-username').value;
    const password = document.getElementById('login-password').value;
    const alertBox = document.getElementById('login-alert');

    alertBox.classList.add('hidden');

    try {
        const response = await fetch(`${API_BASE}/auth.php?action=login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });
        const res = await response.json();

        if (res.status === 'success') {
            if (res.requires_2fa) {
                state.preToken = res.pre_token;
                state.pendingUserId = res.user_id;
                document.getElementById('login-step-1').classList.add('hidden');
                document.getElementById('login-step-2').classList.remove('hidden');
            } else {
                completeAuth(res.token, res.user);
            }
        } else {
            showAlert(alertBox, res.message || 'Error al iniciar sesión');
        }
    } catch (err) {
        showAlert(alertBox, 'Error de conexión con el servidor API');
    }
}

async function handleVerify2FA(e) {
    e.preventDefault();
    const code = document.getElementById('2fa-code').value;
    const alertBox = document.getElementById('2fa-alert');

    alertBox.classList.add('hidden');

    try {
        const response = await fetch(`${API_BASE}/auth.php?action=verify_2fa`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: state.pendingUserId, code })
        });
        const res = await response.json();

        if (res.status === 'success') {
            completeAuth(res.token, res.user);
        } else {
            showAlert(alertBox, res.message || 'Código 2FA incorrecto');
        }
    } catch (err) {
        showAlert(alertBox, 'Error de conexión con el servidor API');
    }
}

function completeAuth(token, user) {
    state.token = token;
    state.user = user;
    localStorage.setItem('advisor_token', token);
    localStorage.setItem('advisor_user', JSON.stringify(user));

    loadCompaniesScreen();
}

function logout() {
    localStorage.clear();
    state.token = null;
    state.user = null;
    state.selectedEmpresa = null;
    state.selectedAnio = null;
    window.location.reload();
}

// ---------------------------------------------------------------------
// Screen 2: Company and Fiscal Year Selection
// ---------------------------------------------------------------------
async function loadCompaniesScreen() {
    showScreen('company-select-screen');
    const selectEmpresa = document.getElementById('select-empresa');
    const selectAnio = document.getElementById('select-anio');

    try {
        // Fetch user companies via Stored Procedure
        const res = await callSP('sp_empresas', 'CONSULTAR_POR_USUARIO', { usuario_id: state.user.id });
        selectEmpresa.innerHTML = '<option value="">-- Seleccione Empresa --</option>';

        if (res.status === 'success' && res.data) {
            res.data.forEach(emp => {
                selectEmpresa.innerHTML += `<option value="${emp.id}" data-obj='${JSON.stringify(emp)}'>${emp.ruc} - ${emp.razon_social}</option>`;
            });
        }
    } catch (err) {
        console.error('Error al cargar empresas', err);
    }
}

async function onEmpresaChange() {
    const selectEmpresa = document.getElementById('select-empresa');
    const selectAnio = document.getElementById('select-anio');
    const empId = selectEmpresa.value;

    selectAnio.innerHTML = '<option value="">-- Seleccione Año --</option>';
    selectAnio.disabled = !empId;

    if (!empId) return;

    try {
        const res = await callSP('sp_empresa_periodos', 'CONSULTAR_ANIOS_POR_EMPRESA', { empresa_id: empId });
        if (res.status === 'success' && res.data) {
            res.data.forEach(row => {
                selectAnio.innerHTML += `<option value="${row.anio}">${row.anio}</option>`;
            });
        }
    } catch (err) {
        console.error('Error al cargar años', err);
    }
}

function confirmCompanyContext(e) {
    e.preventDefault();
    const selectEmpresa = document.getElementById('select-empresa');
    const selectAnio = document.getElementById('select-anio');

    if (!selectEmpresa.value || !selectAnio.value) {
        alert('Debe seleccionar una empresa y un año.');
        return;
    }

    const selectedOption = selectEmpresa.options[selectEmpresa.selectedIndex];
    state.selectedEmpresa = JSON.parse(selectedOption.getAttribute('data-obj'));
    state.selectedAnio = selectAnio.value;

    localStorage.setItem('advisor_empresa', JSON.stringify(state.selectedEmpresa));
    localStorage.setItem('advisor_anio', state.selectedAnio);

    showMainApp();
}

// ---------------------------------------------------------------------
// Screen 3: Main Dashboard Layout & Dynamic Navigation Tree
// ---------------------------------------------------------------------
async function showMainApp() {
    showScreen('main-app-screen');

    // Update Header Context & User Badge
    document.getElementById('lbl-empresa-context').textContent = `${state.selectedEmpresa.razon_social} (${state.selectedAnio})`;
    document.getElementById('lbl-user-name').textContent = state.user.nombre_completo;
    document.getElementById('lbl-user-role').textContent = state.user.username;
    document.getElementById('lbl-user-avatar').textContent = state.user.nombre_completo.charAt(0).toUpperCase();

    // Load System Configuration (Theme)
    await loadSystemConfiguration();

    // Load Dynamic Sidebar Menu Tree
    await buildDynamicMenuTree();
}

async function loadSystemConfiguration() {
    try {
        const res = await callSP('sp_configuracion', 'CONSULTAR', {});
        if (res.status === 'success' && res.data && res.data.length > 0) {
            const config = res.data[0];
            if (config.tema_color) {
                applyTheme(config.tema_color);
            }
            if (config.nombre_sistema) {
                document.querySelectorAll('.lbl-system-title').forEach(el => el.textContent = config.nombre_sistema);
            }
        }
    } catch (err) {
        console.error('Error al cargar configuración', err);
    }
}

async function buildDynamicMenuTree() {
    const container = document.getElementById('sidebar-tree-container');
    container.innerHTML = '';

    try {
        const res = await callSP('sp_usuario_permisos', 'CONSULTAR_MENU_TREE', { usuario_id: state.user.id });
        if (res.status !== 'success' || !res.data) return;

        // Group menus by module
        const modulesMap = {};
        res.data.forEach(item => {
            if (!modulesMap[item.modulo_id]) {
                modulesMap[item.modulo_id] = {
                    id: item.modulo_id,
                    codigo: item.modulo_codigo,
                    nombre: item.modulo_nombre,
                    icono: item.modulo_icono,
                    menus: []
                };
            }
            modulesMap[item.modulo_id].menus.push({
                id: item.menu_id,
                nombre: item.menu_nombre,
                pagina_url: item.menu_pagina_url,
                icono: item.menu_icono
            });
        });

        // Render Tree Structure
        let html = '<div class="tree-root-label"><i class="fas fa-sitemap"></i> ADVISOR SYSTEM</div>';

        Object.values(modulesMap).forEach(mod => {
            html += `
                <div class="tree-module">
                    <div class="tree-module-header" onclick="toggleModuleTree(${mod.id})">
                        <span><i class="${mod.icono}"></i> ${mod.nombre}</span>
                        <i class="fas fa-chevron-down" id="arrow-mod-${mod.id}"></i>
                    </div>
                    <ul class="tree-sub-menu" id="sub-menu-${mod.id}">
            `;

            mod.menus.forEach(menu => {
                html += `
                    <li class="tree-sub-item">
                        <a href="#" onclick="loadPageContent('${menu.pagina_url}', '${menu.nombre}', event)">
                            <i class="${menu.icono}"></i> ${menu.nombre}
                        </a>
                    </li>
                `;
            });

            html += `</ul></div>`;
        });

        container.innerHTML = html;

        // Load first available menu by default if exists
        const firstMenu = res.data[0];
        if (firstMenu) {
            loadPageContent(firstMenu.menu_pagina_url, firstMenu.menu_nombre);
        }

    } catch (err) {
        container.innerHTML = '<div class="alert alert-danger" style="margin:10px;">Error al cargar el menú</div>';
    }
}

function toggleModuleTree(modId) {
    const subMenu = document.getElementById(`sub-menu-${modId}`);
    const arrow = document.getElementById(`arrow-mod-${modId}`);
    if (subMenu) {
        if (subMenu.style.display === 'none') {
            subMenu.style.display = 'block';
            arrow.className = 'fas fa-chevron-down';
        } else {
            subMenu.style.display = 'none';
            arrow.className = 'fas fa-chevron-right';
        }
    }
}

// ---------------------------------------------------------------------
// Dynamic Page Loader (Main Area Router)
// ---------------------------------------------------------------------
function loadPageContent(pageUrl, title, event) {
    if (event) event.preventDefault();

    document.getElementById('lbl-navbar-page-title').textContent = title;
    const body = document.getElementById('main-content-body');
    body.innerHTML = '<div style="text-align:center; padding: 40px;"><i class="fas fa-spinner fa-spin fa-2x"></i> Cargando...</div>';

    // Route to Maintenance Renderers
    switch (pageUrl) {
        case 'usuarios.php':
            renderUsuariosPage(body);
            break;
        case 'empresas.php':
            renderEmpresasPage(body);
            break;
        case 'empresa_periodos.php':
            renderEmpresaPeriodosPage(body);
            break;
        case 'modulos.php':
            renderModulosPage(body);
            break;
        case 'menus.php':
            renderMenusPage(body);
            break;
        case 'usuario_permisos.php':
            renderUsuarioPermisosPage(body);
            break;
        case 'configuracion.php':
            renderConfiguracionPage(body);
            break;
        case 'clientes.php':
            renderClientesPage(body);
            break;
        default:
            body.innerHTML = `
                <div class="card">
                    <div class="card-header"><span class="card-title">${title}</span></div>
                    <p style="padding: 20px;">Módulo en construcción (${pageUrl}). Los datos se conectan mediante el procedimiento almacenado correspondiente.</p>
                </div>
            `;
            break;
    }
}

// ---------------------------------------------------------------------
// API Helper
// ---------------------------------------------------------------------
async function callSP(sp, accion, params = {}) {
    const response = await fetch(`${API_BASE}/sp_handler.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${state.token}`
        },
        body: JSON.stringify({
            sp: sp,
            accion: accion,
            ...params
        })
    });
    return await response.json();
}

function showAlert(alertEl, msg, type = 'danger') {
    alertEl.className = `alert alert-${type}`;
    alertEl.textContent = msg;
    alertEl.classList.remove('hidden');
}

// ---------------------------------------------------------------------
// Page Renderers (Maintenance CRUD Screens)
// ---------------------------------------------------------------------

// 1. Mantenimiento de Usuarios
async function renderUsuariosPage(container) {
    const res = await callSP('sp_usuarios', 'CONSULTAR');
    const list = res.data || [];

    let rows = list.map(u => `
        <tr>
            <td>${u.id}</td>
            <td><strong>${u.username}</strong></td>
            <td>${u.nombre_completo}</td>
            <td>${u.email}</td>
            <td><span class="badge ${u.dos_factores_activo ? 'badge-success' : 'badge-secondary'}">${u.dos_factores_activo ? 'Activo (123456)' : 'Inactivo'}</span></td>
            <td><span class="badge ${u.estado === 'A' ? 'badge-success' : 'badge-danger'}">${u.estado === 'A' ? 'Activo' : 'Inactivo'}</span></td>
            <td>
                <button class="btn-action btn-edit" onclick="openUsuarioModal(${u.id})"><i class="fas fa-edit"></i> Editar</button>
                <button class="btn-action btn-delete" onclick="deleteUsuario(${u.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('');

    container.innerHTML = `
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-users-cog"></i> Mantenimiento de Usuarios</span>
                <button class="btn btn-sm" onclick="openUsuarioModal(0)" style="width:auto;"><i class="fas fa-user-plus"></i> Nuevo Usuario</button>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th><th>Usuario</th><th>Nombre Completo</th><th>Email</th><th>2FA Status</th><th>Estado</th><th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        </div>
    `;
}

async function openUsuarioModal(id) {
    let u = { id: 0, username: '', nombre_completo: '', email: '', dos_factores_activo: 1, dos_factores_secret: '123456', estado: 'A' };
    if (id > 0) {
        const res = await callSP('sp_usuarios', 'CONSULTAR', { id });
        if (res.data && res.data.length > 0) u = res.data[0];
    }

    const modalHtml = `
        <div class="modal-overlay" id="usr-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="modal-title">${id > 0 ? 'Editar' : 'Nuevo'} Usuario</span>
                    <button class="modal-close" onclick="closeModal('usr-modal')">&times;</button>
                </div>
                <form onsubmit="saveUsuario(event, ${id})">
                    <div class="form-group">
                        <label>Usuario (Login)</label>
                        <input type="text" id="m-username" class="form-control" value="${u.username}" required>
                    </div>
                    <div class="form-group">
                        <label>Contraseña ${id > 0 ? '(Dejar en blanco para mantener)' : ''}</label>
                        <input type="password" id="m-password" class="form-control" ${id === 0 ? 'required' : ''}>
                    </div>
                    <div class="form-group">
                        <label>Nombre Completo</label>
                        <input type="text" id="m-nombre" class="form-control" value="${u.nombre_completo}" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" id="m-email" class="form-control" value="${u.email}" required>
                    </div>
                    <div class="form-group">
                        <label>2FA Secret / PIN</label>
                        <input type="text" id="m-secret" class="form-control" value="${u.dos_factores_secret || '123456'}" required>
                    </div>
                    <div class="form-group">
                        <label>Estado</label>
                        <select id="m-estado" class="form-control">
                            <option value="A" ${u.estado === 'A' ? 'selected' : ''}>Activo</option>
                            <option value="I" ${u.estado === 'I' ? 'selected' : ''}>Inactivo</option>
                        </select>
                    </div>
                    <button type="submit" class="btn">Guardar Usuario</button>
                </form>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

async function saveUsuario(e, id) {
    e.preventDefault();
    const payload = {
        id: id,
        username: document.getElementById('m-username').value,
        password: document.getElementById('m-password').value,
        nombre_completo: document.getElementById('m-nombre').value,
        email: document.getElementById('m-email').value,
        dos_factores_secret: document.getElementById('m-secret').value,
        estado: document.getElementById('m-estado').value
    };
    const accion = id > 0 ? 'EDITAR' : 'INSERTAR';
    await callSP('sp_usuarios', accion, payload);
    closeModal('usr-modal');
    renderUsuariosPage(document.getElementById('main-content-body'));
}

async function deleteUsuario(id) {
    if (confirm('¿Desea eliminar este usuario?')) {
        await callSP('sp_usuarios', 'ELIMINAR', { id });
        renderUsuariosPage(document.getElementById('main-content-body'));
    }
}

// 2. Mantenimiento de Empresas
async function renderEmpresasPage(container) {
    const res = await callSP('sp_empresas', 'CONSULTAR');
    const list = res.data || [];

    let rows = list.map(e => `
        <tr>
            <td>${e.id}</td>
            <td><strong>${e.ruc}</strong></td>
            <td>${e.razon_social}</td>
            <td>${e.direccion}</td>
            <td>${e.telefono}</td>
            <td><span class="badge ${e.estado === 'A' ? 'badge-success' : 'badge-danger'}">${e.estado === 'A' ? 'Activa' : 'Inactiva'}</span></td>
            <td>
                <button class="btn-action btn-edit" onclick="openEmpresaModal(${e.id})"><i class="fas fa-edit"></i> Editar</button>
                <button class="btn-action btn-delete" onclick="deleteEmpresa(${e.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('');

    container.innerHTML = `
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-building"></i> Mantenimiento de Empresas</span>
                <button class="btn btn-sm" onclick="openEmpresaModal(0)" style="width:auto;"><i class="fas fa-plus"></i> Nueva Empresa</button>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr><th>ID</th><th>RUC</th><th>Razón Social</th><th>Dirección</th><th>Teléfono</th><th>Estado</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        </div>
    `;
}

async function openEmpresaModal(id) {
    let e = { id: 0, ruc: '', razon_social: '', direccion: '', telefono: '', estado: 'A' };
    if (id > 0) {
        const res = await callSP('sp_empresas', 'CONSULTAR', { id });
        if (res.data && res.data.length > 0) e = res.data[0];
    }

    const modalHtml = `
        <div class="modal-overlay" id="emp-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="modal-title">${id > 0 ? 'Editar' : 'Nueva'} Empresa</span>
                    <button class="modal-close" onclick="closeModal('emp-modal')">&times;</button>
                </div>
                <form onsubmit="saveEmpresa(event, ${id})">
                    <div class="form-group">
                        <label>RUC</label>
                        <input type="text" id="m-ruc" class="form-control" value="${e.ruc}" required>
                    </div>
                    <div class="form-group">
                        <label>Razón Social</label>
                        <input type="text" id="m-razon" class="form-control" value="${e.razon_social}" required>
                    </div>
                    <div class="form-group">
                        <label>Dirección</label>
                        <input type="text" id="m-direccion" class="form-control" value="${e.direccion}">
                    </div>
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" id="m-telefono" class="form-control" value="${e.telefono}">
                    </div>
                    <div class="form-group">
                        <label>Estado</label>
                        <select id="m-estado" class="form-control">
                            <option value="A" ${e.estado === 'A' ? 'selected' : ''}>Activa</option>
                            <option value="I" ${e.estado === 'I' ? 'selected' : ''}>Inactiva</option>
                        </select>
                    </div>
                    <button type="submit" class="btn">Guardar Empresa</button>
                </form>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

async function saveEmpresa(e, id) {
    e.preventDefault();
    const payload = {
        id: id,
        ruc: document.getElementById('m-ruc').value,
        razon_social: document.getElementById('m-razon').value,
        direccion: document.getElementById('m-direccion').value,
        telefono: document.getElementById('m-telefono').value,
        estado: document.getElementById('m-estado').value
    };
    await callSP('sp_empresas', id > 0 ? 'EDITAR' : 'INSERTAR', payload);
    closeModal('emp-modal');
    renderEmpresasPage(document.getElementById('main-content-body'));
}

async function deleteEmpresa(id) {
    if (confirm('¿Desea eliminar esta empresa?')) {
        await callSP('sp_empresas', 'ELIMINAR', { id });
        renderEmpresasPage(document.getElementById('main-content-body'));
    }
}

// 3. Empresa Años y Meses
async function renderEmpresaPeriodosPage(container) {
    const res = await callSP('sp_empresa_periodos', 'CONSULTAR');
    const list = res.data || [];

    let rows = list.map(p => `
        <tr>
            <td>${p.id}</td>
            <td>${p.empresa_nombre}</td>
            <td><strong>${p.anio}</strong></td>
            <td>Mes ${p.mes}</td>
            <td><span class="badge ${p.estado === 'A' ? 'badge-success' : 'badge-secondary'}">${p.estado === 'A' ? 'Abierto' : 'Cerrado'}</span></td>
            <td>
                <button class="btn-action btn-delete" onclick="deletePeriodo(${p.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('');

    container.innerHTML = `
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-calendar-alt"></i> Mantenimiento de Empresa - Años y Meses</span>
                <button class="btn btn-sm" onclick="openPeriodoModal()" style="width:auto;"><i class="fas fa-plus"></i> Registrar Año / Mes</button>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>ID</th><th>Empresa</th><th>Año</th><th>Mes</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        </div>
    `;
}

async function openPeriodoModal() {
    const resEmp = await callSP('sp_empresas', 'CONSULTAR');
    const empresas = resEmp.data || [];
    const empOptions = empresas.map(e => `<option value="${e.id}">${e.razon_social}</option>`).join('');

    const modalHtml = `
        <div class="modal-overlay" id="per-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="modal-title">Nuevo Período (Año y Mes)</span>
                    <button class="modal-close" onclick="closeModal('per-modal')">&times;</button>
                </div>
                <form onsubmit="savePeriodo(event)">
                    <div class="form-group">
                        <label>Empresa</label>
                        <select id="m-empresa-id" class="form-control" required>${empOptions}</select>
                    </div>
                    <div class="form-group">
                        <label>Año</label>
                        <input type="number" id="m-anio" class="form-control" value="2025" required>
                    </div>
                    <div class="form-group">
                        <label>Mes (1 - 12)</label>
                        <input type="number" id="m-mes" class="form-control" min="1" max="12" value="1" required>
                    </div>
                    <button type="submit" class="btn">Registrar Período</button>
                </form>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

async function savePeriodo(e) {
    e.preventDefault();
    const payload = {
        empresa_id: document.getElementById('m-empresa-id').value,
        anio: document.getElementById('m-anio').value,
        mes: document.getElementById('m-mes').value,
        estado: 'A'
    };
    await callSP('sp_empresa_periodos', 'INSERTAR', payload);
    closeModal('per-modal');
    renderEmpresaPeriodosPage(document.getElementById('main-content-body'));
}

async function deletePeriodo(id) {
    if (confirm('¿Eliminar período?')) {
        await callSP('sp_empresa_periodos', 'ELIMINAR', { id });
        renderEmpresaPeriodosPage(document.getElementById('main-content-body'));
    }
}

// 4. Mantenimiento de Módulos
async function renderModulosPage(container) {
    const res = await callSP('sp_modulos', 'CONSULTAR');
    const list = res.data || [];

    let rows = list.map(m => `
        <tr>
            <td>${m.id}</td>
            <td><strong>${m.codigo}</strong></td>
            <td><i class="${m.icono}"></i> ${m.nombre}</td>
            <td>${m.orden}</td>
            <td><span class="badge ${m.estado === 'A' ? 'badge-success' : 'badge-danger'}">${m.estado === 'A' ? 'Activo' : 'Inactivo'}</span></td>
            <td>
                <button class="btn-action btn-edit" onclick="openModuloModal(${m.id})"><i class="fas fa-edit"></i> Editar</button>
            </td>
        </tr>
    `).join('');

    container.innerHTML = `
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-cubes"></i> Mantenimiento de Módulos</span>
                <button class="btn btn-sm" onclick="openModuloModal(0)" style="width:auto;"><i class="fas fa-plus"></i> Nuevo Módulo</button>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>ID</th><th>Código</th><th>Módulo</th><th>Orden</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        </div>
    `;
}

async function openModuloModal(id) {
    let m = { id: 0, codigo: '', nombre: '', icono: 'fas fa-folder', orden: 1, estado: 'A' };
    if (id > 0) {
        const res = await callSP('sp_modulos', 'CONSULTAR', { id });
        if (res.data && res.data.length > 0) m = res.data[0];
    }

    const modalHtml = `
        <div class="modal-overlay" id="mod-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="modal-title">${id > 0 ? 'Editar' : 'Nuevo'} Módulo</span>
                    <button class="modal-close" onclick="closeModal('mod-modal')">&times;</button>
                </div>
                <form onsubmit="saveModulo(event, ${id})">
                    <div class="form-group"><label>Código</label><input type="text" id="m-mod-codigo" class="form-control" value="${m.codigo}" required></div>
                    <div class="form-group"><label>Nombre del Módulo</label><input type="text" id="m-mod-nombre" class="form-control" value="${m.nombre}" required></div>
                    <div class="form-group"><label>Icono (FontAwesome Class)</label><input type="text" id="m-mod-icono" class="form-control" value="${m.icono}"></div>
                    <div class="form-group"><label>Orden</label><input type="number" id="m-mod-orden" class="form-control" value="${m.orden}"></div>
                    <button type="submit" class="btn">Guardar Módulo</button>
                </form>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

async function saveModulo(e, id) {
    e.preventDefault();
    const payload = {
        id,
        codigo: document.getElementById('m-mod-codigo').value,
        nombre: document.getElementById('m-mod-nombre').value,
        icono: document.getElementById('m-mod-icono').value,
        orden: document.getElementById('m-mod-orden').value,
        estado: 'A'
    };
    await callSP('sp_modulos', id > 0 ? 'EDITAR' : 'INSERTAR', payload);
    closeModal('mod-modal');
    renderModulosPage(document.getElementById('main-content-body'));
    buildDynamicMenuTree();
}

// 5. Mantenimiento de Menús por Módulo
async function renderMenusPage(container) {
    const res = await callSP('sp_menus', 'CONSULTAR');
    const list = res.data || [];

    let rows = list.map(m => `
        <tr>
            <td>${m.id}</td>
            <td><span class="badge badge-secondary">${m.modulo_nombre}</span></td>
            <td><i class="${m.icono}"></i> <strong>${m.nombre}</strong></td>
            <td><code>${m.pagina_url}</code></td>
            <td>${m.orden}</td>
            <td>
                <button class="btn-action btn-edit" onclick="openMenuModal(${m.id})"><i class="fas fa-edit"></i> Editar</button>
                <button class="btn-action btn-delete" onclick="deleteMenu(${m.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('');

    container.innerHTML = `
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-list-ul"></i> Mantenimiento de Menús por Módulo</span>
                <button class="btn btn-sm" onclick="openMenuModal(0)" style="width:auto;"><i class="fas fa-plus"></i> Nuevo Menú</button>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>ID</th><th>Módulo</th><th>Menú</th><th>Página a Abrir</th><th>Orden</th><th>Acciones</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        </div>
    `;
}

async function openMenuModal(id) {
    let menu = { id: 0, modulo_id: 1, nombre: '', pagina_url: '', icono: 'fas fa-file-alt', orden: 1, estado: 'A' };
    if (id > 0) {
        const res = await callSP('sp_menus', 'CONSULTAR', { id });
        if (res.data && res.data.length > 0) menu = res.data[0];
    }

    const resMod = await callSP('sp_modulos', 'CONSULTAR');
    const modOptions = (resMod.data || []).map(m => `<option value="${m.id}" ${m.id == menu.modulo_id ? 'selected' : ''}>${m.nombre}</option>`).join('');

    const modalHtml = `
        <div class="modal-overlay" id="menu-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="modal-title">${id > 0 ? 'Editar' : 'Nuevo'} Menú</span>
                    <button class="modal-close" onclick="closeModal('menu-modal')">&times;</button>
                </div>
                <form onsubmit="saveMenu(event, ${id})">
                    <div class="form-group"><label>Módulo Padre</label><select id="m-menu-mod" class="form-control">${modOptions}</select></div>
                    <div class="form-group"><label>Nombre del Menú</label><input type="text" id="m-menu-nombre" class="form-control" value="${menu.nombre}" required></div>
                    <div class="form-group"><label>Página Target (URL / File)</label><input type="text" id="m-menu-url" class="form-control" value="${menu.pagina_url}" required></div>
                    <div class="form-group"><label>Icono</label><input type="text" id="m-menu-icono" class="form-control" value="${menu.icono}"></div>
                    <div class="form-group"><label>Orden</label><input type="number" id="m-menu-orden" class="form-control" value="${menu.orden}"></div>
                    <button type="submit" class="btn">Guardar Menú</button>
                </form>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

async function saveMenu(e, id) {
    e.preventDefault();
    const payload = {
        id,
        modulo_id: document.getElementById('m-menu-mod').value,
        nombre: document.getElementById('m-menu-nombre').value,
        pagina_url: document.getElementById('m-menu-url').value,
        icono: document.getElementById('m-menu-icono').value,
        orden: document.getElementById('m-menu-orden').value,
        estado: 'A'
    };
    await callSP('sp_menus', id > 0 ? 'EDITAR' : 'INSERTAR', payload);
    closeModal('menu-modal');
    renderMenusPage(document.getElementById('main-content-body'));
    buildDynamicMenuTree();
}

async function deleteMenu(id) {
    if (confirm('¿Eliminar menú?')) {
        await callSP('sp_menus', 'ELIMINAR', { id });
        renderMenusPage(document.getElementById('main-content-body'));
        buildDynamicMenuTree();
    }
}

// 6. Mantenimiento de Usuarios, Permisos de Módulos / Menús y Usuario-Empresa
async function renderUsuarioPermisosPage(container) {
    const resUsers = await callSP('sp_usuarios', 'CONSULTAR');
    const users = resUsers.data || [];

    const userOptions = users.map(u => `<option value="${u.id}">${u.username} - ${u.nombre_completo}</option>`).join('');

    container.innerHTML = `
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-user-shield"></i> Mantenimiento de Permisos de Usuarios por Módulo y Menú</span>
            </div>
            <div class="form-group" style="max-width: 400px; margin-bottom: 25px;">
                <label>Seleccione Usuario para Gestionar Acceso:</label>
                <select id="sel-perm-user" class="form-control" onchange="loadUserPermissionsMatrix()">
                    ${userOptions}
                </select>
            </div>
            <div id="perm-matrix-container">Cargando permisos...</div>
        </div>
    `;

    if (users.length > 0) {
        loadUserPermissionsMatrix();
    }
}

async function loadUserPermissionsMatrix() {
    const userId = document.getElementById('sel-perm-user').value;
    const container = document.getElementById('perm-matrix-container');

    const resMenus = await callSP('sp_menus', 'CONSULTAR');
    const allMenus = resMenus.data || [];

    const resPerms = await callSP('sp_usuario_permisos', 'CONSULTAR_PERMISOS_USUARIO', { usuario_id: userId });
    const userPerms = resPerms.data || [];
    const activeMenuIds = new Set(userPerms.filter(p => p.acceso == 1).map(p => p.menu_id));

    let html = `
        <form onsubmit="saveUserPermissions(event, ${userId})">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr><th>Acceso</th><th>Módulo</th><th>Opción de Menú</th><th>Página Asociada</th></tr>
                    </thead>
                    <tbody>
    `;

    allMenus.forEach(m => {
        const isChecked = activeMenuIds.has(m.id) ? 'checked' : '';
        html += `
            <tr>
                <td>
                    <input type="checkbox" class="chk-perm" data-mod="${m.modulo_id}" data-menu="${m.id}" ${isChecked}>
                </td>
                <td><span class="badge badge-secondary">${m.modulo_nombre}</span></td>
                <td><i class="${m.icono}"></i> ${m.nombre}</td>
                <td><code>${m.pagina_url}</code></td>
            </tr>
        `;
    });

    html += `
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn" style="max-width: 250px; margin-top: 15px;"><i class="fas fa-save"></i> Guardar Permisos de Usuario</button>
        </form>
    `;

    container.innerHTML = html;
}

async function saveUserPermissions(e, userId) {
    e.preventDefault();
    // Revoke all first
    await callSP('sp_usuario_permisos', 'REVOCAR_TODOS_USUARIO', { usuario_id: userId });

    const checkboxes = document.querySelectorAll('.chk-perm:checked');
    for (let chk of checkboxes) {
        const moduloId = chk.getAttribute('data-mod');
        const menuId = chk.getAttribute('data-menu');
        await callSP('sp_usuario_permisos', 'ASIGNAR_PERMISO', {
            usuario_id: userId,
            modulo_id: moduloId,
            menu_id: menuId,
            acceso: 1
        });
    }

    alert('Permisos actualizados correctamente.');
    if (userId == state.user.id) {
        buildDynamicMenuTree();
    }
}

// 7. Personalización de Framework (Tema Rojo Granate / Azul Mandalorian)
async function renderConfiguracionPage(container) {
    const res = await callSP('sp_configuracion', 'CONSULTAR');
    const config = (res.data && res.data.length > 0) ? res.data[0] : { tema_color: state.theme, nombre_sistema: 'ADVISOR-ERP' };

    container.innerHTML = `
        <div class="card" style="max-width: 600px;">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-paint-brush"></i> Maestro de Personalización del Framework ADVISOR</span>
            </div>
            <form onsubmit="saveFrameworkConfig(event)">
                <div class="form-group">
                    <label>Nombre Comercial del Sistema</label>
                    <input type="text" id="cfg-nombre" class="form-control" value="${config.nombre_sistema}" required>
                </div>
                <div class="form-group">
                    <label>Tema de Color Principal</label>
                    <select id="cfg-tema" class="form-control" onchange="previewThemeChange(this.value)">
                        <option value="rojo-granate" ${config.tema_color === 'rojo-granate' ? 'selected' : ''}>Rojo Granate (Estándar ADVISOR)</option>
                        <option value="azul-mandalorian" ${config.tema_color === 'azul-mandalorian' ? 'selected' : ''}>Azul Mandalorian</option>
                    </select>
                </div>
                <button type="submit" class="btn"><i class="fas fa-save"></i> Guardar Cambios de Tema</button>
            </form>
        </div>
    `;
}

function previewThemeChange(themeName) {
    applyTheme(themeName);
}

async function saveFrameworkConfig(e) {
    e.preventDefault();
    const tema_color = document.getElementById('cfg-tema').value;
    const nombre_sistema = document.getElementById('cfg-nombre').value;

    await callSP('sp_configuracion', 'ACTUALIZAR', { tema_color, nombre_sistema });
    applyTheme(tema_color);
    document.querySelectorAll('.lbl-system-title').forEach(el => el.textContent = nombre_sistema);
    alert('Personalización guardada con éxito.');
}

// 8. Mantenimiento de Clientes (Demonstrating custom actions like 'CONSULTAR CLIENTES DE BAJA')
async function renderClientesPage(container) {
    loadClientesList(container, 'CONSULTAR');
}

async function loadClientesList(container, accion = 'CONSULTAR') {
    const res = await callSP('sp_clientes', accion, { empresa_id: state.selectedEmpresa.id });
    const list = res.data || [];

    let rows = list.map(c => `
        <tr>
            <td>${c.id}</td>
            <td><strong>${c.codigo}</strong></td>
            <td>${c.ruc_dni}</td>
            <td>${c.razon_social}</td>
            <td>${c.direccion}</td>
            <td>${c.representante}</td>
            <td><span class="badge ${c.estado === 'A' ? 'badge-success' : 'badge-danger'}">${c.estado === 'A' ? 'Activo' : 'Baja'}</span></td>
            <td>
                <button class="btn-action btn-edit" onclick="openClienteModal(${c.id})"><i class="fas fa-edit"></i> Editar</button>
                <button class="btn-action btn-delete" onclick="deleteCliente(${c.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('');

    container.innerHTML = `
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-user-tie"></i> Mantenimiento de Clientes - Empresa: ${state.selectedEmpresa.razon_social}</span>
                <div>
                    <button class="btn btn-sm btn-secondary" onclick="loadClientesList(document.getElementById('main-content-body'), '${accion === 'CONSULTAR' ? 'CONSULTAR_CLIENTES_DE_BAJA' : 'CONSULTAR'}')" style="width:auto; margin-right: 10px;">
                        <i class="fas fa-filter"></i> ${accion === 'CONSULTAR' ? 'Ver Clientes de Baja' : 'Ver Clientes Activos'}
                    </button>
                    <button class="btn btn-sm" onclick="openClienteModal(0)" style="width:auto;"><i class="fas fa-plus"></i> Nuevo Cliente</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>ID</th><th>Código</th><th>RUC/DNI</th><th>Razón Social</th><th>Dirección</th><th>Representante</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>${rows.length > 0 ? rows : '<tr><td colspan="8" style="text-align:center;">No hay registros encontrados.</td></tr>'}</tbody>
                </table>
            </div>
        </div>
    `;
}

async function openClienteModal(id) {
    let c = { id: 0, codigo: 'CLI' + Math.floor(100 + Math.random() * 900), ruc_dni: '', razon_social: '', direccion: '', representante: '', tipo_doc: 'RUC', estado: 'A' };
    if (id > 0) {
        const res = await callSP('sp_clientes', 'CONSULTAR', { id });
        if (res.data && res.data.length > 0) c = res.data[0];
    }

    const modalHtml = `
        <div class="modal-overlay" id="cli-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="modal-title">${id > 0 ? 'Editar' : 'Nuevo'} Cliente</span>
                    <button class="modal-close" onclick="closeModal('cli-modal')">&times;</button>
                </div>
                <form onsubmit="saveCliente(event, ${id})">
                    <div class="form-group"><label>Código</label><input type="text" id="m-cli-codigo" class="form-control" value="${c.codigo}" required></div>
                    <div class="form-group"><label>RUC / DNI</label><input type="text" id="m-cli-ruc" class="form-control" value="${c.ruc_dni}" required></div>
                    <div class="form-group"><label>Razón Social / Nombre</label><input type="text" id="m-cli-razon" class="form-control" value="${c.razon_social}" required></div>
                    <div class="form-group"><label>Dirección</label><input type="text" id="m-cli-dir" class="form-control" value="${c.direccion}"></div>
                    <div class="form-group"><label>Representante Legal</label><input type="text" id="m-cli-rep" class="form-control" value="${c.representante}"></div>
                    <div class="form-group">
                        <label>Estado</label>
                        <select id="m-cli-estado" class="form-control">
                            <option value="A" ${c.estado === 'A' ? 'selected' : ''}>Activo</option>
                            <option value="B" ${c.estado === 'B' ? 'selected' : ''}>De Baja</option>
                        </select>
                    </div>
                    <button type="submit" class="btn">Guardar Cliente</button>
                </form>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

async function saveCliente(e, id) {
    e.preventDefault();
    const payload = {
        id,
        empresa_id: state.selectedEmpresa.id,
        codigo: document.getElementById('m-cli-codigo').value,
        ruc_dni: document.getElementById('m-cli-ruc').value,
        razon_social: document.getElementById('m-cli-razon').value,
        direccion: document.getElementById('m-cli-dir').value,
        representante: document.getElementById('m-cli-rep').value,
        tipo_doc: 'RUC',
        estado: document.getElementById('m-cli-estado').value
    };
    await callSP('sp_clientes', id > 0 ? 'EDITAR' : 'INSERTAR', payload);
    closeModal('cli-modal');
    renderClientesPage(document.getElementById('main-content-body'));
}

async function deleteCliente(id) {
    if (confirm('¿Eliminar cliente?')) {
        await callSP('sp_clientes', 'ELIMINAR', { id });
        renderClientesPage(document.getElementById('main-content-body'));
    }
}

function closeModal(modalId) {
    const el = document.getElementById(modalId);
    if (el) el.remove();
}
