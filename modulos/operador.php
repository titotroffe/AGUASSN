<?php
require_once '../config.php';

$mensaje = '';
$turnoId = obtenerTurnoActivo();
$usuarioActivo = $_SESSION['user_id'];

// Procesar formularios
if ($_POST) {
    $pdo = conectarDB();

    try {
        if (isset($_POST['accion'])) {
            switch ($_POST['accion']) {
                case 'agregar_presion':
                    // registros_presion NO tiene usuario_id
                    $stmt = $pdo->prepare("INSERT INTO registros_presion (presion_tanque, presion_planta, presion_falcon, nivel_cisterna, turno_id) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['presion_tanque'],
                        $_POST['presion_planta'],
                        $_POST['presion_falcon'],
                        $_POST['nivel_cisterna'],
                        $turnoId
                    ]);
                    $mensaje = mostrarMensaje('success', 'Registro de presión agregado correctamente');
                    break;

                case 'registrar_lavado':
                    $filtrosSeleccionados = [];
                    if (isset($_POST['filtros'])) {
                        foreach ($_POST['filtros'] as $filtro) {
                            $filtrosSeleccionados[] = $filtro;
                        }
                    }

                    if (empty($filtrosSeleccionados)) {
                        $mensaje = mostrarMensaje('error', 'Debe seleccionar al menos un filtro');
                        break;
                    }

                    if (empty($_POST['hora_inicio']) || empty($_POST['hora_final'])) {
                        $mensaje = mostrarMensaje('error', 'Debe completar hora de inicio y hora final');
                        break;
                    }

                    $filtrosTexto = implode(', ', $filtrosSeleccionados);

                    // lavados_filtros NO tiene usuario_id
                    $stmt = $pdo->prepare("INSERT INTO lavados_filtros (turno_id, filtros_lavados, hora_inicio, hora_final) VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $turnoId,
                        $filtrosTexto,
                        $_POST['hora_inicio'],
                        $_POST['hora_final']
                    ]);

                    // Agregar a novedades_turno (sí tiene usuario_id)
                    $descripcionLavado = "LAVADO REALIZADO: {$filtrosTexto} - INICIO: {$_POST['hora_inicio']} - FIN: {$_POST['hora_final']}";
                    $stmt = $pdo->prepare("INSERT INTO novedades_turno (turno_id, descripcion, modulo, usuario_id) VALUES (?, ?, 'industrial', ?)");
                    $stmt->execute([$turnoId, $descripcionLavado, $usuarioActivo]);

                    $mensaje = mostrarMensaje('success', 'Lavado registrado correctamente');
                    break;

                case 'setear_nivel':
                    if (empty($_POST['nivel'])) {
                        $mensaje = mostrarMensaje('error', 'Debe ingresar un nivel');
                        break;
                    }

                    $stmt = $pdo->prepare("INSERT INTO niveles_quimicos (turno_id, tipo_quimico, nivel, usuario_id) VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $turnoId,
                        $_POST['tipo_quimico'],
                        $_POST['nivel'],
                        $usuarioActivo
                    ]);
                    $mensaje = mostrarMensaje('success', "Nivel de {$_POST['tipo_quimico']} seteado correctamente: {$_POST['nivel']}");
                    break;

                case 'actualizar_nivel_tanque':
                    if (empty($_POST['nivel_actual'])) {
                        $mensaje = mostrarMensaje('error', 'Debe ingresar un nivel');
                        break;
                    }

                    // Actualizar o insertar el nivel del tanque específico
                    $stmt = $pdo->prepare("
                        INSERT INTO niveles_tanques (tipo_quimico, tipo_tanque, nivel_actual, turno_id, fecha_actualizacion) 
                        VALUES (?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE 
                        nivel_actual = VALUES(nivel_actual), 
                        turno_id = VALUES(turno_id), 
                        fecha_actualizacion = NOW()
                    ");
                    $stmt->execute([
                        $_POST['tipo_quimico'],
                        $_POST['tipo_tanque'],
                        $_POST['nivel_actual'],
                        $turnoId
                    ]);

                    $tipoTanqueTexto = $_POST['tipo_tanque'] == 'principal' ? 'Principal' : 'Auxiliar';
                    $mensaje = mostrarMensaje('success', "Nivel de {$_POST['tipo_quimico']} - Tanque {$tipoTanqueTexto} actualizado: {$_POST['nivel_actual']}%");
                    break;

                case 'agregar_novedad':
                    if (empty($_POST['descripcion'])) {
                        $mensaje = mostrarMensaje('error', 'Debe escribir una novedad');
                        break;
                    }

                    $stmt = $pdo->prepare("INSERT INTO novedades_turno (turno_id, descripcion, modulo, usuario_id) VALUES (?, ?, 'industrial', ?)");
                    $stmt->execute([$turnoId, $_POST['descripcion'], $usuarioActivo]);
                    $mensaje = mostrarMensaje('success', 'Novedad agregada correctamente');
                    break;

                case 'editar_novedad':
                    if (empty($_POST['descripcion'])) {
                        $mensaje = mostrarMensaje('error', 'Debe escribir una novedad');
                        break;
                    }

                    $stmt = $pdo->prepare("UPDATE novedades_turno SET descripcion = ?, usuario_id = ? WHERE id = ? AND turno_id = ? AND modulo = 'industrial'");
                    $stmt->execute([$_POST['descripcion'], $usuarioActivo, $_POST['id'], $turnoId]);
                    $mensaje = mostrarMensaje('success', 'Novedad editada correctamente');
                    break;

                case 'eliminar_novedad':
                    $stmt = $pdo->prepare("DELETE FROM novedades_turno WHERE id = ? AND turno_id = ? AND modulo = 'industrial'");
                    $stmt->execute([$_POST['id'], $turnoId]);
                    $mensaje = mostrarMensaje('success', 'Novedad eliminada correctamente');
                    break;

                case 'cerrar_turno':
                    // Cerrar turno actual (sin abrir uno nuevo)
                    $pdo = conectarDB();
                    $stmt = $pdo->prepare("UPDATE turnos SET fecha_cierre = NOW(), estado = 'cerrado' WHERE id = ?");
                    $stmt->execute([$turnoId]);

                    // Destruir sesión para desloguear
                    session_unset();
                    session_destroy();

                    // Redirigir al login
                    header('Location: ../login.php');
                    exit;
            }
        }
    } catch (Exception $e) {
        $mensaje = mostrarMensaje('error', 'Error: ' . $e->getMessage());
    }
}

// Obtener datos para mostrar (solo del módulo industrial)
$registrosPresion = obtenerRegistrosPresion($turnoId);
$novedades = obtenerNovedades($turnoId, 'industrial');

// Obtener novedad para editar si se solicita (solo del módulo industrial)
$novedadEditar = null;
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("SELECT * FROM novedades_turno WHERE id = ? AND turno_id = ? AND modulo = 'industrial'");
    $stmt->execute([$_GET['editar'], $turnoId]);
    $novedadEditar = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Obtener niveles actuales de tanques químicos
$pdo = conectarDB();
$stmt = $pdo->prepare("SELECT tipo_quimico, tipo_tanque, nivel_actual, fecha_actualizacion FROM niveles_tanques ORDER BY tipo_quimico, tipo_tanque");
$stmt->execute();
$nivelesActuales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organizar niveles por químico y tipo de tanque
$nivelesPorQuimico = [];
foreach ($nivelesActuales as $nivel) {
    $nivelesPorQuimico[$nivel['tipo_quimico']][$nivel['tipo_tanque']] = $nivel;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulario de Monitoreo Industrial</title>
    <link rel="stylesheet" href="./styles/styles.css">
    <link rel="shortcut icon" href="./img/favicon.ico" type="image/x-icon">
</head>

<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">FORMULARIO DE OPERADOR DE TURNO</h1>
                <div style="text-align: center;">
                    <a href="../index.php" class="button button-gray" style="display: inline-block; margin-bottom: 16px;">← VOLVER AL MENÚ</a>
                </div>
            </div>
            <div class="card-content">
                <?php echo $mensaje; ?>

                <!-- Pressure Readings -->
                <form method="POST">
                    <input type="hidden" name="accion" value="agregar_presion">
                    <div class="grid-4">
                        <div class="input-group">
                            <label class="label">PRESION EN TANQUE</label>
                            <input type="number" step="0.01" class="input" name="presion_tanque" placeholder="0.00" required>
                        </div>
                        <div class="input-group">
                            <label class="label">PRESION EN PLANTA</label>
                            <input type="number" step="0.01" class="input" name="presion_planta" placeholder="0.00" required>
                        </div>
                        <div class="input-group">
                            <label class="label">PRESION EN FALCON</label>
                            <input type="number" step="0.01" class="input" name="presion_falcon" placeholder="0.00" required>
                        </div>
                        <div class="input-group">
                            <label class="label">NIVEL DE CISTERNA</label>
                            <input type="number" step="0.01" class="input" name="nivel_cisterna" placeholder="0.00" required>
                        </div>
                    </div>
                    <div class="center">
                        <button type="submit" class="button">CONFIRMAR</button>
                    </div>
                </form>

                <!-- Records Table -->
                <?php if (!empty($registrosPresion)): ?>
                    <div class="section">
                        <label class="label" style="display: block; text-align: center; margin-bottom: 12px; font-weight: bold;">REGISTROS DEL TURNO</label>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>PRESION BAJADA DE TANQUE</th>
                                    <th>PRESION EN PLANTA</th>
                                    <th>PRESION EN FALCON</th>
                                    <th>NIVEL DE CISTERNA</th>
                                    <th>HORA</th>
                                    <th>USUARIO</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registrosPresion as $registro): ?>
                                    <tr>
                                        <td><?php echo $registro['presion_tanque']; ?></td>
                                        <td><?php echo $registro['presion_planta']; ?></td>
                                        <td><?php echo $registro['presion_falcon']; ?></td>
                                        <td><?php echo $registro['nivel_cisterna']; ?></td>
                                        <td><?php echo date('H:i:s', strtotime($registro['fecha_registro'])); ?></td>
                                        <td><?php echo htmlspecialchars($registro['usuario_nombre'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- Filters and Time Section -->
                <form method="POST">
                    <input type="hidden" name="accion" value="registrar_lavado">
                    <div class="grid-3">
                        <div class="filter-section">
                            <label class="label" style="font-weight: bold;">LINEA NORTE</label>
                            <div class="filter-group">
                                <div class="checkbox-item">
                                    <input type="checkbox" class="checkbox" name="filtros[]" value="LINEA NORTE - FILTRO 1">
                                    <label class="label">FILTRO 1</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" class="checkbox" name="filtros[]" value="LINEA NORTE - FILTRO 2">
                                    <label class="label">FILTRO 2</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" class="checkbox" name="filtros[]" value="LINEA NORTE - FILTRO 3">
                                    <label class="label">FILTRO 3</label>
                                </div>
                            </div>
                        </div>

                        <div class="filter-section">
                            <label class="label" style="font-weight: bold;">LINEA SUR</label>
                            <div class="filter-group">
                                <div class="checkbox-item">
                                    <input type="checkbox" class="checkbox" name="filtros[]" value="LINEA SUR - FILTRO 1">
                                    <label class="label">FILTRO 1</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" class="checkbox" name="filtros[]" value="LINEA SUR - FILTRO 2">
                                    <label class="label">FILTRO 2</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" class="checkbox" name="filtros[]" value="LINEA SUR - FILTRO 3">
                                    <label class="label">FILTRO 3</label>
                                </div>
                            </div>
                        </div>

                        <div class="time-section">
                            <div class="time-group">
                                <label class="label" style="font-weight: bold;">HORA INICIO</label>
                                <input type="time" class="input input-time" name="hora_inicio" required>
                            </div>
                            <div class="time-group">
                                <label class="label" style="font-weight: bold;">HORA FINAL</label>
                                <input type="time" class="input input-time" name="hora_final" required>
                            </div>
                        </div>
                    </div>
                    <div class="center">
                        <button type="submit" class="button button-gray">LAVADO</button>
                    </div>
                </form>

                <!-- Shift News Section - SOLO MÓDULO INDUSTRIAL -->
                <div class="section">
                    <div class="novedades-header">
                        <label class="label" style="font-weight: bold;">NOVEDADES DEL TURNO - MÓDULO INDUSTRIAL</label>
                        <a href="?modal=novedad" class="btn-agregar-novedad">AGREGAR NOVEDADES</a>
                    </div>
                    <div class="novedades-box">
                        <ul class="novedades-list">
                            <?php if (empty($novedades)): ?>
                                <li class="novedades-item">
                                    <div class="novedades-content">
                                        <span class="bullet">•</span>
                                        <span style="color: #9ca3af; font-style: italic;">No hay novedades registradas para el módulo industrial</span>
                                    </div>
                                </li>
                            <?php else: ?>
                                <?php foreach ($novedades as $novedad): ?>
                                    <li class="novedades-item">
                                        <div class="novedades-content">
                                            <span class="bullet">•</span>
                                            <div class="novedad-text">
                                                <span><?php echo htmlspecialchars($novedad['descripcion']); ?></span>
                                                <small class="novedad-hora"><?php echo date('H:i:s', strtotime($novedad['fecha_registro'])); ?></small>
                                            </div>
                                        </div>
                                        <div class="novedades-actions">
                                            <a href="?editar=<?php echo $novedad['id']; ?>" class="icon-button icon-button-edit">EDITAR</a>
                                            <form method="POST" class="form-inline">
                                                <input type="hidden" name="accion" value="eliminar_novedad">
                                                <input type="hidden" name="id" value="<?php echo $novedad['id']; ?>">
                                                <button type="submit" class="icon-button" onclick="return confirm('¿Eliminar esta novedad?')">ELIMINAR</button>
                                            </form>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <!-- Chemical Levels -->
                <div class="section">
                    <label class="label" style="display: block; text-align: center; margin-bottom: 20px; font-weight: bold; font-size: 18px;">NIVELES DE TANQUES QUÍMICOS</label>

                    <!-- Reorganized layout to show 3 chemicals aligned horizontally with auxiliar tanks below principal ones -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 30px; margin-bottom: 30px;">
                        <!-- CLORO Column -->
                        <div class="chemical-column">
                            <h3 style="text-align: center; margin-bottom: 15px; color: #2563eb;">CLORO</h3>

                            <!-- Tanque Principal de Cloro -->
                            <div class="chemical-section" style="margin-bottom: 20px;">
                                <form method="POST">
                                    <input type="hidden" name="accion" value="actualizar_nivel_tanque">
                                    <input type="hidden" name="tipo_quimico" value="cloro">
                                    <input type="hidden" name="tipo_tanque" value="principal">
                                    <label class="label">TANQUE PRINCIPAL</label>
                                    <div style="margin-bottom: 10px;">
                                        <small>Nivel actual: <?php echo isset($nivelesPorQuimico['cloro']['principal']) ? $nivelesPorQuimico['cloro']['principal']['nivel_actual'] . '%' : '0%'; ?></small>
                                    </div>
                                    <input type="number" step="0.01" min="0" max="100" class="input" name="nivel_actual" placeholder="Ej: 85.5" required>
                                    <button type="submit" class="button button-small">ACTUALIZAR NIVEL</button>
                                </form>
                            </div>

                            <!-- Tanque Auxiliar de Cloro -->
                            <div class="chemical-section">
                                <form method="POST">
                                    <input type="hidden" name="accion" value="actualizar_nivel_tanque">
                                    <input type="hidden" name="tipo_quimico" value="cloro">
                                    <input type="hidden" name="tipo_tanque" value="auxiliar">
                                    <label class="label">TANQUE AUXILIAR</label>
                                    <div style="margin-bottom: 10px;">
                                        <small>Nivel actual: <?php echo isset($nivelesPorQuimico['cloro']['auxiliar']) ? $nivelesPorQuimico['cloro']['auxiliar']['nivel_actual'] . '%' : '0%'; ?></small>
                                    </div>
                                    <input type="number" step="0.01" min="0" max="100" class="input" name="nivel_actual" placeholder="Ej: 72.3" required>
                                    <button type="submit" class="button button-small">ACTUALIZAR NIVEL</button>
                                </form>
                            </div>
                        </div>

                        <!-- POLIAMINA Column -->
                        <div class="chemical-column">
                            <h3 style="text-align: center; margin-bottom: 15px; color: #059669;">POLIAMINA</h3>

                            <!-- Tanque Principal de Poliamina -->
                            <div class="chemical-section" style="margin-bottom: 20px;">
                                <form method="POST">
                                    <input type="hidden" name="accion" value="actualizar_nivel_tanque">
                                    <input type="hidden" name="tipo_quimico" value="poliamina">
                                    <input type="hidden" name="tipo_tanque" value="principal">
                                    <label class="label">TANQUE PRINCIPAL</label>
                                    <div style="margin-bottom: 10px;">
                                        <small>Nivel actual: <?php echo isset($nivelesPorQuimico['poliamina']['principal']) ? $nivelesPorQuimico['poliamina']['principal']['nivel_actual'] . '%' : '0%'; ?></small>
                                    </div>
                                    <input type="number" step="0.01" min="0" max="100" class="input" name="nivel_actual" placeholder="Ej: 68.7" required>
                                    <button type="submit" class="button button-small">ACTUALIZAR NIVEL</button>
                                </form>
                            </div>

                            <!-- Tanque Auxiliar de Poliamina -->
                            <div class="chemical-section">
                                <form method="POST">
                                    <input type="hidden" name="accion" value="actualizar_nivel_tanque">
                                    <input type="hidden" name="tipo_quimico" value="poliamina">
                                    <input type="hidden" name="tipo_tanque" value="auxiliar">
                                    <label class="label">TANQUE AUXILIAR</label>
                                    <div style="margin-bottom: 10px;">
                                        <small>Nivel actual: <?php echo isset($nivelesPorQuimico['poliamina']['auxiliar']) ? $nivelesPorQuimico['poliamina']['auxiliar']['nivel_actual'] . '%' : '0%'; ?></small>
                                    </div>
                                    <input type="number" step="0.01" min="0" max="100" class="input" name="nivel_actual" placeholder="Ej: 45.2" required>
                                    <button type="submit" class="button button-small">ACTUALIZAR NIVEL</button>
                                </form>
                            </div>
                        </div>

                        <!-- SULFATO Column -->
                        <div class="chemical-column">
                            <h3 style="text-align: center; margin-bottom: 15px; color: #dc2626;">SULFATO</h3>

                            <!-- Tanque Principal de Sulfato -->
                            <div class="chemical-section" style="margin-bottom: 20px;">
                                <form method="POST">
                                    <input type="hidden" name="accion" value="actualizar_nivel_tanque">
                                    <input type="hidden" name="tipo_quimico" value="sulfato">
                                    <input type="hidden" name="tipo_tanque" value="principal">
                                    <label class="label">TANQUE PRINCIPAL</label>
                                    <div style="margin-bottom: 10px;">
                                        <small>Nivel actual: <?php echo isset($nivelesPorQuimico['sulfato']['principal']) ? $nivelesPorQuimico['sulfato']['principal']['nivel_actual'] . '%' : '0%'; ?></small>
                                    </div>
                                    <input type="number" step="0.01" min="0" max="100" class="input" name="nivel_actual" placeholder="Ej: 92.1" required>
                                    <button type="submit" class="button button-small">ACTUALIZAR NIVEL</button>
                                </form>
                            </div>

                            <!-- Tanque Auxiliar de Sulfato -->
                            <div class="chemical-section">
                                <form method="POST">
                                    <input type="hidden" name="accion" value="actualizar_nivel_tanque">
                                    <input type="hidden" name="tipo_quimico" value="sulfato">
                                    <input type="hidden" name="tipo_tanque" value="auxiliar">
                                    <label class="label">TANQUE AUXILIAR</label>
                                    <div style="margin-bottom: 10px;">
                                        <small>Nivel actual: <?php echo isset($nivelesPorQuimico['sulfato']['auxiliar']) ? $nivelesPorQuimico['sulfato']['auxiliar']['nivel_actual'] . '%' : '0%'; ?></small>
                                    </div>
                                    <input type="number" step="0.01" min="0" max="100" class="input" name="nivel_actual" placeholder="Ej: 78.9" required>
                                    <button type="submit" class="button button-small">ACTUALIZAR NIVEL</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Close Shift Button -->
                <div class="center" style="padding-top: 16px;">
                    <form method="POST">
                        <input type="hidden" name="accion" value="cerrar_turno">
                        <button type="submit" class="button button-red" onclick="return confirm('¿Está seguro de cerrar el turno?')">CERRAR TURNO</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para agregar novedad -->
    <?php if (isset($_GET['modal']) && $_GET['modal'] === 'novedad'): ?>
        <div class="modal show">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Agregar Novedad - Módulo Industrial</h3>
                </div>
                <form method="POST">
                    <input type="hidden" name="accion" value="agregar_novedad">
                    <div class="modal-body">
                        <input type="text" name="descripcion" placeholder="Escribir novedad del módulo industrial..." class="input-full" required>
                    </div>
                    <div class="modal-footer">
                        <a href="?" class="button button-gray">CANCELAR</a>
                        <button type="submit" class="button">AGREGAR</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Modal para editar novedad -->
    <?php if ($novedadEditar): ?>
        <div class="modal show">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Editar Novedad - Módulo Industrial</h3>
                </div>
                <form method="POST">
                    <input type="hidden" name="accion" value="editar_novedad">
                    <input type="hidden" name="id" value="<?php echo $novedadEditar['id']; ?>">
                    <div class="modal-body">
                        <input type="text" name="descripcion" value="<?php echo htmlspecialchars($novedadEditar['descripcion']); ?>" class="input-full" required>
                    </div>
                    <div class="modal-footer">
                        <a href="?" class="button button-gray">CANCELAR</a>
                        <button type="submit" class="button">GUARDAR</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</body>

</html>