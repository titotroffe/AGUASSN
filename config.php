<?php
// ============================
// Configuración de la base de datos
// ============================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'aguas_san_nicolas');

session_start();

// ============================
// Función para conectar a la base de datos
// ============================
function conectarDB()
{
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
}

// ============================
// Función para obtener o crear turno activo
// ============================
function obtenerTurnoActivo()
{
    $pdo = conectarDB();
    $stmt = $pdo->query("SELECT id FROM turnos WHERE estado = 'activo' ORDER BY fecha_inicio DESC LIMIT 1");
    $turno = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$turno) {
        // Crear nuevo turno si no existe uno activo
        $usuarioActivo = $_SESSION['user_id'] ?? 1; // Fallback a usuario 1
        $stmt = $pdo->prepare("INSERT INTO turnos (fecha_inicio, usuario_id, estado) VALUES (NOW(), ?, 'activo')");
        $stmt->execute([$usuarioActivo]);
        $nuevoTurnoId = $pdo->lastInsertId();

        $_SESSION['turno_actual'] = $nuevoTurnoId;
        return $nuevoTurnoId;
    }

    $_SESSION['turno_actual'] = $turno['id'];
    return $turno['id'];
}

// ============================
// Función para cerrar turno y crear uno nuevo
// ============================
function cerrarTurnoYCrearNuevo($turnoActual, $usuarioId = null)
{
    $pdo = conectarDB();

    // Si no se pasa usuario, usar el de la sesión con fallback
    if ($usuarioId === null) {
        $usuarioId = $_SESSION['user_id'] ?? 1;
    }

    try {
        $pdo->beginTransaction();

        // Cerrar turno actual
        $stmt = $pdo->prepare("UPDATE turnos SET fecha_cierre = NOW(), estado = 'cerrado' WHERE id = ?");
        $stmt->execute([$turnoActual]);

        // Crear nuevo turno con el usuario correcto
        $stmt = $pdo->prepare("INSERT INTO turnos (fecha_inicio, usuario_id, estado) VALUES (NOW(), ?, 'activo')");
        $stmt->execute([$usuarioId]);
        $nuevoTurnoId = $pdo->lastInsertId();

        $_SESSION['turno_actual'] = $nuevoTurnoId;

        $pdo->commit();
        return $nuevoTurnoId;
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
}

// ============================
// Función para obtener usuarios
// ============================
function obtenerUsuarios()
{
    $pdo = conectarDB();
    $stmt = $pdo->query("SELECT * FROM usuarios ORDER BY nombre");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ============================
// Función para mostrar mensajes
// ============================
function mostrarMensaje($tipo, $mensaje)
{
    $clase = $tipo === 'success' ? 'alert-success' : 'alert-error';
    return "<div class='alert {$clase}'>{$mensaje}</div>";
}

// ============================
// FUNCIONES CORREGIDAS PARA REGISTROS Y USUARIOS
// ============================

// Función para obtener registros de presión con usuario del turno
function obtenerRegistrosPresion($turnoId)
{
    $pdo = conectarDB();
    $stmt = $pdo->prepare("
        SELECT rp.*, u.nombre as usuario_nombre 
        FROM registros_presion rp
        LEFT JOIN turnos t ON rp.turno_id = t.id
        LEFT JOIN usuarios u ON t.usuario_id = u.id
        WHERE rp.turno_id = ? 
        ORDER BY rp.fecha_registro DESC
    ");
    $stmt->execute([$turnoId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener novedades por módulo con usuario directo
function obtenerNovedades($turnoId, $modulo = 'industrial')
{
    $pdo = conectarDB();
    $stmt = $pdo->prepare("
        SELECT nt.*, u.nombre as usuario_nombre 
        FROM novedades_turno nt 
        LEFT JOIN usuarios u ON nt.usuario_id = u.id 
        WHERE nt.turno_id = ? AND nt.modulo = ? 
        ORDER BY nt.fecha_registro DESC
    ");
    $stmt->execute([$turnoId, $modulo]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener registros de calidad con usuario del turno
function obtenerRegistrosCalidad($turnoId)
{
    $pdo = conectarDB();
    $stmt = $pdo->prepare("
        SELECT ca.*, u.nombre as usuario_nombre 
        FROM calidad_agua ca 
        LEFT JOIN turnos t ON ca.turno_id = t.id
        LEFT JOIN usuarios u ON t.usuario_id = u.id
        WHERE ca.turno_id = ? 
        ORDER BY ca.fecha_registro DESC
    ");
    $stmt->execute([$turnoId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
