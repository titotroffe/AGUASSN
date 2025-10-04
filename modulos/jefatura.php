<?php
require_once '../config.php';

// Función para obtener niveles de tanques
function obtenerNivelesTanques()
{
    $pdo = conectarDB();
    $stmt = $pdo->query("
        SELECT nt.*
        FROM niveles_tanques nt 
        ORDER BY nt.tipo_quimico, nt.tipo_tanque
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener tareas de mantenimiento activas
function obtenerTareasMantenimiento()
{
    $pdo = conectarDB();
    $stmt = $pdo->query("
        SELECT tm.*, 
               GROUP_CONCAT(u.nombre SEPARATOR ', ') as usuarios_nombres,
               CASE 
                   WHEN tm.estado = 'en_proceso' AND tm.fecha_iniciada IS NOT NULL 
                   THEN TIMESTAMPDIFF(MINUTE, tm.fecha_iniciada, NOW())
                   ELSE NULL
               END as tiempo_transcurrido
        FROM tareas_mantenimiento tm
        LEFT JOIN tarea_usuarios tu ON tm.id = tu.tarea_id
        LEFT JOIN usuarios u ON tu.usuario_id = u.id
        WHERE tm.estado IN ('pendiente', 'en_proceso')
        GROUP BY tm.id
        ORDER BY 
            CASE tm.prioridad 
                WHEN 'critica' THEN 1 
                WHEN 'alta' THEN 2 
                WHEN 'media' THEN 3 
                WHEN 'baja' THEN 4 
            END,
            tm.fecha_creacion DESC
        LIMIT 10
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener tareas completadas
function obtenerTareasCompletadas()
{
    $pdo = conectarDB();
    $stmt = $pdo->query("
        SELECT tm.*, 
               GROUP_CONCAT(u.nombre SEPARATOR ', ') as usuarios_nombres,
               CASE 
                   WHEN tm.fecha_iniciada IS NOT NULL AND tm.fecha_completada IS NOT NULL 
                   THEN TIMESTAMPDIFF(MINUTE, tm.fecha_iniciada, tm.fecha_completada)
                   ELSE NULL
               END as tiempo_total
        FROM tareas_mantenimiento tm
        LEFT JOIN tarea_usuarios tu ON tm.id = tu.tarea_id
        LEFT JOIN usuarios u ON tu.usuario_id = u.id
        WHERE tm.estado = 'completada'
        GROUP BY tm.id
        ORDER BY tm.fecha_completada DESC
        LIMIT 10
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener usuarios de mantenimiento
function obtenerUsuariosMantenimiento()
{
    $pdo = conectarDB();
    $stmt = $pdo->query("
        SELECT id, nombre 
        FROM usuarios 
        WHERE rol = 'mantenimiento'
        ORDER BY nombre
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function crearTareaMantenimiento($descripcion, $prioridad, $usuarios_ids = [])
{
    $pdo = conectarDB();

    try {
        $pdo->beginTransaction();

        // Crear la tarea
        $stmt = $pdo->prepare("
            INSERT INTO tareas_mantenimiento 
            (descripcion, prioridad, fecha_creacion)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$descripcion, $prioridad]);

        $tarea_id = $pdo->lastInsertId();

        // Asignar usuarios si fueron seleccionados
        if (!empty($usuarios_ids)) {
            $stmt_asignacion = $pdo->prepare("
                INSERT INTO tarea_usuarios (tarea_id, usuario_id)
                VALUES (?, ?)
            ");

            foreach ($usuarios_ids as $usuario_id) {
                if (!empty($usuario_id)) {
                    $stmt_asignacion->execute([$tarea_id, $usuario_id]);
                }
            }
        }

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

// Función para formatear tiempo en minutos a horas y minutos
function formatearTiempo($minutos)
{
    if ($minutos === null) return null;

    $horas = floor($minutos / 60);
    $mins = $minutos % 60;

    if ($horas > 0) {
        return $horas . 'h ' . $mins . 'm';
    } else {
        return $mins . 'm';
    }
}

if ($_POST && isset($_POST['crear_tarea'])) {
    $descripcion = $_POST['descripcion'] ?? '';
    $prioridad = $_POST['prioridad'] ?? 'media';
    $usuarios_ids = $_POST['usuarios_ids'] ?? [];

    // Filtrar valores vacíos
    $usuarios_ids = array_filter($usuarios_ids, function ($id) {
        return !empty($id);
    });

    if (!empty($descripcion)) {
        if (crearTareaMantenimiento($descripcion, $prioridad, $usuarios_ids)) {
            $mensaje_exito = "Tarea creada exitosamente";
        } else {
            $mensaje_error = "Error al crear la tarea";
        }
    } else {
        $mensaje_error = "La descripción es obligatoria";
    }
}

// Función para obtener todas las novedades de turnos
function obtenerTodasLasNovedades()
{
    $pdo = conectarDB();
    $stmt = $pdo->query("
        SELECT nt.*, u.nombre as usuario_nombre, t.fecha_inicio as turno_inicio
        FROM novedades_turno nt
        LEFT JOIN usuarios u ON nt.usuario_id = u.id
        LEFT JOIN turnos t ON nt.turno_id = t.id
        ORDER BY nt.fecha_registro DESC
        LIMIT 20
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener registros de presión recientes
function obtenerRegistrosPresionRecientes()
{
    $pdo = conectarDB();
    $stmt = $pdo->query("
        SELECT rp.*, t.fecha_inicio as turno_inicio,
               u.nombre as usuario_nombre
        FROM registros_presion rp
        LEFT JOIN turnos t ON rp.turno_id = t.id
        LEFT JOIN usuarios u ON t.usuario_id = u.id
        ORDER BY rp.fecha_registro DESC
        LIMIT 15
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener registros de calidad recientes
function obtenerRegistrosCalidadRecientes()
{
    $pdo = conectarDB();
    $stmt = $pdo->query("
        SELECT ca.*, t.fecha_inicio as turno_inicio,
               u_turno.nombre as usuario_nombre
        FROM calidad_agua ca
        LEFT JOIN turnos t ON ca.turno_id = t.id
        LEFT JOIN usuarios u_turno ON t.usuario_id = u_turno.id
        ORDER BY ca.fecha_registro DESC
        LIMIT 15
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener clase de color según el nivel
function obtenerClaseNivel($nivel)
{
    if ($nivel <= 20) return 'low';
    if ($nivel <= 50) return 'medium';
    return 'normal';
}

// Obtener todos los datos
$nivelesTanques = obtenerNivelesTanques();
$tareasMantenimiento = obtenerTareasMantenimiento();
$tareasCompletadas = obtenerTareasCompletadas();
$usuariosMantenimiento = obtenerUsuariosMantenimiento();
$novedadesTurnos = obtenerTodasLasNovedades();
$registrosPresion = obtenerRegistrosPresionRecientes();
$registrosCalidad = obtenerRegistrosCalidadRecientes();

// Organizar niveles por tipo
$tanques = [];
foreach ($nivelesTanques as $nivel) {
    $tanques[$nivel['tipo_quimico']][$nivel['tipo_tanque']] = $nivel;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Jefe - Sistema de Monitoreo</title>
    <link rel="stylesheet" href="./styles/styles.css">
    <link rel="stylesheet" href="./styles/dashboard-jefatura.css">
    <link rel="stylesheet" href="./styles/tarea-jefatura.css">
    <link rel="shortcut icon" href="./img/favicon.ico" type="image/x-icon">
    <style>
        .form-group input,
        .form-group select,
        .form-group textarea {
            background-color: #1f2937;
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            color: white;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 115px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">DASHBOARD JEFATURA</h1>
                <div style="text-align: center;">
                    <a href="../index.php" class="button button-gray" style="display: inline-block; margin-bottom: 16px;">← VOLVER AL MENÚ</a>
                </div>
                <div style="text-align: center;">
                    <a href="./usuarios.php" class="button button-gray" style="display: inline-block; margin-bottom: 16px;">CREAR NUEVO EMPLEADO</a>
                </div>
            </div>
            <div class="card-content">

                <!-- Nueva sección para crear tareas de mantenimiento (Con múltiples usuarios) -->
                <div class="dashboard-grid">
                    <div class="dashboard-card full-width">
                        <div class="dashboard-title">CREAR NUEVA TAREA DE MANTENIMIENTO</div>

                        <?php if (isset($mensaje_exito)): ?>
                            <div class="mensaje exito"><?php echo $mensaje_exito; ?></div>
                        <?php endif; ?>

                        <?php if (isset($mensaje_error)): ?>
                            <div class="mensaje error"><?php echo $mensaje_error; ?></div>
                        <?php endif; ?>

                        <div class="form-container">
                            <form method="POST">
                                <div class="form-row">
                                    <div class="form-group" style="flex: 2;">
                                        <label for="descripcion">Descripción de la Tarea *</label>
                                        <textarea name="descripcion" id="descripcion" required placeholder="Describe detalladamente la tarea a realizar..."></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="usuarios_selector">Asignar usuarios</label>
                                        <div style="border: 1px solid #ddd; border-radius: 4px; max-height: 200px; overflow-y: auto;background-color:#1f2937">
                                            <?php foreach ($usuariosMantenimiento as $usuario): ?>
                                                <div onclick="toggleUser(this, <?php echo $usuario['id']; ?>)"
                                                    style="padding: 10px; cursor: pointer;">
                                                    <?php echo htmlspecialchars($usuario['nombre']); ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <div id="hiddenInputs"></div>

                                        <small style="color: #666; font-size: 12px; margin-top: 5px;">
                                            Haz clic para seleccionar/deseleccionar usuarios.
                                        </small>
                                    </div>
                                    <div class="form-group">
                                        <label for="prioridad">Prioridad</label>
                                        <select name="prioridad" id="prioridad">
                                            <option value="baja">Baja</option>
                                            <option value="media" selected>Media</option>
                                            <option value="alta">Alta</option>
                                            <option value="critica">Crítica</option>
                                        </select>
                                    </div>
                                </div>
                                <script>
                                    function toggleUser(element, userId) {
                                        if (element.classList.contains('selected')) {
                                            // Deseleccionar
                                            element.classList.remove('selected');
                                            element.style.position = '';
                                            element.innerHTML = element.innerHTML.replace('<span style="position: absolute; right: 10px; color: #4ade80; font-weight: bold;">✓</span>', '');
                                            const inputToRemove = document.querySelector(`input[value="${userId}"]`);
                                            if (inputToRemove) inputToRemove.remove();
                                        } else {
                                            // Seleccionar
                                            element.classList.add('selected');
                                            element.style.position = 'relative';
                                            element.innerHTML += '<span style="position: absolute; right: 10px; color: #4ade80; font-weight: bold;">✓</span>';

                                            const input = document.createElement('input');
                                            input.type = 'hidden';
                                            input.name = 'usuarios_ids[]';
                                            input.value = userId;
                                            document.getElementById('hiddenInputs').appendChild(input);
                                        }
                                    }
                                </script>
                                <div class="form-row">
                                    <button type="submit" name="crear_tarea" class="btn-crear">CREAR TAREA</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Niveles de Tanques -->
                <div class="dashboard-grid">
                    <!-- Tanques de Cloro -->
                    <div class="dashboard-card">
                        <div class="dashboard-title">NIVELES DE TANQUES - CLORO</div>
                        <div class="tank-container">
                            <?php if (isset($tanques['cloro']) && !empty($tanques['cloro'])): ?>
                                <?php foreach ($tanques['cloro'] as $tipo => $tanque): ?>
                                    <div class="tank-wrapper">
                                        <div class="tank">
                                            <div class="tank-fill cloro animate" style="height: <?php echo $tanque['nivel_actual']; ?>%"></div>
                                            <div class="tank-level"><?php echo $tanque['nivel_actual']; ?>%</div>
                                        </div>
                                        <div class="tank-label">
                                            <span class="tank-type"><?php echo strtoupper($tipo); ?></span>
                                            <span class="timestamp">
                                                <?php echo date('d/m H:i', strtotime($tanque['fecha_actualizacion'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="text-align: center; color: #666;">No hay datos de tanques de cloro disponibles</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Tanques de Sulfato -->
                    <div class="dashboard-card">
                        <div class="dashboard-title">NIVELES DE TANQUES - SULFATO</div>
                        <div class="tank-container">
                            <?php if (isset($tanques['sulfato']) && !empty($tanques['sulfato'])): ?>
                                <?php foreach ($tanques['sulfato'] as $tipo => $tanque): ?>
                                    <div class="tank-wrapper">
                                        <div class="tank">
                                            <div class="tank-fill sulfato animate" style="height: <?php echo $tanque['nivel_actual']; ?>%"></div>
                                            <div class="tank-level"><?php echo $tanque['nivel_actual']; ?>%</div>
                                        </div>
                                        <div class="tank-label">
                                            <span class="tank-type"><?php echo strtoupper($tipo); ?></span>
                                            <span class="timestamp">
                                                <?php echo date('d/m H:i', strtotime($tanque['fecha_actualizacion'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="text-align: center; color: #666;">No hay datos de tanques de sulfato disponibles</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Tanques de Poliamina -->
                    <div class="dashboard-card">
                        <div class="dashboard-title">NIVELES DE TANQUES - POLIAMINA</div>
                        <div class="tank-container">
                            <?php if (isset($tanques['poliamina']) && !empty($tanques['poliamina'])): ?>
                                <?php foreach ($tanques['poliamina'] as $tipo => $tanque): ?>
                                    <div class="tank-wrapper">
                                        <div class="tank">
                                            <div class="tank-fill poliamina animate" style="height: <?php echo $tanque['nivel_actual']; ?>%"></div>
                                            <div class="tank-level"><?php echo $tanque['nivel_actual']; ?>%</div>
                                        </div>
                                        <div class="tank-label">
                                            <span class="tank-type"><?php echo strtoupper($tipo); ?></span>
                                            <span class="timestamp">
                                                <?php echo date('d/m H:i', strtotime($tanque['fecha_actualizacion'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="text-align: center; color: #666;">No hay datos de tanques de poliamina disponibles</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tareas de Mantenimiento -->
                <div class="dashboard-grid">
                    <div class="dashboard-card full-width">
                        <div class="dashboard-title">TAREAS DE MANTENIMIENTO ACTIVAS</div>
                        <?php if (!empty($tareasMantenimiento)): ?>
                            <?php foreach ($tareasMantenimiento as $tarea): ?>
                                <div class="novedad-item">
                                    <div class="novedad-header">
                                        <div>
                                            <span class="priority-badge priority-<?php echo $tarea['prioridad']; ?>">
                                                <?php echo strtoupper($tarea['prioridad']); ?>
                                            </span>
                                            <?php if (isset($tarea['estado'])): ?>
                                                <span class="status-badge status-<?php echo $tarea['estado']; ?>">
                                                    <?php echo strtoupper(str_replace('_', ' ', $tarea['estado'])); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="timestamp">
                                            <?php echo date('d/m/Y H:i:s', strtotime($tarea['fecha_creacion'])); ?>
                                        </span>
                                    </div>
                                    <div class="novedad-content">
                                        <strong><?php echo htmlspecialchars($tarea['descripcion']); ?></strong>
                                    </div>
                                    <div class="novedad-meta">
                                        <span>Asignado a: <?php echo $tarea['usuarios_nombres'] ?: 'Sin asignar'; ?></span>
                                        <?php if ($tarea['estado'] == 'en_proceso' && $tarea['tiempo_transcurrido']): ?>
                                            <span>Tiempo transcurrido: <?php echo formatearTiempo($tarea['tiempo_transcurrido']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($tarea['fecha_iniciada']): ?>
                                            <span>Iniciada: <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_iniciada'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No hay tareas de mantenimiento activas.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tareas Completadas -->
                <div class="dashboard-grid">
                    <div class="dashboard-card full-width">
                        <div class="dashboard-title">ÚLTIMAS TAREAS COMPLETADAS</div>
                        <?php if (!empty($tareasCompletadas)): ?>
                            <?php foreach ($tareasCompletadas as $tarea): ?>
                                <div class="novedad-item">
                                    <div class="novedad-header">
                                        <div>
                                            <span class="priority-badge priority-<?php echo $tarea['prioridad']; ?>">
                                                <?php echo strtoupper($tarea['prioridad']); ?>
                                            </span>
                                            <span class="status-badge status-completada">
                                                COMPLETADA
                                            </span>
                                        </div>
                                        <span class="timestamp">
                                            Completada: <?php echo date('d/m/Y H:i:s', strtotime($tarea['fecha_completada'])); ?>
                                        </span>
                                    </div>
                                    <div class="novedad-content">
                                        <strong><?php echo htmlspecialchars($tarea['descripcion']); ?></strong>
                                    </div>
                                    <div class="novedad-meta">
                                        <span>Asignado a: <?php echo $tarea['usuarios_nombres'] ?: 'Sin asignar'; ?></span>
                                        <?php if ($tarea['tiempo_total']): ?>
                                            <span>Tiempo trabajado: <?php echo formatearTiempo($tarea['tiempo_total']); ?></span>
                                        <?php endif; ?>
                                        <span>Creada: <?php echo date('d/m/Y H:i:s', strtotime($tarea['fecha_creacion'])); ?></span>
                                        <?php if ($tarea['fecha_iniciada']): ?>
                                            <span>Iniciada: <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_iniciada'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No hay tareas completadas registradas.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Novedades de Turnos -->
                <div class="dashboard-grid">
                    <div class="dashboard-card full-width">
                        <div class="dashboard-title">NOVEDADES DE TURNOS RECIENTES</div>
                        <?php if (!empty($novedadesTurnos)): ?>
                            <?php foreach ($novedadesTurnos as $novedad): ?>
                                <div class="novedad-item">
                                    <div class="novedad-header">
                                        <div>
                                            <span class="novedad-modulo <?php echo $novedad['modulo']; ?>">
                                                <?php echo strtoupper(str_replace('_', ' ', $novedad['modulo'])); ?>
                                            </span>
                                            <span class="timestamp">
                                                <?php echo date('d/m/Y H:i:s', strtotime($novedad['fecha_registro'])); ?>
                                            </span>
                                        </div>
                                        <span class="timestamp">
                                            Usuario: <?php echo $novedad['usuario_nombre'] ?: 'Sistema'; ?>
                                        </span>
                                    </div>
                                    <div class="novedad-content">
                                        <?php echo htmlspecialchars($novedad['descripcion']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No hay novedades registradas.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Registros de Presión -->
                <div class="dashboard-grid">
                    <div class="dashboard-card full-width">
                        <div class="dashboard-title">REGISTROS DE PRESIÓN RECIENTES</div>
                        <?php if (!empty($registrosPresion)): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Fecha/Hora</th>
                                        <th>Tanque</th>
                                        <th>Planta</th>
                                        <th>Falcon</th>
                                        <th>Cisterna</th>
                                        <th>Usuario</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($registrosPresion as $registro): ?>
                                        <tr>
                                            <td><?php echo date('d/m H:i:s', strtotime($registro['fecha_registro'])); ?></td>
                                            <td><?php echo $registro['presion_tanque'] ?: '-'; ?></td>
                                            <td><?php echo $registro['presion_planta'] ?: '-'; ?></td>
                                            <td><?php echo $registro['presion_falcon'] ?: '-'; ?></td>
                                            <td><?php echo $registro['nivel_cisterna'] ?: '-'; ?></td>
                                            <td><?php echo $registro['usuario_nombre'] ?: 'Sistema'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>No hay registros de presión disponibles.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Registros de Calidad -->
                <div class="dashboard-grid">
                    <div class="dashboard-card full-width">
                        <div class="dashboard-title">REGISTROS DE CALIDAD DE AGUA RECIENTES</div>
                        <?php if (!empty($registrosCalidad)): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Fecha/Hora</th>
                                        <th>Lugar</th>
                                        <th>Turbiedad</th>
                                        <th>pH</th>
                                        <th>Cloro Residual</th>
                                        <th>Usuario</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($registrosCalidad as $registro): ?>
                                        <tr>
                                            <td><?php echo date('d/m H:i:s', strtotime($registro['fecha_registro'])); ?></td>
                                            <td><?php echo htmlspecialchars($registro['lugar']); ?></td>
                                            <td><?php echo $registro['turbiedad'] ?: '-'; ?></td>
                                            <td><?php echo $registro['ph'] ?: '-'; ?></td>
                                            <td><?php echo $registro['cloro_residual'] ?: '-'; ?></td>
                                            <td><?php echo $registro['usuario_nombre'] ?: 'Sistema'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>No hay registros de calidad de agua disponibles.</p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // Animación de llenado de tanques al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            const tankFills = document.querySelectorAll('.tank-fill');
            tankFills.forEach(fill => {
                const height = fill.style.height;
                fill.style.height = '0%';
                setTimeout(() => {
                    fill.style.height = height;
                }, 500);
            });
        });

        // Auto-refresh cada 30 segundos
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>

</html>