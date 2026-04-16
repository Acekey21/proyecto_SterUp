<?php
/**
 * API PARA GESTIÓN DE PREGUNTAS SECRETAS
 * Permite configurar y validar preguntas de seguridad cifradas
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../includes/conexion.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

// Verificar autenticación
$payload = auth_protect();

switch ($method) {
    case 'GET':
        switch ($action) {
            case 'list':
                // Listar preguntas del usuario (sin respuestas)
                $userId = $payload['sub'];
                $sql = "SELECT id, pregunta, created_at FROM security_questions WHERE usuario_id = ? ORDER BY created_at DESC";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $result = $stmt->get_result();

                $questions = [];
                while ($row = $result->fetch_assoc()) {
                    $questions[] = $row;
                }

                send_json_response(200, ['questions' => $questions]);
                break;

            case 'validate':
                // Validar respuesta a una pregunta específica
                $questionId = intval($_GET['question_id'] ?? 0);
                $answer = trim($_GET['answer'] ?? '');

                if (!$questionId || !$answer) {
                    send_json_response(400, ['error' => 'question_id y answer son requeridos']);
                }

                // Verificar que la pregunta pertenece al usuario
                $userId = $payload['sub'];
                $sql = "SELECT respuesta_encriptada FROM security_questions WHERE id = ? AND usuario_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ii', $questionId, $userId);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 0) {
                    send_json_response(404, ['error' => 'Pregunta no encontrada']);
                }

                $storedAnswer = $result->fetch_assoc()['respuesta_encriptada'];

                // Verificar respuesta (usando password_verify para comparación segura)
                if (password_verify(strtolower(trim($answer)), $storedAnswer)) {
                    send_json_response(200, ['valid' => true, 'message' => 'Respuesta correcta']);
                } else {
                    send_json_response(200, ['valid' => false, 'message' => 'Respuesta incorrecta']);
                }
                break;

            default:
                send_json_response(400, ['error' => 'Acción no válida']);
        }
        break;

    case 'POST':
        switch ($action) {
            case 'create':
                // Crear nueva pregunta secreta
                $data = json_decode(file_get_contents('php://input'), true);
                if (!$data) {
                    $data = $_POST;
                }

                $question = trim($data['question'] ?? '');
                $answer = trim($data['answer'] ?? '');

                if (!$question || !$answer) {
                    send_json_response(400, ['error' => 'Pregunta y respuesta son requeridas']);
                }

                if (strlen($question) < 10 || strlen($answer) < 3) {
                    send_json_response(400, ['error' => 'Pregunta debe tener al menos 10 caracteres, respuesta al menos 3']);
                }

                $userId = $payload['sub'];

                // Verificar límite de preguntas (máximo 3 por usuario)
                $countSql = "SELECT COUNT(*) as total FROM security_questions WHERE usuario_id = ?";
                $countStmt = $conn->prepare($countSql);
                $countStmt->bind_param('i', $userId);
                $countStmt->execute();
                $count = $countStmt->get_result()->fetch_assoc()['total'];

                if ($count >= 3) {
                    send_json_response(400, ['error' => 'Máximo 3 preguntas secretas por usuario']);
                }

                // Encriptar respuesta
                $encryptedAnswer = password_hash(strtolower($answer), PASSWORD_DEFAULT);

                // Insertar pregunta
                $insertSql = "INSERT INTO security_questions (usuario_id, pregunta, respuesta_encriptada) VALUES (?, ?, ?)";
                $insertStmt = $conn->prepare($insertSql);
                $insertStmt->bind_param('iss', $userId, $question, $encryptedAnswer);
                $insertStmt->execute();

                send_json_response(201, ['message' => 'Pregunta secreta creada exitosamente']);
                break;

            case 'reset':
                // Solicitar reset usando preguntas secretas
                $data = json_decode(file_get_contents('php://input'), true);
                if (!$data) {
                    $data = $_POST;
                }

                $correo = trim($data['correo'] ?? '');
                if (!$correo) {
                    send_json_response(400, ['error' => 'Correo electrónico es requerido']);
                }

                // Buscar usuario
                $userSql = "SELECT id FROM usuarios WHERE correo = ? AND activo = 1";
                $userStmt = $conn->prepare($userSql);
                $userStmt->bind_param('s', $correo);
                $userStmt->execute();
                $userResult = $userStmt->get_result();

                if ($userResult->num_rows === 0) {
                    send_json_response(404, ['error' => 'Usuario no encontrado']);
                }

                $userId = $userResult->fetch_assoc()['id'];

                // Verificar que tiene preguntas secretas
                $questionsSql = "SELECT COUNT(*) as total FROM security_questions WHERE usuario_id = ?";
                $questionsStmt = $conn->prepare($questionsSql);
                $questionsStmt->bind_param('i', $userId);
                $questionsStmt->execute();
                $questionsCount = $questionsStmt->get_result()->fetch_assoc()['total'];

                if ($questionsCount === 0) {
                    send_json_response(400, ['error' => 'Usuario no tiene preguntas secretas configuradas']);
                }

                // Generar token especial para reset con preguntas
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $tokenSql = "INSERT INTO password_reset_tokens (usuario_id, token, expires_at, tipo) VALUES (?, ?, ?, 'questions')";
                $tokenStmt = $conn->prepare($tokenSql);
                $tokenStmt->bind_param('iss', $userId, $token, $expires);
                $tokenStmt->execute();

                send_json_response(200, [
                    'message' => 'Token generado para reset con preguntas secretas',
                    'token' => $token,
                    'questions_count' => $questionsCount
                ]);
                break;

            default:
                send_json_response(400, ['error' => 'Acción no válida']);
        }
        break;

    case 'DELETE':
        // Eliminar pregunta secreta
        $questionId = intval($_GET['question_id'] ?? 0);
        if (!$questionId) {
            send_json_response(400, ['error' => 'question_id es requerido']);
        }

        $userId = $payload['sub'];
        $deleteSql = "DELETE FROM security_questions WHERE id = ? AND usuario_id = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->bind_param('ii', $questionId, $userId);
        $deleteStmt->execute();

        if ($deleteStmt->affected_rows > 0) {
            send_json_response(200, ['message' => 'Pregunta secreta eliminada']);
        } else {
            send_json_response(404, ['error' => 'Pregunta no encontrada']);
        }
        break;

    default:
        send_json_response(405, ['error' => 'Método no permitido']);
}