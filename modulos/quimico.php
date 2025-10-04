<?php
require_once '../config.php';

$mensaje = '';
$turnoId = obtenerTurnoActivo();
$usuarioActivo = $_SESSION['user_id'] ?? 1; // Usuario logueado o fallback 1

// Procesar formularios
if ($_POST) {
    $pdo = conectarDB();

    try {
        if (isset($_POST['accion'])) {
            switch ($_POST['accion']) {
                case 'agregar_calidad_agua':
                    $registrosInsertados = 0;

                    // Procesar cada ubicación fija
                    $ubicaciones = [
                        'decantador_norte' => 'DECANTADOR NORTE',
                        'decantador_sur'   => 'DECANTADOR SUR',
                        'cisterna'         => 'CISTERNA',
                        'rio'              => 'RIO'
                    ];

                    foreach ($ubicaciones as $key => $nombre) {
                        $turbiedad = $_POST[$key . '_turbiedad'] ?? '';
                        $ph        = $_POST[$key . '_ph'] ?? '';
                        $cloro     = $_POST[$key . '_cloro'] ?? '';

                        if (!empty($turbiedad) || !empty($ph) || !empty($cloro)) {
                            $stmt = $pdo->prepare("INSERT INTO calidad_agua 
                                (lugar, turbiedad, ph, cloro_residual, turno_id) 
                                VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $nombre,
                                $turbiedad ?: null,
                                $ph ?: null,
                                $cloro ?: null,
                                $turnoId
                            ]);
                            $registrosInsertados++;
                        }
                    }

                    // Procesar filtros
                    $filtros = ['filtro_norte', 'filtro_sur'];
                    $nombres = ['FILTRO LINEA NORTE', 'FILTRO LINEA SUR'];

                    foreach ($filtros as $index => $key) {
                        $filtro    = $_POST[$key] ?? '';
                        $turbiedad = $_POST[$key . '_turbiedad'] ?? '';
                        $ph        = $_POST[$key . '_ph'] ?? '';

                        if (!empty($turbiedad) || !empty($ph) || !empty($filtro)) {
                            $lugar = $nombres[$index];
                            if (!empty($filtro)) {
                                $lugar .= ' - ' . $filtro;
                            }

                            $stmt = $pdo->prepare("INSERT INTO calidad_agua 
                                (lugar, turbiedad, ph, filtro_especifico, turno_id) 
                                VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $lugar,
                                $turbiedad ?: null,
                                $ph ?: null,
                                $filtro ?: null,
                                $turnoId
                            ]);
                            $registrosInsertados++;
                        }
                    }

                    if ($registrosInsertados > 0) {
                        $mensaje = mostrarMensaje('success', "{$registrosInsertados} registro(s) de calidad agregado(s) correctamente");
                    } else {
                        $mensaje = mostrarMensaje('error', 'Debe completar al menos un campo para registrar datos');
                    }
                    break;

                case 'agregar_novedad':
                    if (empty($_POST['descripcion'])) {
                        $mensaje = mostrarMensaje('error', 'Debe escribir una novedad');
                        break;
                    }

                    $stmt = $pdo->prepare("INSERT INTO novedades_turno 
                        (turno_id, descripcion, modulo, usuario_id) 
                        VALUES (?, ?, 'calidad_agua', ?)");
                    $stmt->execute([$turnoId, $_POST['descripcion'], $usuarioActivo]);
                    $mensaje = mostrarMensaje('success', 'Novedad agregada correctamente');
                    break;

                case 'editar_novedad':
                    if (empty($_POST['descripcion'])) {
                        $mensaje = mostrarMensaje('error', 'Debe escribir una novedad');
                        break;
                    }

                    $stmt = $pdo->prepare("UPDATE novedades_turno 
                        SET descripcion = ?, usuario_id = ? 
                        WHERE id = ? AND turno_id = ? AND modulo = 'calidad_agua'");
                    $stmt->execute([$_POST['descripcion'], $usuarioActivo, $_POST['id'], $turnoId]);
                    $mensaje = mostrarMensaje('success', 'Novedad editada correctamente');
                    break;

                case 'eliminar_novedad':
                    $stmt = $pdo->prepare("DELETE FROM novedades_turno 
                        WHERE id = ? AND turno_id = ? AND modulo = 'calidad_agua'");
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

// Obtener datos para mostrar (solo del módulo calidad_agua)
$registrosCalidad = obtenerRegistrosCalidad($turnoId);
$novedades = obtenerNovedades($turnoId, 'calidad_agua');

// Obtener novedad para editar si se solicita (solo del módulo calidad_agua)
$novedadEditar = null;
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("SELECT * FROM novedades_turno WHERE id = ? AND turno_id = ? AND modulo = 'calidad_agua'");
    $stmt->execute([$_GET['editar'], $turnoId]);
    $novedadEditar = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoreo de Calidad de Agua</title>
    <link rel="stylesheet" href="./styles/styles.css">
    <link rel="shortcut icon" href="./img/favicon.ico" type="image/x-icon">
</head>

<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">MONITOREO DE CALIDAD DE AGUA</h1>
                <div style="text-align: center;">
                    <a href="../index.php" class="button button-gray" style="display: inline-block; margin-bottom: 16px;">← VOLVER AL MENÚ</a>
                </div>
            </div>
            <div class="card-content">
                <?php echo $mensaje; ?>

                <!-- Formulario de calidad de agua -->
                <form method="POST">
                    <input type="hidden" name="accion" value="agregar_calidad_agua">
                    <div class="grid-6">
                        <!-- Decantador Norte -->
                        <div class="monitoring-section">
                            <div class="section-title">DECANTADOR NORTE</div>
                            <div class="input-row">
                                <div class="input-horizontal">
                                    <label class="label-inline">TURBIEDAD N.T.U.</label>
                                    <input type="number" step="0.01" class="input input-small" name="decantador_norte_turbiedad" placeholder="0.00">
                                </div>
                                <div class="input-horizontal">
                                    <label class="label-inline">pH</label>
                                    <input type="number" step="0.01" class="input input-small" name="decantador_norte_ph" placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        <!-- Decantador Sur -->
                        <div class="monitoring-section">
                            <div class="section-title">DECANTADOR SUR</div>
                            <div class="input-row">
                                <div class="input-horizontal">
                                    <label class="label-inline">TURBIEDAD N.T.U.</label>
                                    <input type="number" step="0.01" class="input input-small" name="decantador_sur_turbiedad" placeholder="0.00">
                                </div>
                                <div class="input-horizontal">
                                    <label class="label-inline">pH</label>
                                    <input type="number" step="0.01" class="input input-small" name="decantador_sur_ph" placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        <!-- Cisterna -->
                        <div class="monitoring-section">
                            <div class="section-title">CISTERNA</div>
                            <div class="input-row">
                                <div class="input-horizontal">
                                    <label class="label-inline">TURBIEDAD N.T.U.</label>
                                    <input type="number" step="0.01" class="input input-small" name="cisterna_turbiedad" placeholder="0.00">
                                </div>
                                <div class="input-horizontal">
                                    <label class="label-inline">pH</label>
                                    <input type="number" step="0.01" class="input input-small" name="cisterna_ph" placeholder="0.00">
                                </div>
                                <div class="input-horizontal">
                                    <label class="label-inline">CLORO RESIDUAL</label>
                                    <input type="number" step="0.01" class="input input-small" name="cisterna_cloro" placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        <!-- Rio -->
                        <div class="monitoring-section">
                            <div class="section-title">RIO</div>
                            <div class="input-row">
                                <div class="input-horizontal">
                                    <label class="label-inline">TURBIEDAD N.T.U.</label>
                                    <input type="number" step="0.01" class="input input-small" name="rio_turbiedad" placeholder="0.00">
                                </div>
                                <div class="input-horizontal">
                                    <label class="label-inline">pH</label>
                                    <input type="number" step="0.01" class="input input-small" name="rio_ph" placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        <!-- Filtro Linea Norte -->
                        <div class="monitoring-section">
                            <div class="section-title">FILTRO LINEA NORTE</div>
                            <div class="input-row">
                                <select class="select" name="filtro_norte">
                                    <option value="">Seleccionar</option>
                                    <option value="FILTRO 1">FILTRO 1</option>
                                    <option value="FILTRO 2">FILTRO 2</option>
                                    <option value="FILTRO 3">FILTRO 3</option>
                                </select>
                                <div class="input-horizontal">
                                    <label class="label-inline">TURBIEDAD N.T.U.</label>
                                    <input type="number" step="0.01" class="input input-small" name="filtro_norte_turbiedad" placeholder="0.00">
                                </div>
                                <div class="input-horizontal">
                                    <label class="label-inline">pH</label>
                                    <input type="number" step="0.01" class="input input-small" name="filtro_norte_ph" placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        <!-- Filtro Linea Sur -->
                        <div class="monitoring-section">
                            <div class="section-title">FILTRO LINEA SUR</div>
                            <div class="input-row">
                                <select class="select" name="filtro_sur">
                                    <option value="">Seleccionar</option>
                                    <option value="FILTRO 1">FILTRO 1</option>
                                    <option value="FILTRO 2">FILTRO 2</option>
                                    <option value="FILTRO 3">FILTRO 3</option>
                                </select>
                                <div class="input-horizontal">
                                    <label class="label-inline">TURBIEDAD N.T.U.</label>
                                    <input type="number" step="0.01" class="input input-small" name="filtro_sur_turbiedad" placeholder="0.00">
                                </div>
                                <div class="input-horizontal">
                                    <label class="label-inline">pH</label>
                                    <input type="number" step="0.01" class="input input-small" name="filtro_sur_ph" placeholder="0.00">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Confirm Button -->
                    <div class="center">
                        <button type="submit" class="button">CONFIRMAR</button>
                    </div>
                </form>

                <!-- Quality Records Table -->
                <?php if (!empty($registrosCalidad)): ?>
                    <div class="section">
                        <div class="section-title" style="text-align: center; margin-bottom: 12px;">REGISTROS DE CALIDAD</div>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>LUGAR</th>
                                    <th>TURBIEDAD N.T.U.</th>
                                    <th>pH</th>
                                    <th>CLORO RESIDUAL</th>
                                    <th>HORA</th>
                                    <th>USUARIO</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registrosCalidad as $registro): ?>
                                    <tr>
                                        <td style="font-weight: 600;"><?php echo htmlspecialchars($registro['lugar']); ?></td>
                                        <td><?php echo $registro['turbiedad'] ?: '-'; ?></td>
                                        <td><?php echo $registro['ph'] ?: '-'; ?></td>
                                        <td><?php echo $registro['cloro_residual'] ?: '-'; ?></td>
                                        <td><?php echo date('H:i:s', strtotime($registro['fecha_registro'])); ?></td>
                                        <td><?php echo htmlspecialchars($registro['usuario_nombre'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- Shift News Section - SOLO MÓDULO CALIDAD DE AGUA -->
                <div class="section">
                    <div class="novedades-header">
                        <div class="section-title" style="margin: 0;">NOVEDADES DEL TURNO - MÓDULO CALIDAD DE AGUA</div>
                        <a href="?modal=novedad" class="btn-agregar-novedad">AGREGAR NOVEDADES</a>
                    </div>
                    <div class="novedades-box">
                        <ul class="novedades-list">
                            <?php if (empty($novedades)): ?>
                                <li class="novedades-item">
                                    <div class="novedades-content">
                                        <span class="bullet">•</span>
                                        <span style="color: #9ca3af; font-style: italic;">No hay novedades registradas para el módulo de calidad de agua</span>
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
                    <h3>Agregar Novedad - Módulo Calidad de Agua</h3>
                </div>
                <form method="POST">
                    <input type="hidden" name="accion" value="agregar_novedad">
                    <div class="modal-body">
                        <input type="text" name="descripcion" placeholder="Escribir novedad del módulo de calidad de agua..." class="input-full" required>
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
                    <h3>Editar Novedad - Módulo Calidad de Agua</h3>
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