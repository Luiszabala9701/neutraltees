<?php

/**
 * Modulo: confirmacion de pedido.
 * Responsabilidad: mostrar una confirmacion simple luego del checkout.
 */

require_once __DIR__ . '/config/conexion_DB.php';
$conexion = obtener_conexion_db();
requiere_login();

$idPedido = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($idPedido > 0) {
    $_SESSION['compra_confirmada_reciente'] = $idPedido;
}

require_once __DIR__ . '/includes/cabecera_publica.php';
?>

<section class="tarjeta-formulario tarjeta-formulario--centrada">
    <h1 class="tarjeta-formulario__titulo">Pedido realizado</h1>
    <p class="tarjeta-formulario__texto">Tu compra fue registrada correctamente.</p>
    <?php if ($idPedido > 0): ?>
        <p class="tarjeta-formulario__texto">Número de pedido: #<?php echo $idPedido; ?></p>
    <?php endif; ?>
    <a class="boton-principal" href="/index.php">Volver a la tienda</a>
</section>

<?php require_once __DIR__ . '/includes/pie_publico.php'; ?>
