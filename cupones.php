<?php

/**
 * Modulo: cupones del cliente.
 * Responsabilidad: permitir cargar codigos y listar cupones asociados al usuario.
 */

require_once __DIR__ . '/config/conexion_DB.php';
$conexion = obtener_conexion_db();
requiere_login();

$usuario = usuario_actual();
$idUsuario = (int) $usuario['id_usuario'];
$erroresCupon = [];

if (!isset($_SESSION['cupones_usuario'][$idUsuario])) {
    $_SESSION['cupones_usuario'][$idUsuario] = [];
}

if (es_post()) {
    $accion = limpiar_entrada((string) ($_POST['accion'] ?? ''));

    if ($accion === 'agregar_cupon') {
        $codigo = strtoupper(limpiar_entrada((string) ($_POST['codigo_cupon'] ?? '')));

        if ($codigo === '') {
            $erroresCupon[] = 'Ingresá el código del cupón.';
        } else {
            $sentenciaCupon = $conexion->prepare('SELECT * FROM cupon WHERE codigo = :codigo LIMIT 1');
            $sentenciaCupon->execute([':codigo' => $codigo]);
            $cupon = $sentenciaCupon->fetch(PDO::FETCH_ASSOC);

            if (!$cupon) {
                $erroresCupon[] = 'No encontramos un cupón con ese código.';
            } else {
                $validacion = validar_cupon_para_usuario($conexion, $cupon, $idUsuario, 0.0, false);

                if (!$validacion['ok']) {
                    $erroresCupon[] = $validacion['mensaje'];
                } elseif (isset($_SESSION['cupones_usuario'][$idUsuario][(int) $cupon['id_cupon']])) {
                    $erroresCupon[] = 'Ese cupón ya está en tu lista.';
                } else {
                    $_SESSION['cupones_usuario'][$idUsuario][(int) $cupon['id_cupon']] = date('Y-m-d H:i:s');
                    guardar_flash('mensaje_exito', 'Cupón agregado.');
                    redirigir('/cupones.php');
                }
            }
        }
    }

    if ($accion === 'quitar_cupon') {
        $idCupon = (int) ($_POST['id_cupon'] ?? 0);
        unset($_SESSION['cupones_usuario'][$idUsuario][$idCupon]);
        guardar_flash('mensaje_exito', 'Cupón quitado.');
        redirigir('/cupones.php');
    }
}

$cuponesIngresados = obtener_cupones_ingresados_usuario($conexion, $idUsuario);

require_once __DIR__ . '/includes/cabecera_publica.php';
?>

<section class="tarjeta-formulario tarjeta-formulario--perfil">
    <h1 class="tarjeta-formulario__titulo">Mis cupones</h1>
    <p class="tarjeta-formulario__texto">Ingresá tus códigos y tenelos listos para usarlos en el checkout.</p>

    <?php foreach ($erroresCupon as $error): ?>
        <div class="mensaje-vacio mensaje-vacio--error u-mb-18"><?php echo sanear_texto($error); ?></div>
    <?php endforeach; ?>

    <form class="cupon-cliente-formulario" method="post">
        <input type="hidden" name="accion" value="agregar_cupon">
        <div class="grupo-campo">
            <label for="codigo_cupon">Código de cupón</label>
            <input class="campo-texto campo-texto--mayusculas" id="codigo_cupon" type="text" name="codigo_cupon" maxlength="50" required placeholder="Ej: BIENVENIDA10" data-mayusculas>
        </div>
        <button class="boton-principal" type="submit">Agregar cupón</button>
    </form>

    <div class="u-mt-24">
        <h2 class="tarjeta-formulario__titulo">Cupones ingresados</h2>

        <?php if ($cuponesIngresados === []): ?>
            <div class="mensaje-vacio">Todavía no ingresaste cupones.</div>
        <?php else: ?>
            <div class="lista-cupones-cliente">
                <?php foreach ($cuponesIngresados as $cupon): ?>
                    <?php
                    $validacion = validar_cupon_para_usuario($conexion, $cupon, $idUsuario, 0.0, false);
                    $valorCupon = (string) $cupon['tipo_descuento'] === 'porcentaje'
                        ? number_format((float) $cupon['valor'], 0, ',', '.') . '%'
                        : formatear_precio((float) $cupon['valor']);
                    ?>
                    <article class="cupon-cliente">
                        <div>
                            <h3 class="cupon-cliente__codigo"><?php echo sanear_texto(strtoupper((string) $cupon['codigo'])); ?></h3>
                            <p class="pedido__detalle"><?php echo sanear_texto((string) ($cupon['descripcion'] ?? 'Cupón de descuento')); ?></p>
                        </div>
                        <div class="cupon-datos">
                            <span class="etiqueta etiqueta--azul"><?php echo (string) $cupon['tipo_descuento'] === 'porcentaje' ? 'Descuento' : 'Monto'; ?>: <?php echo sanear_texto($valorCupon); ?></span>
                            <span class="etiqueta etiqueta--gris">Compra mínima: <?php echo formatear_precio((float) $cupon['compra_minima']); ?></span>
                            <span class="etiqueta <?php echo $validacion['ok'] ? 'etiqueta--verde' : 'etiqueta--amarillo'; ?>"><?php echo sanear_texto($validacion['mensaje']); ?></span>
                        </div>
                        <form method="post" data-confirmar="¿Querés quitar este cupón de tu lista?">
                            <input type="hidden" name="accion" value="quitar_cupon">
                            <input type="hidden" name="id_cupon" value="<?php echo (int) $cupon['id_cupon']; ?>">
                            <button class="boton-terciario" type="submit">Quitar</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/pie_publico.php'; ?>
