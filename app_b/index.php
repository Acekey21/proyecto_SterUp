<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>App B - SSO Demo</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
        }
        .sso-section {
            margin: 20px 0;
            padding: 20px;
            border: 2px dashed #3498db;
            border-radius: 5px;
            background-color: #ecf0f1;
        }
        .token-input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: monospace;
        }
        .btn {
            background-color: #3498db;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 5px;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        .btn-success {
            background-color: #27ae60;
        }
        .btn-success:hover {
            background-color: #229954;
        }
        .user-info {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .logs {
            margin-top: 30px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .log-entry {
            font-family: monospace;
            font-size: 12px;
            margin: 5px 0;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 App B - Demostración SSO</h1>
            <p>Aplicación secundaria que demuestra Single Sign-On desde StepUp</p>
        </div>

        <div id="login-section">
            <h2>🔐 Autenticación SSO</h2>
            <div class="sso-section">
                <p><strong>Instrucciones:</strong></p>
                <ol>
                    <li>Inicia sesión en <a href="../nova/login.php" target="_blank">StepUp</a></li>
                    <li>Ve a la sección "SSO Apps" en el panel de usuario</li>
                    <li>Genera un token SSO para "App B"</li>
                    <li>Pega el token abajo y haz clic en "Autenticar via SSO"</li>
                </ol>

                <textarea id="sso-token" class="token-input" rows="3" placeholder="Pega aquí el token SSO de StepUp..."></textarea>
                <br>
                <button class="btn" onclick="authenticateSSO()">🔑 Autenticar via SSO</button>
                <button class="btn btn-success" onclick="generateTokenFromStepUp()">📱 Ir a StepUp</button>
            </div>
        </div>

        <div id="user-section" style="display: none;">
            <h2>✅ Sesión Activa en App B</h2>
            <div id="user-info" class="user-info">
                <!-- User info will be populated here -->
            </div>
            <button class="btn" onclick="logout()">🚪 Cerrar Sesión</button>
        </div>

        <div id="error-section" style="display: none;">
            <div id="error-message" class="error">
                <!-- Error messages will be shown here -->
            </div>
        </div>

        <div class="logs">
            <h3>📋 Logs de SSO (últimas 10 entradas)</h3>
            <div id="sso-logs">
                <!-- Logs will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        let currentSession = null;

        async function authenticateSSO() {
            const token = document.getElementById('sso-token').value.trim();

            if (!token) {
                showError('Por favor ingresa un token SSO válido');
                return;
            }

            try {
                const response = await fetch('api/sso_login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        sso_token: token
                    })
                });

                const data = await response.json();

                if (data.success) {
                    currentSession = data.session;
                    showUserInfo(data.user, data.session);
                    loadLogs();
                } else {
                    showError(data.error || 'Error en autenticación SSO');
                }
            } catch (error) {
                showError('Error de conexión: ' + error.message);
            }
        }

        function generateTokenFromStepUp() {
            window.open('../nova/panel.php?sso=app_b', '_blank');
        }

        function showUserInfo(user, session) {
            document.getElementById('login-section').style.display = 'none';
            document.getElementById('user-section').style.display = 'block';
            document.getElementById('error-section').style.display = 'none';

            const userInfo = document.getElementById('user-info');
            userInfo.innerHTML = `
                <h3>👋 Bienvenido, ${user.nombre}!</h3>
                <p><strong>Email:</strong> ${user.email}</p>
                <p><strong>ID de Usuario:</strong> ${user.id}</p>
                <p><strong>Rol:</strong> ${user.rol}</p>
                <p><strong>Token de Sesión:</strong> <code>${session.token}</code></p>
                <p><strong>Autenticado via:</strong> ${session.created_via}</p>
            `;
        }

        function showError(message) {
            document.getElementById('error-section').style.display = 'block';
            document.getElementById('error-message').innerHTML = `<strong>Error:</strong> ${message}`;
        }

        function logout() {
            currentSession = null;
            document.getElementById('login-section').style.display = 'block';
            document.getElementById('user-section').style.display = 'none';
            document.getElementById('error-section').style.display = 'none';
            document.getElementById('sso-token').value = '';
        }

        async function loadLogs() {
            try {
                const response = await fetch('api/get_logs.php');
                const data = await response.json();

                const logsContainer = document.getElementById('sso-logs');
                logsContainer.innerHTML = '';

                if (data.logs && data.logs.length > 0) {
                    data.logs.forEach(log => {
                        const logEntry = document.createElement('div');
                        logEntry.className = 'log-entry';
                        logEntry.textContent = log;
                        logsContainer.appendChild(logEntry);
                    });
                } else {
                    logsContainer.innerHTML = '<p>No hay logs disponibles</p>';
                }
            } catch (error) {
                console.error('Error cargando logs:', error);
            }
        }

        // Cargar logs al iniciar
        loadLogs();
    </script>
</body>
</html>