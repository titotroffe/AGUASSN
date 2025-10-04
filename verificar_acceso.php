<?php
// verificar_acceso.php
require_once 'config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

$seccion = $_GET['seccion'] ?? '';
$rol_usuario = $_SESSION['rol'];


$paginas = [
    'jefatura' => './modulos/jefatura.php',
    'operador' => './modulos/operador.php',
    'quimico' => './modulos/quimico.php',
    'mantenimiento' => './modulos/mantenimiento.php'
];

if (!array_key_exists($seccion, $paginas)) {
    $_SESSION['acceso_denegado'] = "Sección no válida";
    header("Location: index.php");
    exit();
}
if ($rol_usuario === 'jefatura') {
    header("Location: " . $paginas[$seccion]);
    exit();
} else if ($rol_usuario === $seccion) {
    header("Location: " . $paginas[$seccion]);
    exit();
} else {
    $_SESSION['acceso_denegado'] = "Acceso denegado. No tiene permisos para acceder a esta sección.";
    header("Location: index.php");
    exit();
}
