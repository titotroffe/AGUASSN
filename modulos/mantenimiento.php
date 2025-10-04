<?php
require_once '../config.php';

$mensaje = '';

// Funciones para obtener tareas
function obtenerTareasPorEstado($estado)
{
    $pdo = conectarDB();
    $stmt = $pdo->prepare("
        SELECT tm.id, tm.descripcion, tm.prioridad, tm.estado, tm.fecha_creacion, 
               tm.fecha_completada, tm.fecha_iniciada, tm.observaciones,
               GROUP_CONCAT(u.nombre SEPARATOR ', ') as usuarios_asignados
        FROM tareas_mantenimiento tm
        LEFT JOIN tarea_usuarios tu ON tm.id = tu.tarea_id
        LEFT JOIN usuarios u ON tu.usuario_id = u.id
        WHERE tm.estado = ?
        GROUP BY tm.id
        ORDER BY 
            CASE tm.prioridad 
                WHEN 'critica' THEN 1 
                WHEN 'alta' THEN 2 
                WHEN 'media' THEN 3 
                WHEN 'baja' THEN 4 
            END ASC, 
            tm.fecha_creacion DESC
    ");
    $stmt->execute([$estado]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Procesar acciones
if ($_POST) {
    try {
        $pdo = conectarDB();

        switch ($_POST['accion']) {
            case 'cambiar_estado':
                $fecha_completada = null;
                $fecha_iniciada = null;

                if ($_POST['nuevo_estado'] === 'completada') {
                    $fecha_completada = date('Y-m-d H:i:s');
                } elseif ($_POST['nuevo_estado'] === 'en_proceso') {
                    $fecha_iniciada = date('Y-m-d H:i:s');
                }

                $stmt = $pdo->prepare("UPDATE tareas_mantenimiento SET estado = ?, fecha_completada = ?, fecha_iniciada = ? WHERE id = ?");
                $stmt->execute([$_POST['nuevo_estado'], $fecha_completada, $fecha_iniciada, $_POST['tarea_id']]);

                echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
                exit;

            case 'iniciar_tarea':
                $stmt = $pdo->prepare("UPDATE tareas_mantenimiento SET estado = 'en_proceso', fecha_iniciada = NOW() WHERE id = ?");
                $stmt->execute([$_POST['tarea_id']]);

                $mensaje = mostrarMensaje('success', 'Tarea iniciada correctamente');
                break;

            case 'completar_tarea':
                $stmt = $pdo->prepare("UPDATE tareas_mantenimiento SET estado = 'completada', fecha_completada = NOW(), observaciones = ? WHERE id = ?");
                $stmt->execute([$_POST['observaciones'], $_POST['tarea_id']]);

                $mensaje = mostrarMensaje('success', 'Tarea completada correctamente');
                break;
        }
    } catch (Exception $e) {
        if (isset($_POST['accion']) && $_POST['accion'] === 'cambiar_estado') {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
        $mensaje = mostrarMensaje('error', 'Error: ' . $e->getMessage());
    }
}

// Obtener tareas por estado
$tareasPendientes = obtenerTareasPorEstado('pendiente');
$tareasEnProceso = obtenerTareasPorEstado('en_proceso');
$tareasCompletadas = obtenerTareasPorEstado('completada');

// Obtener tarea para ver detalles
$tareaDetalle = null;
if (isset($_GET['ver']) && is_numeric($_GET['ver'])) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("
        SELECT tm.id, tm.descripcion, tm.prioridad, tm.estado, tm.fecha_creacion, 
               tm.fecha_completada, tm.fecha_iniciada, tm.observaciones,
               GROUP_CONCAT(u.nombre SEPARATOR ', ') as usuarios_asignados
        FROM tareas_mantenimiento tm
        LEFT JOIN tarea_usuarios tu ON tm.id = tu.tarea_id
        LEFT JOIN usuarios u ON tu.usuario_id = u.id
        WHERE tm.id = ?
        GROUP BY tm.id
    ");
    $stmt->execute([$_GET['ver']]);
    $tareaDetalle = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo de Mantenimiento - Pizarrón de Tareas</title>
    <link rel="stylesheet" href="./styles/styles.css">
    <link rel="stylesheet" href="./styles/dashboard-jefatura.css">
    <link rel="stylesheet" href="./styles/modulo-mantenimiento.css">
    <link rel="shortcut icon" href="./img/favicon.ico" type="image/x-icon">
    <style>
        /* Estilos específicos para el módulo de mantenimiento usando el mismo diseño que el formulario industrial */
        .kanban-board {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .kanban-column {
            background-color: #4b5563;
            border: 1px solid #6b7280;
            border-radius: 8px;
            padding: 16px;
            min-height: 400px;
        }

        .column-header {
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 16px;
            padding: 8px;
            background-color: #374151;
            border-radius: 4px;
        }

        .task-card {
            background-color: #374151;
            border: 1px solid #6b7280;
            border-radius: 4px;
            padding: 12px;
            margin-bottom: 12px;
            cursor: move;
            transition: all 0.2s;
        }

        .task-card:hover {
            background-color: #4b5563;
            transform: translateY(-2px);
        }

        .task-card.dragging {
            opacity: 0.5;
        }

        .task-priority {
            font-size: 10px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 2px;
            margin-bottom: 8px;
            text-align: center;
            text-transform: uppercase;
        }

        .priority-critica {
            background-color: #dc2626;
            color: white;
        }

        .priority-alta {
            background-color: #ea580c;
            color: white;
        }

        .priority-media {
            background-color: #ca8a04;
            color: white;
        }

        .priority-baja {
            background-color: #059669;
            color: white;
        }

        .task-title {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .task-meta {
            font-size: 10px;
            color: #9ca3af;
            margin-bottom: 8px;
        }

        .task-info {
            font-size: 10px;
            color: #d1d5db;
            margin-bottom: 8px;
        }

        .task-actions {
            display: flex;
            gap: 4px;
        }

        .task-btn {
            background-color: #2563eb;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 2px;
            font-size: 10px;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .task-btn:hover {
            background-color: #1d4ed8;
        }

        .btn-start {
            background-color: #059669;
        }

        .btn-start:hover {
            background-color: #047857;
        }

        .btn-complete {
            background-color: #dc2626;
        }

        .btn-complete:hover {
            background-color: #b91c1c;
        }

        .drop-zone {
            text-align: center;
            color: #9ca3af;
            font-style: italic;
            padding: 20px;
            border: 2px dashed #6b7280;
            border-radius: 4px;
        }

        .drag-over {
            background-color: #1f2937;
            border-color: #2563eb;
        }

        .stats-section {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background-color: #4b5563;
            border: 1px solid #6b7280;
            border-radius: 4px;
            padding: 16px;
            text-align: center;
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 12px;
            color: #9ca3af;
        }

        @media (max-width: 768px) {
            .kanban-board {
                grid-template-columns: 1fr;
            }

            .stats-section {
                grid-template-columns: repeat(3, 1fr);
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">MÓDULO DE MANTENIMIENTO</h1>
                <div style="text-align: center;">
                    <a href="../index.php" class="button button-gray" style="display: inline-block; margin-bottom: 16px;">← VOLVER AL MENÚ</a>
                </div>
            </div>

            <div class="card-content">
                <?php echo $mensaje; ?>

                <div class="stats-section">
                    <div class="stat-card">
                        <div class="stat-number" style="color: #ef4444;"><?php echo count($tareasPendientes); ?></div>
                        <div class="stat-label">PENDIENTES</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" style="color: #3b82f6;"><?php echo count($tareasEnProceso); ?></div>
                        <div class="stat-label">EN PROCESO</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" style="color: #10b981;"><?php echo count($tareasCompletadas); ?></div>
                        <div class="stat-label">COMPLETADAS</div>
                    </div>
                </div>

                <div class="kanban-board">
                    <div class="kanban-column" data-estado="pendiente">
                        <div class="column-header">
                            PENDIENTES (<?php echo count($tareasPendientes); ?>)
                        </div>

                        <?php foreach ($tareasPendientes as $tarea): ?>
                            <div class="task-card" draggable="true" data-task-id="<?php echo $tarea['id']; ?>">
                                <div class="task-priority priority-<?php echo $tarea['prioridad']; ?>">
                                    <?php echo strtoupper($tarea['prioridad']); ?>
                                </div>

                                <div class="task-title">
                                    <?php echo htmlspecialchars($tarea['descripcion']); ?>
                                </div>

                                <div class="task-meta">
                                    MANTENIMIENTO • TAREA
                                </div>

                                <div class="task-info">
                                    <div>👤 <?php echo $tarea['usuarios_asignados'] ? htmlspecialchars($tarea['usuarios_asignados']) : 'Sin asignar'; ?></div>
                                    <div>📅 <?php echo date('d/m/Y', strtotime($tarea['fecha_creacion'])); ?></div>
                                </div>

                                <div class="task-actions">
                                    <a href="?ver=<?php echo $tarea['id']; ?>" class="task-btn">VER</a>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="accion" value="iniciar_tarea">
                                        <input type="hidden" name="tarea_id" value="<?php echo $tarea['id']; ?>">
                                        <button type="submit" class="task-btn btn-start">INICIAR</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($tareasPendientes)): ?>
                            <div class="drop-zone">
                                No hay tareas pendientes
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="kanban-column" data-estado="en_proceso">
                        <div class="column-header">
                            EN PROCESO (<?php echo count($tareasEnProceso); ?>)
                        </div>

                        <?php foreach ($tareasEnProceso as $tarea): ?>
                            <div class="task-card" draggable="true" data-task-id="<?php echo $tarea['id']; ?>">
                                <div class="task-priority priority-<?php echo $tarea['prioridad']; ?>">
                                    <?php echo strtoupper($tarea['prioridad']); ?>
                                </div>

                                <div class="task-title">
                                    <?php echo htmlspecialchars($tarea['descripcion']); ?>
                                </div>

                                <div class="task-meta">
                                    MANTENIMIENTO • EN PROCESO
                                </div>

                                <div class="task-info">
                                    <div>👤 <?php echo $tarea['usuarios_asignados'] ? htmlspecialchars($tarea['usuarios_asignados']) : 'Sin asignar'; ?></div>
                                    <div>📅 <?php echo date('d/m/Y', strtotime($tarea['fecha_creacion'])); ?></div>
                                    <?php if ($tarea['fecha_iniciada']): ?>
                                        <div>▶️ <?php echo date('d/m H:i', strtotime($tarea['fecha_iniciada'])); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="task-actions">
                                    <a href="?ver=<?php echo $tarea['id']; ?>" class="task-btn">VER</a>
                                    <a href="?completar=<?php echo $tarea['id']; ?>" class="task-btn btn-complete">COMPLETAR</a>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($tareasEnProceso)): ?>
                            <div class="drop-zone">
                                No hay tareas en proceso
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="kanban-column" data-estado="completada">
                        <div class="column-header">
                            COMPLETADAS (<?php echo count($tareasCompletadas); ?>)
                        </div>

                        <?php foreach ($tareasCompletadas as $tarea): ?>
                            <div class="task-card" data-task-id="<?php echo $tarea['id']; ?>">
                                <div class="task-priority priority-<?php echo $tarea['prioridad']; ?>">
                                    ✓
                                </div>

                                <div class="task-title">
                                    <?php echo htmlspecialchars($tarea['descripcion']); ?>
                                </div>

                                <div class="task-meta">
                                    MANTENIMIENTO • COMPLETADA
                                </div>

                                <div class="task-info">
                                    <div>👤 <?php echo $tarea['usuarios_asignados'] ? htmlspecialchars($tarea['usuarios_asignados']) : 'Sin asignar'; ?></div>
                                    <div>✅ <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_completada'])); ?></div>
                                </div>

                                <div class="task-actions">
                                    <a href="?ver=<?php echo $tarea['id']; ?>" class="task-btn">VER</a>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($tareasCompletadas)): ?>
                            <div class="drop-zone">
                                No hay tareas completadas
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($tareaDetalle): ?>
        <div class="modal show">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Detalles de la Tarea</h3>
                </div>

                <div class="modal-body">
                    <div style="margin-bottom: 12px;">
                        <strong>Descripción:</strong><br>
                        <?php echo htmlspecialchars($tareaDetalle['descripcion']); ?>
                    </div>

                    <div style="margin-bottom: 12px;">
                        <strong>Estado:</strong>
                        <span style="text-transform: uppercase;"><?php echo str_replace('_', ' ', $tareaDetalle['estado']); ?></span>
                    </div>

                    <div style="margin-bottom: 12px;">
                        <strong>Prioridad:</strong>
                        <span style="text-transform: uppercase;"><?php echo $tareaDetalle['prioridad']; ?></span>
                    </div>

                    <div style="margin-bottom: 12px;">
                        <strong>Fecha de Creación:</strong><br>
                        <?php echo date('d/m/Y H:i', strtotime($tareaDetalle['fecha_creacion'])); ?>
                    </div>

                    <?php if ($tareaDetalle['usuarios_asignados']): ?>
                        <div style="margin-bottom: 12px;">
                            <strong>Usuarios Asignados:</strong><br>
                            <?php echo htmlspecialchars($tareaDetalle['usuarios_asignados']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($tareaDetalle['fecha_iniciada']): ?>
                        <div style="margin-bottom: 12px;">
                            <strong>Fecha de Inicio:</strong><br>
                            <?php echo date('d/m/Y H:i', strtotime($tareaDetalle['fecha_iniciada'])); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($tareaDetalle['fecha_completada']): ?>
                        <div style="margin-bottom: 12px;">
                            <strong>Fecha de Completado:</strong><br>
                            <?php echo date('d/m/Y H:i', strtotime($tareaDetalle['fecha_completada'])); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($tareaDetalle['observaciones']): ?>
                        <div style="margin-bottom: 12px;">
                            <strong>Observaciones:</strong><br>
                            <?php echo htmlspecialchars($tareaDetalle['observaciones']); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="modal-footer">
                    <a href="?" class="button button-gray">CERRAR</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['completar']) && is_numeric($_GET['completar'])): ?>
        <div class="modal show">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Completar Tarea</h3>
                </div>

                <form method="POST">
                    <input type="hidden" name="accion" value="completar_tarea">
                    <input type="hidden" name="tarea_id" value="<?php echo $_GET['completar']; ?>">

                    <div class="modal-body">
                        <label class="label">Observaciones del Trabajo Realizado</label>
                        <textarea name="observaciones" class="input-full" rows="4" placeholder="Describe el trabajo realizado, materiales utilizados, etc." required></textarea>
                    </div>

                    <div class="modal-footer">
                        <a href="?" class="button button-gray">CANCELAR</a>
                        <button type="submit" class="button">COMPLETAR TAREA</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
        // Funcionalidad Drag & Drop
        let draggedElement = null;

        // Eventos de drag
        document.querySelectorAll('.task-card[draggable="true"]').forEach(card => {
            card.addEventListener('dragstart', function(e) {
                draggedElement = this;
                this.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/html', this.outerHTML);
            });

            card.addEventListener('dragend', function(e) {
                this.classList.remove('dragging');
                draggedElement = null;
            });
        });

        // Eventos de drop en columnas
        document.querySelectorAll('.kanban-column').forEach(column => {
            column.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                this.classList.add('drag-over');
            });

            column.addEventListener('dragleave', function(e) {
                this.classList.remove('drag-over');
            });

            column.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('drag-over');

                if (draggedElement) {
                    const taskId = draggedElement.getAttribute('data-task-id');
                    const newState = this.getAttribute('data-estado');

                    // Enviar petición AJAX para cambiar estado
                    fetch('', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `accion=cambiar_estado&tarea_id=${taskId}&nuevo_estado=${newState}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error al actualizar la tarea');
                        });
                }
            });
        });
    </script>
</body>

</html>