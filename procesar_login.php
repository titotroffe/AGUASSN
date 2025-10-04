<?php
// procesar_login.php
session_start();
require_once 'config.php';

// Conectar a la base de datos
$pdo = conectarDB();
if (!$pdo) {
    $_SESSION['error_login'] = "Error al conectar con la base de datos";
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario']);
    $password = trim($_POST['password']);

    if (empty($usuario) || empty($password)) {
        $_SESSION['error_login'] = "Por favor, complete todos los campos";
        header("Location: login.php");
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT id, usuario, password, rol, nombre FROM usuarios WHERE usuario = ?");
        $stmt->execute([$usuario]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $password === $user['password']) {
            // Login exitoso
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['usuario'] = $user['usuario'];
            $_SESSION['rol'] = $user['rol'];
            $_SESSION['nombre'] = $user['nombre'];
            $_SESSION['loggedin'] = true;

            header("Location: index.php");
            exit();
        } else {
            $_SESSION['error_login'] = "Usuario o contraseña incorrectos";
            header("Location: login.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error_login'] = "Error del sistema. Intente nuevamente.";
        header("Location: login.php");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
