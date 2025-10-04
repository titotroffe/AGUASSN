<?php
require_once 'config.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar si el usuario tiene rol de jefatura
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'jefatura') {
    header('Location: ../index.php');
    exit;
}

$mensaje = '';
$tipo_mensaje = '';

// Procesar formulario de creación y eliminación
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion'])) {
    $pdo = conectarDB();
    $accion = $_POST['accion'];

    if ($accion == 'crear') {
        $nombre = trim($_POST['nombre']);
        $rol = trim($_POST['rol']);
        $usuario = trim($_POST['usuario']);
        $password = trim($_POST['password']);

        // Validar campos
        if (empty($nombre) || empty($rol) || empty($usuario) || empty($password)) {
            $mensaje = 'Todos los campos son obligatorios';
            $tipo_mensaje = 'error';
        } else {
            try {
                // Verificar si el usuario ya existe
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ?");
                $stmt->execute([$usuario]);

                if ($stmt->fetch()) {
                    $mensaje = 'El nombre de usuario ya existe';
                    $tipo_mensaje = 'error';
                } else {
                    // Insertar nuevo usuario
                    $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, rol, usuario, password) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$nombre, $rol, $usuario, $password]);

                    $mensaje = 'Usuario creado exitosamente';
                    $tipo_mensaje = 'success';
                    $_POST = array();
                }
            } catch (PDOException $e) {
                $mensaje = 'Error al crear el usuario: ' . $e->getMessage();
                $tipo_mensaje = 'error';
            }
        }
    }

    // Procesar eliminación de usuario
    elseif ($accion == 'eliminar' && isset($_POST['id'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $mensaje = 'Usuario eliminado exitosamente';
            $tipo_mensaje = 'success';
        } catch (PDOException $e) {
            $mensaje = 'Error al eliminar el usuario: ' . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    }
}

// Obtener lista de usuarios
$usuarios = obtenerUsuarios();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Aguas San Nicolás</title>
    <link rel="stylesheet" href="../styles/usuarios.css">
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Gestión de Usuarios</h1>
            <a href="../index.php" class="btn-volver">← VOLVER AL DASHBOARD</a>
        </div>

        <div class="grid">
            <!-- Formulario de creación -->
            <div class="card">
                <h2>Crear Nuevo Usuario</h2>

                <?php if ($mensaje && $tipo_mensaje): ?>
                    <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                        <?php echo htmlspecialchars($mensaje); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="accion" value="crear">

                    <div class="form-group">
                        <label for="nombre">Nombre Completo:</label>
                        <input type="text" id="nombre" name="nombre" required
                            value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="rol">Rol:</label>
                        <select id="rol" name="rol" required>
                            <option value="">Seleccione un rol</option>
                            <option value="jefatura">Jefatura</option>
                            <option value="operador">Operador</option>
                            <option value="quimico">Químico</option>
                            <option value="mantenimiento">Mantenimiento</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="usuario">Usuario:</label>
                        <input type="text" id="usuario" name="usuario" required
                            placeholder="Ej: jperez"
                            value="<?php echo isset($_POST['usuario']) ? htmlspecialchars($_POST['usuario']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="password">Contraseña:</label>
                        <input type="password" id="password" name="password" required
                            placeholder="Mínimo 6 caracteres" minlength="6">
                    </div>

                    <button type="submit" class="btn">✓ Crear Usuario</button>
                </form>
            </div>

            <!-- Tabla de usuarios -->
            <div class="card">
                <h2>Usuarios Registrados (<?php echo count($usuarios); ?>)</h2>

                <?php if (count($usuarios) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Rol</th>
                                <th>Usuario</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['nombre']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['rol']; ?>">
                                            <?php echo ucfirst($user['rol']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['usuario']); ?></td>
                                    <td>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" style="display: inline;"
                                                onsubmit="return confirm('¿Está seguro de eliminar este usuario?');">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn-eliminar">🗑️ Eliminar</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #6c757d; font-size: 14px;">Usuario actual</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">
                        No hay usuarios registrados en el sistema
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>