<?php
require_once 'config.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$mensaje = '';
$tipo_mensaje = '';

// Obtener información del usuario actual
$pdo = conectarDB();
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$usuario_actual = $stmt->fetch(PDO::FETCH_ASSOC);

// Procesar formulario de actualización
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    // Cambiar contraseña
    if ($accion == 'cambiar_password') {
        $password_actual = trim($_POST['password_actual']);
        $password_nueva = trim($_POST['password_nueva']);
        $password_confirmar = trim($_POST['password_confirmar']);

        // Validar campos
        if (empty($password_actual) || empty($password_nueva) || empty($password_confirmar)) {
            $mensaje = 'Todos los campos son obligatorios';
            $tipo_mensaje = 'error';
        } elseif ($password_nueva !== $password_confirmar) {
            $mensaje = 'Las contraseñas nuevas no coinciden';
            $tipo_mensaje = 'error';
        } elseif (strlen($password_nueva) < 6) {
            $mensaje = 'La contraseña debe tener al menos 6 caracteres';
            $tipo_mensaje = 'error';
        } else {
            // Verificar contraseña actual
            if ($usuario_actual['password'] === $password_actual) {
                try {
                    // Actualizar contraseña
                    $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                    $stmt->execute([$password_nueva, $_SESSION['user_id']]);

                    $mensaje = 'Contraseña actualizada exitosamente';
                    $tipo_mensaje = 'success';

                    // Actualizar información del usuario
                    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $usuario_actual = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    $mensaje = 'Error al actualizar la contraseña: ' . $e->getMessage();
                    $tipo_mensaje = 'error';
                }
            } else {
                $mensaje = 'La contraseña actual es incorrecta';
                $tipo_mensaje = 'error';
            }
        }
    }

    // Actualizar nombre
    if ($accion == 'actualizar_nombre') {
        $nombre_nuevo = trim($_POST['nombre']);

        if (empty($nombre_nuevo)) {
            $mensaje = 'El nombre no puede estar vacío';
            $tipo_mensaje = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ? WHERE id = ?");
                $stmt->execute([$nombre_nuevo, $_SESSION['user_id']]);

                $mensaje = 'Nombre actualizado exitosamente';
                $tipo_mensaje = 'success';

                // Actualizar sesión y datos del usuario
                $_SESSION['nombre'] = $nombre_nuevo;
                $usuario_actual['nombre'] = $nombre_nuevo;
            } catch (PDOException $e) {
                $mensaje = 'Error al actualizar el nombre: ' . $e->getMessage();
                $tipo_mensaje = 'error';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Aguas San Nicolás</title>
    <link rel="stylesheet" href="./styles/editar_perfil.css">
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Mi Perfil</h1>
            <a href="./index.php" class="btn-volver">← VOLVER AL DASHBOARD</a>
        </div>

        <?php if ($mensaje && $tipo_mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <div class="grid">
            <!-- Información del usuario -->
            <div class="card">
                <h2>Información Personal</h2>

                <div class="user-info">
                    <div class="info-item">
                        <span class="info-label">Usuario:</span>
                        <span class="info-value"><?php echo htmlspecialchars($usuario_actual['usuario']); ?></span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Rol:</span>
                        <span class="badge badge-<?php echo $usuario_actual['rol']; ?>">
                            <?php echo ucfirst($usuario_actual['rol']); ?>
                        </span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">ID:</span>
                        <span class="info-value">#<?php echo $usuario_actual['id']; ?></span>
                    </div>

                    <?php if (isset($usuario_actual['fecha_creacion'])): ?>
                        <div class="info-item">
                            <span class="info-label">Miembro desde:</span>
                            <span class="info-value">
                                <?php echo date('d/m/Y', strtotime($usuario_actual['fecha_creacion'])); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Formulario para cambiar nombre -->
                <form method="POST" action="" style="margin-top: 30px;">
                    <input type="hidden" name="accion" value="actualizar_nombre">

                    <div class="form-group">
                        <label for="nombre">Nombre Completo:</label>
                        <input type="text" id="nombre" name="nombre" required
                            value="<?php echo htmlspecialchars($usuario_actual['nombre']); ?>">
                    </div>

                    <button type="submit" class="btn">Actualizar Nombre</button>
                </form>
            </div>

            <!-- Cambiar contraseña -->
            <div class="card">
                <h2>Cambiar Contraseña</h2>

                <form method="POST" action="">
                    <input type="hidden" name="accion" value="cambiar_password">

                    <div class="form-group">
                        <label for="password_actual">Contraseña Actual:</label>
                        <input type="password" id="password_actual" name="password_actual" required
                            placeholder="Ingrese su contraseña actual">
                    </div>

                    <div class="form-group">
                        <label for="password_nueva">Contraseña Nueva:</label>
                        <input type="password" id="password_nueva" name="password_nueva" required
                            placeholder="Mínimo 6 caracteres" minlength="6">
                    </div>

                    <div class="form-group">
                        <label for="password_confirmar">Confirmar Contraseña Nueva:</label>
                        <input type="password" id="password_confirmar" name="password_confirmar" required
                            placeholder="Repita la contraseña nueva" minlength="6">
                    </div>

                    <button type="submit" class="btn">Cambiar Contraseña</button>
                </form>

                <div class="password-tips">
                    <h3>💡 Consejos de seguridad:</h3>
                    <ul>
                        <li>Use al menos 6 caracteres</li>
                        <li>Combine letras y números</li>
                        <li>No comparta su contraseña</li>
                        <li>Cambie su contraseña periódicamente</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>

</html>