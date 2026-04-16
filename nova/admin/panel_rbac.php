<?php
/**
 * PANEL DE ADMINISTRACIÓN CON RBAC
 * Dashboard completo para gestión del sistema
 */

require_once '../auth/middleware.php';
require_once '../includes/conexion.php';

// Verificar autenticación JWT
$payload = auth_protect();

// Verificar permisos de admin
auth_has_permission($payload, 'usuarios.ver');

// Obtener información del usuario actual
$userId = $payload['sub'];
$userQuery = $conn->prepare("SELECT nombre, correo FROM usuarios WHERE id = ?");
$userQuery->bind_param('i', $userId);
$userQuery->execute();
$currentUser = $userQuery->get_result()->fetch_assoc();

// Estadísticas del sistema
$stats = [];

// Total de usuarios
$userCount = $conn->query("SELECT COUNT(*) as total FROM usuarios")->fetch_assoc()['total'];
$stats['usuarios'] = $userCount;

// Usuarios por rol
$roleStats = $conn->query("
    SELECT r.nombre, COUNT(u.id) as cantidad
    FROM roles r
    LEFT JOIN usuarios u ON r.id = u.rol_id
    GROUP BY r.id, r.nombre
    ORDER BY r.nivel DESC
");
$stats['roles'] = [];
while ($row = $roleStats->fetch_assoc()) {
    $stats['roles'][] = $row;
}

// Sesiones activas
$activeSessions = $conn->query("SELECT COUNT(*) as total FROM user_sessions WHERE revoked = 0 AND expires_at > NOW()")->fetch_assoc()['total'];
$stats['sesiones_activas'] = $activeSessions;

// Intentos de recuperación recientes (últimas 24h)
$recoveryAttempts = $conn->query("SELECT COUNT(*) as total FROM password_reset_attempts WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)")->fetch_assoc()['total'];
$stats['intentos_recuperacion'] = $recoveryAttempts;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - StepUp</title>
    <link rel="stylesheet" href="../Estilos/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <header class="admin-header">
            <div class="header-content">
                <h1>Panel de Administración</h1>
                <div class="user-info">
                    <span>Bienvenido, <?php echo htmlspecialchars($currentUser['nombre']); ?></span>
                    <a href="../api/logout.php" class="btn-logout">Cerrar Sesión</a>
                </div>
            </div>
        </header>

        <!-- Navigation -->
        <nav class="admin-nav">
            <ul>
                <li><a href="#dashboard" class="active">Dashboard</a></li>
                <li><a href="#usuarios">Usuarios</a></li>
                <li><a href="#roles">Roles & Permisos</a></li>
                <li><a href="#sesiones">Sesiones</a></li>
                <li><a href="#seguridad">Seguridad</a></li>
                <li><a href="#productos">Productos</a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Dashboard Section -->
            <section id="dashboard" class="admin-section active">
                <h2>Dashboard General</h2>

                <div class="stats-grid">
                    <div class="stat-card">
                        <h3><?php echo $stats['usuarios']; ?></h3>
                        <p>Total Usuarios</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $stats['sesiones_activas']; ?></h3>
                        <p>Sesiones Activas</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $stats['intentos_recuperacion']; ?></h3>
                        <p>Intentos Recuperación (24h)</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo count($stats['roles']); ?></h3>
                        <p>Roles Activos</p>
                    </div>
                </div>

                <div class="chart-container">
                    <h3>Distribución de Usuarios por Rol</h3>
                    <canvas id="rolesChart"></canvas>
                </div>
            </section>

            <!-- Usuarios Section -->
            <section id="usuarios" class="admin-section">
                <h2>Gestión de Usuarios</h2>
                <div class="section-actions">
                    <button onclick="loadUsers()" class="btn-primary">Cargar Usuarios</button>
                </div>
                <div id="usersTable" class="data-table">
                    <!-- Tabla se carga dinámicamente -->
                </div>
            </section>

            <!-- Roles Section -->
            <section id="roles" class="admin-section">
                <h2>Gestión de Roles y Permisos</h2>
                <div class="section-actions">
                    <button onclick="loadRoles()" class="btn-primary">Cargar Roles</button>
                    <button onclick="loadPermisos()" class="btn-secondary">Ver Permisos</button>
                </div>
                <div id="rolesContent">
                    <!-- Contenido se carga dinámicamente -->
                </div>
            </section>

            <!-- Sesiones Section -->
            <section id="sesiones" class="admin-section">
                <h2>Gestión de Sesiones</h2>
                <div class="section-actions">
                    <button onclick="loadAllSessions()" class="btn-primary">Ver Todas las Sesiones</button>
                </div>
                <div id="sessionsTable" class="data-table">
                    <!-- Tabla se carga dinámicamente -->
                </div>
            </section>

            <!-- Seguridad Section -->
            <section id="seguridad" class="admin-section">
                <h2>Informes de Seguridad</h2>
                <div class="section-actions">
                    <button onclick="loadSecurityStats()" class="btn-primary">Cargar Estadísticas</button>
                </div>
                <div id="securityStats">
                    <!-- Estadísticas se cargan dinámicamente -->
                </div>
            </section>

            <!-- Productos Section -->
            <section id="productos" class="admin-section">
                <h2>Gestión de Productos</h2>
                <p>Funcionalidad de productos próximamente...</p>
            </section>
        </main>
    </div>

    <!-- Modal para editar usuarios -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Editar Usuario</h3>
            <form id="userForm">
                <input type="hidden" id="editUserId">
                <div class="form-group">
                    <label>Nombre:</label>
                    <input type="text" id="editUserName" required>
                </div>
                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" id="editUserEmail" required>
                </div>
                <div class="form-group">
                    <label>Rol:</label>
                    <select id="editUserRole" required>
                        <!-- Opciones se cargan dinámicamente -->
                    </select>
                </div>
                <button type="submit" class="btn-primary">Guardar Cambios</button>
            </form>
        </div>
    </div>

    <script>
        // Variables globales
        let currentSection = 'dashboard';
        const token = localStorage.getItem('access_token');

        // Configuración de headers para API
        const headers = {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        };

        // Navegación
        document.querySelectorAll('.admin-nav a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const target = this.getAttribute('href').substring(1);

                // Cambiar sección activa
                document.querySelectorAll('.admin-section').forEach(section => {
                    section.classList.remove('active');
                });
                document.getElementById(target).classList.add('active');

                // Cambiar enlace activo
                document.querySelectorAll('.admin-nav a').forEach(navLink => {
                    navLink.classList.remove('active');
                });
                this.classList.add('active');

                currentSection = target;
            });
        });

        // Gráfico de roles
        const ctx = document.getElementById('rolesChart').getContext('2d');
        const rolesData = <?php echo json_encode($stats['roles']); ?>;
        const chart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: rolesData.map(item => item.nombre),
                datasets: [{
                    data: rolesData.map(item => item.cantidad),
                    backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });

        // Funciones para cargar datos
        async function loadUsers() {
            try {
                const response = await fetch('../api/usuarios.php?action=list', { headers });
                const data = await response.json();

                if (data.usuarios) {
                    displayUsersTable(data.usuarios);
                }
            } catch (error) {
                console.error('Error cargando usuarios:', error);
            }
        }

        function displayUsersTable(users) {
            const tableHtml = `
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Fecha Registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${users.map(user => `
                            <tr>
                                <td>${user.id}</td>
                                <td>${user.nombre}</td>
                                <td>${user.correo}</td>
                                <td>${user.rol || 'Sin asignar'}</td>
                                <td>${new Date(user.created_at).toLocaleDateString()}</td>
                                <td>
                                    <button onclick="editUser(${user.id})" class="btn-edit">Editar</button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
            document.getElementById('usersTable').innerHTML = tableHtml;
        }

        async function loadRoles() {
            try {
                const response = await fetch('../api/roles.php?action=roles', { headers });
                const data = await response.json();

                if (data.roles) {
                    displayRoles(data.roles);
                }
            } catch (error) {
                console.error('Error cargando roles:', error);
            }
        }

        function displayRoles(roles) {
            const rolesHtml = `
                <div class="roles-list">
                    ${roles.map(rol => `
                        <div class="rol-card">
                            <h4>${rol.nombre}</h4>
                            <p>${rol.descripcion}</p>
                            <p>Nivel: ${rol.nivel}</p>
                            <button onclick="viewRolePermisos(${rol.id})" class="btn-secondary">Ver Permisos</button>
                        </div>
                    `).join('')}
                </div>
            `;
            document.getElementById('rolesContent').innerHTML = rolesHtml;
        }

        async function loadAllSessions() {
            try {
                const response = await fetch('../api/sessions.php?action=all', { headers });
                const data = await response.json();

                if (data.sessions) {
                    displaySessionsTable(data.sessions);
                }
            } catch (error) {
                console.error('Error cargando sesiones:', error);
            }
        }

        function displaySessionsTable(sessions) {
            const tableHtml = `
                <table>
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>IP</th>
                            <th>User Agent</th>
                            <th>Creada</th>
                            <th>Expira</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${sessions.map(session => `
                            <tr>
                                <td>${session.nombre || session.correo || 'N/A'}</td>
                                <td>${session.ip}</td>
                                <td>${session.user_agent ? session.user_agent.substring(0, 50) + '...' : 'N/A'}</td>
                                <td>${new Date(session.created_at).toLocaleString()}</td>
                                <td>${new Date(session.expires_at).toLocaleString()}</td>
                                <td>${session.revoked ? 'Cerrada' : 'Activa'}</td>
                                <td>
                                    ${!session.revoked ? `<button onclick="revokeSession(${session.id})" class="btn-danger">Cerrar</button>` : ''}
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
            document.getElementById('sessionsTable').innerHTML = tableHtml;
        }

        async function revokeSession(sessionId) {
            if (!confirm('¿Estás seguro de cerrar esta sesión?')) return;

            try {
                const response = await fetch('../api/sessions_revoke.php', {
                    method: 'POST',
                    headers,
                    body: JSON.stringify({ session_id: sessionId })
                });
                const data = await response.json();

                if (response.ok) {
                    alert('Sesión cerrada exitosamente');
                    loadAllSessions();
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (error) {
                console.error('Error cerrando sesión:', error);
            }
        }

        // Funciones adicionales (placeholders)
        function loadPermisos() { alert('Funcionalidad próximamente'); }
        function loadSecurityStats() { alert('Funcionalidad próximamente'); }
        function editUser(userId) { alert('Funcionalidad próximamente'); }
        function viewRolePermisos(roleId) { alert('Funcionalidad próximamente'); }
    </script>
</body>
</html>