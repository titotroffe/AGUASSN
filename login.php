<?php

// login.php - Página de login con manejo de errores
session_start();

// Si ya está logueado, redirigir al index
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>AGUAS DE SAN NICOLAS - Login</title>
    <link rel="stylesheet" href="./styles/login.css" />
    <link rel="shortcut icon" href="./img/favicon.ico" type="image/x-icon">
</head>

<body>
    <section>
        <div class="contenedor-logo">
            <img class="logo" src="./img/logo.svg" alt="" />
        </div>
        <form class="form-login" action="procesar_login.php" method="POST">
            <input type="text" name="usuario" placeholder="Usuario" required />
            <input type="password" name="password" placeholder="Contraseña" required />
            <button class="btn-login" type="submit">Confirmar</button>
        </form>

        <?php
        if (isset($_SESSION['error_login'])) {
            echo "<div class='error-message'>" . htmlspecialchars($_SESSION['error_login']) . "</div>";
            unset($_SESSION['error_login']);
        }
        ?>
    </section>
</body>

</html>