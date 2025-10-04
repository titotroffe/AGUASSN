<?php
// logout.php - Cerrar sesión y turno
session_start();
require_once 'config.php';

// Verificar si hay un turno activo y cerrarlo
if (isset($_SESSION['user_id'])) {
    try {
        $pdo = conectarDB();

        // Obtener el turno activo del usuario actual
        $stmt = $pdo->prepare("
            SELECT id 
            FROM turnos 
            WHERE usuario_id = ? AND estado = 'activo' 
            ORDER BY fecha_inicio DESC 
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $turno = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si hay un turno activo, cerrarlo
        if ($turno) {
            $stmt = $pdo->prepare("
                UPDATE turnos 
                SET fecha_cierre = NOW(), estado = 'cerrado' 
                WHERE id = ?
            ");
            $stmt->execute([$turno['id']]);
        }
    } catch (PDOException $e) {
        // Si hay error, continuar con el logout de todas formas
        error_log("Error al cerrar turno en logout: " . $e->getMessage());
    }
}

// Destruir todas las variables de sesión
session_unset();
session_destroy();

// Redirigir al login
header("Location: login.php");
exit();
