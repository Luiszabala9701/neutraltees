<?php

/**
 * Modulo: checkout.
 * Responsabilidad: validar datos del cliente, stock, cupones y crear el pedido.
 */

require_once __DIR__ . '/config/conexion_DB.php';
require_once __DIR__ . '/config/funciones_mail.php';
$conexion = obtener_conexion_db();
requiere_login();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (isset($_GET['estado'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'bloqueado' => !empty($_SESSION['compra_confirmada_reciente']),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$usuario = usuario_actual();
$detallesCarrito = obtener_detalles_carrito($conexion);

if ($detallesCarrito === []) {
    guardar_flash('mensaje_error', 'Tu carrito está vacío.');
    redirigir('/carrito.php');
}

$totalCarrito = 0.0;
foreach ($detallesCarrito as $detalle) {
    $totalCarrito += (float) $detalle['subtotal'];
}

// Estado del formulario: conserva datos si alguna validacion falla.
$erroresCheckout = [];
$datosCheckout = [
    'dni_cliente' => '',
    'telefono_cliente' => (string) ($usuario['telefono'] ?? ''),
    'metodo_pago' => '',
    'id_cupon' => '0',
];
$cuponesIngresadosCheckout = obtener_cupones_ingresados_usuario($conexion, (int) $usuario['id_usuario']);

function describir_beneficio_cupon_checkout(array $cupon): string
{
    if ((string) $cupon['tipo_descuento'] === 'porcentaje') {
        $beneficio = rtrim(rtrim(number_format((float) $cupon['valor'], 2, ',', '.'), '0'), ',') . '% OFF';

        if (!empty($cupon['tope_descuento']) && (float) $cupon['tope_descuento'] > 0) {
            $beneficio .= ', tope ' . formatear_precio((float) $cupon['tope_descuento']);
        }

        return $beneficio;
    }

    return formatear_precio((float) $cupon['valor']) . ' OFF';
}

if (es_post() && ($_POST['accion_checkout'] ?? '') === 'agregar_cupon') {
    header('Content-Type: application/json; charset=utf-8');

    $codigoCuponCheckout = strtoupper(limpiar_entrada((string) ($_POST['codigo_cupon_checkout'] ?? '')));
    $idUsuarioCheckout = (int) $usuario['id_usuario'];
    $respuestaCupon = [
        'ok' => false,
        'mensaje' => '',
    ];

    if (!isset($_SESSION['cupones_usuario'][$idUsuarioCheckout])) {
        $_SESSION['cupones_usuario'][$idUsuarioCheckout] = [];
    }

    if ($codigoCuponCheckout === '') {
        $respuestaCupon['mensaje'] = 'Ingresa el codigo del cupon.';
    } else {
        $sentenciaCuponCheckout = $conexion->prepare('SELECT * FROM cupon WHERE codigo = :codigo LIMIT 1');
        $sentenciaCuponCheckout->execute([':codigo' => $codigoCuponCheckout]);
        $cuponCheckout = $sentenciaCuponCheckout->fetch(PDO::FETCH_ASSOC);

        if (!$cuponCheckout) {
            $respuestaCupon['mensaje'] = 'El cupon es incorrecto.';
        } else {
            $validacionCuponCheckout = validar_cupon_para_usuario($conexion, $cuponCheckout, $idUsuarioCheckout, $totalCarrito);

            if (!$validacionCuponCheckout['ok']) {
                $respuestaCupon['mensaje'] = $validacionCuponCheckout['mensaje'];
            } else {
                $_SESSION['cupones_usuario'][$idUsuarioCheckout][(int) $cuponCheckout['id_cupon']] = date('Y-m-d H:i:s');
                $descuentoCuponCheckout = calcular_descuento_cupon($cuponCheckout, $totalCarrito);
                $respuestaCupon = [
                    'ok' => true,
                    'mensaje' => 'Cupon aplicado.',
                    'id_cupon' => (int) $cuponCheckout['id_cupon'],
                    'codigo' => (string) $cuponCheckout['codigo'],
                    'texto' => (string) $cuponCheckout['codigo'] . ' - ' . describir_beneficio_cupon_checkout($cuponCheckout),
                    'descuento' => $descuentoCuponCheckout,
                    'total' => max(0.0, $totalCarrito - $descuentoCuponCheckout),
                ];
            }
        }
    }

    echo json_encode($respuestaCupon, JSON_UNESCAPED_UNICODE);
    exit;
}

if (es_post()) {
    $datosCheckout['dni_cliente'] = limpiar_entrada((string) ($_POST['dni_cliente'] ?? ''));
    $datosCheckout['telefono_cliente'] = limpiar_entrada((string) ($_POST['telefono_cliente'] ?? ''));
    $datosCheckout['metodo_pago'] = limpiar_entrada((string) ($_POST['metodo_pago'] ?? ''));
    $datosCheckout['id_cupon'] = limpiar_entrada((string) ($_POST['id_cupon'] ?? '0'));
    $metodosPagoPermitidos = ['efectivo', 'mercado_pago'];

    if ($datosCheckout['dni_cliente'] === '') {
        $erroresCheckout[] = 'Completá tu DNI.';
    } elseif (!ctype_digit($datosCheckout['dni_cliente'])) {
        $erroresCheckout[] = 'El DNI debe tener solo numeros.';
    }

    if ($datosCheckout['telefono_cliente'] === '') {
        $erroresCheckout[] = 'Completá tu número de teléfono.';
    } elseif (!ctype_digit($datosCheckout['telefono_cliente'])) {
        $erroresCheckout[] = 'El telefono debe tener solo numeros.';
    }

    if (!in_array($datosCheckout['metodo_pago'], $metodosPagoPermitidos, true)) {
        $erroresCheckout[] = 'Seleccioná un método de pago.';
    }

    if ($erroresCheckout === []) {
        try {
            $conexion->beginTransaction();

            // Revalidamos stock y limite por producto justo antes de crear el pedido.
            $subtotal = 0.0;
            $descuento = 0.0;
            $cuponAplicado = null;
            $unidadesPorProducto = [];

            foreach ($detallesCarrito as $detalle) {
                $variante = obtener_variante_por_id($conexion, (int) $detalle['id_variante']);

                if (!$variante || $variante['estado'] !== 'activo') {
                    throw new RuntimeException('Una de las variantes del carrito ya no está disponible.');
                }

                if ((int) $variante['stock'] < (int) $detalle['cantidad']) {
                    throw new RuntimeException('Stock insuficiente para ' . $variante['talle'] . ' de ' . $variante['nombre_producto'] . '.');
                }

                $subtotal += (float) $detalle['subtotal'];
                $idProductoDetalle = (int) $detalle['id_producto'];
                $unidadesPorProducto[$idProductoDetalle] = ($unidadesPorProducto[$idProductoDetalle] ?? 0) + (int) $detalle['cantidad'];

                if ($unidadesPorProducto[$idProductoDetalle] > limite_unidades_por_producto()) {
                    throw new RuntimeException('Solo podés comprar hasta ' . limite_unidades_por_producto() . ' unidades por producto.');
                }
            }

            // El cupon solo se aplica si el usuario lo habia cargado previamente.
            $idCuponSeleccionado = (int) $datosCheckout['id_cupon'];

            if ($idCuponSeleccionado > 0) {
                $idsCuponesIngresados = array_map('intval', array_column($cuponesIngresadosCheckout, 'id_cupon'));

                if (!in_array($idCuponSeleccionado, $idsCuponesIngresados, true)) {
                    throw new RuntimeException('Ese cupón no está en tu lista de cupones ingresados.');
                }

                foreach ($cuponesIngresadosCheckout as $cuponIngresado) {
                    if ((int) $cuponIngresado['id_cupon'] === $idCuponSeleccionado) {
                        $cuponAplicado = $cuponIngresado;
                        break;
                    }
                }

                if (!$cuponAplicado) {
                    throw new RuntimeException('No pudimos aplicar el cupón seleccionado.');
                }

                $validacionCupon = validar_cupon_para_usuario($conexion, $cuponAplicado, (int) $usuario['id_usuario'], $subtotal);

                if (!$validacionCupon['ok']) {
                    throw new RuntimeException($validacionCupon['mensaje']);
                }

                $descuento = calcular_descuento_cupon($cuponAplicado, $subtotal);
            }

            $total = max(0.0, $subtotal - $descuento);
            $estadoPagoInicial = $datosCheckout['metodo_pago'] === 'efectivo' ? 'pendiente' : 'pagado';

            // La cabecera del pedido guarda datos del cliente, pago, fecha y totales.
            $sentenciaPedido = $conexion->prepare(
                'INSERT INTO pedido
                    (id_usuario, dni_cliente, telefono_cliente, metodo_pago, fecha, subtotal, descuento, total, estado_pedido, estado_pago, fecha_actualizacion)
                 VALUES
                    (:id_usuario, :dni_cliente, :telefono_cliente, :metodo_pago, NOW(), :subtotal, :descuento, :total, :estado_pedido, :estado_pago, NOW())'
            );
            $sentenciaPedido->execute([
                ':id_usuario' => (int) $usuario['id_usuario'],
                ':dni_cliente' => $datosCheckout['dni_cliente'],
                ':telefono_cliente' => $datosCheckout['telefono_cliente'],
                ':metodo_pago' => $datosCheckout['metodo_pago'],
                ':subtotal' => $subtotal,
                ':total' => $total,
                ':descuento' => $descuento,
                ':estado_pedido' => 'pendiente',
                ':estado_pago' => $estadoPagoInicial,
            ]);

            $idPedido = (int) $conexion->lastInsertId();

            // Cada linea del carrito se persiste y descuenta stock de su variante.
            foreach ($detallesCarrito as $detalle) {
                $subtotalLinea = (float) $detalle['subtotal'];

                $sentenciaDetalle = $conexion->prepare(
                    'INSERT INTO detalle_pedido (id_pedido, id_variante, cantidad, precio_unitario, subtotal_linea)
                     VALUES (:id_pedido, :id_variante, :cantidad, :precio_unitario, :subtotal_linea)'
                );
                $sentenciaDetalle->execute([
                    ':id_pedido' => $idPedido,
                    ':id_variante' => (int) $detalle['id_variante'],
                    ':cantidad' => (int) $detalle['cantidad'],
                    ':precio_unitario' => (float) $detalle['precio'],
                    ':subtotal_linea' => $subtotalLinea,
                ]);

                $sentenciaStock = $conexion->prepare(
                    'UPDATE producto_variante
                     SET stock = stock - :cantidad, fecha_actualizacion = NOW()
                     WHERE id_variante = :id_variante'
                );
                $sentenciaStock->execute([
                    ':cantidad' => (int) $detalle['cantidad'],
                    ':id_variante' => (int) $detalle['id_variante'],
                ]);
            }

            // Registrar el uso evita que el mismo cliente reutilice ese cupon.
            if ($cuponAplicado && $descuento > 0) {
                $sentenciaUsoCupon = $conexion->prepare(
                    'INSERT INTO uso_cupon (id_pedido, id_usuario, id_cupon, monto_descuento, fecha_uso)
                     VALUES (:id_pedido, :id_usuario, :id_cupon, :monto_descuento, NOW())'
                );
                $sentenciaUsoCupon->execute([
                    ':id_pedido' => $idPedido,
                    ':id_usuario' => (int) $usuario['id_usuario'],
                    ':id_cupon' => (int) $cuponAplicado['id_cupon'],
                    ':monto_descuento' => $descuento,
                ]);

                unset($_SESSION['cupones_usuario'][(int) $usuario['id_usuario']][(int) $cuponAplicado['id_cupon']]);
            }

            $conexion->commit();
            enviar_mail_pedido_creado($conexion, $idPedido);
            vaciar_carrito();
            $_SESSION['compra_confirmada_reciente'] = $idPedido;
            guardar_flash('mensaje_exito', 'Pago realizado exitosamente.');
            redirigir('/index.php');
        } catch (Throwable $ex) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }

            registrar_error_sistema('Error al generar pedido', $ex->getMessage());
            $erroresCheckout[] = $ex->getMessage();
        }
    }
}

$descuentoVista = 0.0;
$cuponVista = null;

if ((int) $datosCheckout['id_cupon'] > 0) {
    foreach ($cuponesIngresadosCheckout as $cuponIngresado) {
        if ((int) $cuponIngresado['id_cupon'] === (int) $datosCheckout['id_cupon']) {
            $cuponVista = $cuponIngresado;
            break;
        }
    }

    if ($cuponVista) {
        $validacionVista = validar_cupon_para_usuario($conexion, $cuponVista, (int) $usuario['id_usuario'], $totalCarrito);
        if ($validacionVista['ok']) {
            $descuentoVista = calcular_descuento_cupon($cuponVista, $totalCarrito);
        }
    }
}

$totalConDescuentoVista = max(0.0, $totalCarrito - $descuentoVista);

require_once __DIR__ . '/includes/cabecera_publica.php';
?>

<section class="tarjeta-formulario">
    <h1 class="tarjeta-formulario__titulo">Checkout</h1>
    <p class="tarjeta-formulario__texto">Confirmá tu compra.</p>

    <?php foreach ($erroresCheckout as $error): ?>
        <div class="mensaje-vacio mensaje-vacio--error u-mb-18"><?php echo sanear_texto($error); ?></div>
    <?php endforeach; ?>

    <form method="post" data-formulario-checkout>
        <div class="grupo-campo">
            <label>Comprador</label>
            <input class="campo-texto" value="<?php echo sanear_texto(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellido'] ?? '')); ?>" disabled>
        </div>

        <div class="grupo-campo">
            <label>Email</label>
            <input class="campo-texto" value="<?php echo sanear_texto($usuario['mail'] ?? ''); ?>" disabled>
        </div>

        <div class="rejilla-campos">
            <div class="grupo-campo">
                <label for="dni_cliente">DNI</label>
                <input class="campo-texto" id="dni_cliente" type="text" name="dni_cliente" value="<?php echo sanear_texto($datosCheckout['dni_cliente']); ?>" inputmode="numeric" pattern="[0-9]+" maxlength="10" autocomplete="off" required data-solo-numeros>
            </div>

            <div class="grupo-campo">
                <label for="telefono_cliente">Número de teléfono</label>
                <input class="campo-texto" id="telefono_cliente" type="tel" name="telefono_cliente" value="<?php echo sanear_texto($datosCheckout['telefono_cliente']); ?>" inputmode="numeric" pattern="[0-9]+" maxlength="15" autocomplete="tel" required data-solo-numeros>
            </div>
        </div>

        <div class="grupo-campo">
            <label for="metodo_pago">Método de pago</label>
            <select class="campo-select" id="metodo_pago" name="metodo_pago" required>
                <option value="">Seleccionar método de pago</option>
                <option value="efectivo" <?php echo $datosCheckout['metodo_pago'] === 'efectivo' ? 'selected' : ''; ?>>Efectivo</option>
                <option value="mercado_pago" <?php echo $datosCheckout['metodo_pago'] === 'mercado_pago' ? 'selected' : ''; ?>>Mercado Pago</option>
            </select>
        </div>

        <div class="grupo-campo">
            <label for="id_cupon">Cupón</label>
            <select class="campo-select" id="id_cupon" name="id_cupon">
                <option value="0">Sin cupón</option>
                <?php foreach ($cuponesIngresadosCheckout as $cuponCheckout): ?>
                    <?php $validacionCuponCheckout = validar_cupon_para_usuario($conexion, $cuponCheckout, (int) $usuario['id_usuario'], $totalCarrito); ?>
                    <?php $descuentoCuponCheckout = $validacionCuponCheckout['ok'] ? calcular_descuento_cupon($cuponCheckout, $totalCarrito) : 0.0; ?>
                    <?php $textoCuponCheckout = $validacionCuponCheckout['ok'] ? describir_beneficio_cupon_checkout($cuponCheckout) : $validacionCuponCheckout['mensaje']; ?>
                    <option
                        value="<?php echo (int) $cuponCheckout['id_cupon']; ?>"
                        data-descuento="<?php echo $descuentoCuponCheckout; ?>"
                        <?php echo (int) $datosCheckout['id_cupon'] === (int) $cuponCheckout['id_cupon'] ? 'selected' : ''; ?>
                        <?php echo $validacionCuponCheckout['ok'] ? '' : 'disabled'; ?>
                    >
                        <?php echo sanear_texto($cuponCheckout['codigo'] . ' - ' . $textoCuponCheckout); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <details class="checkout-cupon-rapido">
                <summary class="enlace-texto">
                    <?php echo $cuponesIngresadosCheckout === [] ? 'Agregar cupon' : 'Agregar otro cupon'; ?>
                </summary>
                <div class="checkout-cupon-rapido__contenido">
                    <input class="campo-texto campo-texto--mayusculas" type="text" name="codigo_cupon_checkout" maxlength="50" placeholder="Ej: BIENVENIDA10" data-cupon-checkout-input data-mayusculas>
                    <button class="boton-secundario" type="button" data-agregar-cupon-checkout>Agregar</button>
                </div>
                <span class="campo-error" data-error-cupon-checkout hidden></span>
            </details>
        </div>

        <div class="resumen-carrito resumen-carrito--checkout">
            <div>
                <h2 class="resumen-carrito__titulo">Total del pedido</h2>
                <p class="resumen-carrito__linea">Subtotal: <?php echo formatear_precio($totalCarrito); ?></p>
                <p class="resumen-carrito__linea resumen-carrito__linea--descuento" data-fila-descuento-checkout <?php echo $descuentoVista > 0 ? '' : 'hidden'; ?>>Descuento: -<span data-descuento-checkout><?php echo formatear_precio($descuentoVista); ?></span></p>
            </div>
            <div class="resumen-carrito__bloque">
                <div class="resumen-carrito__importe" data-total-checkout data-subtotal="<?php echo $totalCarrito; ?>"><?php echo formatear_precio($totalConDescuentoVista); ?></div>
                <button class="boton-principal" type="submit" data-boton-confirmar-pago>Confirmar compra</button>
            </div>
        </div>
    </form>
</section>

<div class="modal-pago" data-modal-pago aria-hidden="true">
    <div class="modal-pago__tarjeta" role="dialog" aria-modal="true" aria-live="polite">
        <div class="modal-pago__spinner" data-pago-spinner></div>
        <div class="modal-pago__exito" data-pago-exito hidden>✓</div>
        <h2 class="modal-pago__titulo" data-pago-titulo>Procesando pago...</h2>
        <p class="modal-pago__texto">No cierres esta ventana.</p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var formulario = document.querySelector('[data-formulario-checkout]');
    var modal = document.querySelector('[data-modal-pago]');
    var titulo = document.querySelector('[data-pago-titulo]');
    var spinner = document.querySelector('[data-pago-spinner]');
    var exito = document.querySelector('[data-pago-exito]');
    var boton = document.querySelector('[data-boton-confirmar-pago]');
    var envioHabilitado = false;
    var selectorCupon = document.getElementById('id_cupon');
    var totalCheckout = document.querySelector('[data-total-checkout]');
    var filaDescuento = document.querySelector('[data-fila-descuento-checkout]');
    var textoDescuento = document.querySelector('[data-descuento-checkout]');
    var entradaCuponCheckout = document.querySelector('[data-cupon-checkout-input]');
    var botonAgregarCuponCheckout = document.querySelector('[data-agregar-cupon-checkout]');
    var errorCuponCheckout = document.querySelector('[data-error-cupon-checkout]');

    var formatearPrecioCheckout = function (valor) {
        return '$' + Math.round(valor).toLocaleString('es-AR');
    };

    var actualizarResumenCupon = function (descuento) {
        var subtotal = Number(totalCheckout.getAttribute('data-subtotal') || '0');
        var total = Math.max(0, subtotal - descuento);

        textoDescuento.textContent = formatearPrecioCheckout(descuento);
        totalCheckout.textContent = formatearPrecioCheckout(total);
        filaDescuento.hidden = descuento <= 0;
    };

    if (!formulario || !modal || !titulo || !spinner || !exito || !boton) {
        return;
    }

    if (selectorCupon && totalCheckout && filaDescuento && textoDescuento) {
        selectorCupon.addEventListener('change', function () {
            var opcionSeleccionada = selectorCupon.selectedOptions[0];
            var descuento = Number(opcionSeleccionada ? opcionSeleccionada.getAttribute('data-descuento') || '0' : '0');

            actualizarResumenCupon(descuento);
        });
    }

    if (botonAgregarCuponCheckout && entradaCuponCheckout && selectorCupon && errorCuponCheckout) {
        botonAgregarCuponCheckout.addEventListener('click', function () {
            var codigo = entradaCuponCheckout.value.trim().toUpperCase();

            errorCuponCheckout.hidden = true;
            errorCuponCheckout.textContent = '';

            if (codigo === '') {
                errorCuponCheckout.textContent = 'Ingresa el codigo del cupon.';
                errorCuponCheckout.hidden = false;
                return;
            }

            botonAgregarCuponCheckout.disabled = true;

            var datosCupon = new FormData();
            datosCupon.append('accion_checkout', 'agregar_cupon');
            datosCupon.append('codigo_cupon_checkout', codigo);

            fetch('/checkout.php', {
                method: 'POST',
                body: datosCupon,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (respuesta) { return respuesta.json(); })
                .then(function (datos) {
                    if (!datos || !datos.ok) {
                        errorCuponCheckout.textContent = (datos && datos.mensaje) ? datos.mensaje : 'El cupon es incorrecto.';
                        errorCuponCheckout.hidden = false;
                        return;
                    }

                    var idCupon = String(datos.id_cupon);
                    var opcion = selectorCupon.querySelector('option[value="' + idCupon + '"]');

                    if (!opcion) {
                        opcion = document.createElement('option');
                        opcion.value = idCupon;
                        selectorCupon.appendChild(opcion);
                    }

                    opcion.textContent = datos.texto || (datos.codigo + ' - Cupon disponible.');
                    opcion.disabled = false;
                    opcion.setAttribute('data-descuento', String(datos.descuento || 0));
                    selectorCupon.value = idCupon;
                    entradaCuponCheckout.value = '';
                    actualizarResumenCupon(Number(datos.descuento || 0));
                })
                .catch(function () {
                    errorCuponCheckout.textContent = 'No pudimos validar el cupon. Intenta nuevamente.';
                    errorCuponCheckout.hidden = false;
                })
                .finally(function () {
                    botonAgregarCuponCheckout.disabled = false;
                });
        });
    }

    formulario.addEventListener('submit', function (evento) {
        if (envioHabilitado) {
            return;
        }

        if (!formulario.checkValidity()) {
            return;
        }

        evento.preventDefault();
        boton.disabled = true;
        modal.classList.add('is-visible');
        modal.setAttribute('aria-hidden', 'false');

        window.setTimeout(function () {
            spinner.hidden = true;
            exito.hidden = false;
            titulo.textContent = 'Pago realizado exitosamente';

            window.setTimeout(function () {
                envioHabilitado = true;
                formulario.submit();
            }, 1000);
        }, 3000);
    });
});

document.addEventListener('pageshow', function (event) {
    var navigationEntries = performance.getEntriesByType('navigation');
    var navigationType = navigationEntries.length > 0 ? navigationEntries[0].type : '';

    if (!event.persisted && navigationType !== 'back_forward') {
        return;
    }

    fetch('/checkout.php?estado=1', { cache: 'no-store' })
        .then(function (respuesta) { return respuesta.json(); })
        .then(function (datos) {
            if (datos && datos.bloqueado) {
                window.location.replace('/index.php');
            }
        })
        .catch(function () {});
});
</script>

<?php require_once __DIR__ . '/includes/pie_publico.php'; ?>
