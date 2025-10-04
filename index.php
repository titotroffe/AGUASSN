<?php
// index.php - Página principal protegida
require_once 'config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  header("Location: login.php");
  exit();
}

$rol_usuario = $_SESSION['rol'];
$nombre_usuario = $_SESSION['nombre'];
?>

<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>AGUAS DE SAN NICOLAS</title>
  <link rel="stylesheet" href="./styles/index.css" />
  <link rel="shortcut icon" href="./img/favicon.ico" type="image/x-icon">
</head>

<body>
  <div class="header-info">
    <span>Bienvenido, <?php echo htmlspecialchars($nombre_usuario); ?></span>
    <div>
      <a href="editar_perfil.php" class="header-btn">Editar Perfil</a>
      <a href="logout.php" class="header-btn">Cerrar Sesión</a>
    </div>
  </div>
  <section>

    <div class="iconos">
      <div class="icono">
        <p>Jefatura</p>
        <a href="verificar_acceso.php?seccion=jefatura">
          <img src="./img/jefatura.svg" alt="Jefatura" />
        </a>
      </div>
      <div class="icono">
        <p>Encargado de Turno</p>
        <a href="verificar_acceso.php?seccion=operador">
          <img src="./img/operador.svg" alt="Operador" />
        </a>
      </div>

      <div class="icono">
        <p>Químico</p>
        <a href="verificar_acceso.php?seccion=quimico">
          <img src="./img/quimico.svg" alt="Químico" />
        </a>
      </div>

      <div class="icono">
        <p>Mantenimiento</p>
        <a href="verificar_acceso.php?seccion=mantenimiento">
          <img src="./img/mantenimiento2.svg" alt="Mantenimiento" />
        </a>
      </div>
    </div>
  </section>

  <script>
    // Función para mostrar popup de error
    function mostrarErrorPopup(mensaje) {
      // Crear el overlay
      const overlay = document.createElement('div');
      overlay.className = 'error-overlay';

      // Crear el elemento del popup
      const popup = document.createElement('div');
      popup.className = 'error-message';
      popup.innerHTML = `
            ${mensaje}
            <button class="close-btn" onclick="cerrarPopup()">&times;</button>
        `;

      // Añadir el popup al overlay
      overlay.appendChild(popup);

      // Añadir el overlay al body
      document.body.appendChild(overlay);

      // Cerrar automáticamente después de 5 segundos
      setTimeout(() => {
        cerrarPopup();
      }, 5000);
    }

    // Función para cerrar el popup
    function cerrarPopup() {
      const overlay = document.querySelector('.error-overlay');
      if (overlay) {
        overlay.parentNode.removeChild(overlay);
      }
    }

    // Cerrar popup al hacer clic en el fondo
    document.addEventListener('click', function(e) {
      if (e.target.classList.contains('error-overlay')) {
        cerrarPopup();
      }
    });

    // Cerrar popup con la tecla Escape
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        cerrarPopup();
      }
    });

    // Mostrar popup si hay un mensaje de acceso denegado
    <?php if (isset($_SESSION['acceso_denegado'])): ?>
      mostrarErrorPopup("<?php echo addslashes($_SESSION['acceso_denegado']); ?>");
      <?php unset($_SESSION['acceso_denegado']); ?>
    <?php endif; ?>
  </script>
</body>

</html>