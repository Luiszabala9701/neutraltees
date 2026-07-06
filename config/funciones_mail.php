<?php

/**
 * Modulo: envio de correos.
 * Responsabilidad: configurar PHPMailer y construir mensajes transaccionales
 * para verificacion, recuperacion, compras y cambios de estado.
 */

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/funciones.php';

/**
 * Carga la configuracion SMTP local; si falta, avisa como prepararla.
 */
function obtener_configuracion_mail(): array
{
    $rutaConfiguracion = __DIR__ . '/mail.php';

    if (!file_exists($rutaConfiguracion)) {
        throw new RuntimeException('No existe config/mail.php. Copia config/mail.ejemplo.php y completa tus datos SMTP.');
    }

    return require $rutaConfiguracion;
}

/**
 * Envia un email HTML con version de texto usando los datos SMTP configurados.
 */
function enviar_mail(string $destinoEmail, string $destinoNombre, string $asunto, string $contenidoHtml, string $contenidoTexto = ''): bool
{
    $configuracion = obtener_configuracion_mail();
    $mail = new PHPMailer(true);

    try {
        /* PHPMailer se conecta al servidor SMTP configurado.
           En local usamos Gmail; en Hostinger solo se cambian estos datos. */
        $mail->isSMTP();
        $mail->Host = (string) $configuracion['host'];
        $mail->SMTPAuth = true;
        $mail->Username = (string) $configuracion['usuario'];
        $mail->Password = (string) $configuracion['contrasena'];
        $mail->Port = (int) $configuracion['puerto'];
        $mail->CharSet = 'UTF-8';

        if (($configuracion['seguridad'] ?? '') === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->setFrom((string) $configuracion['remitente_email'], (string) $configuracion['remitente_nombre']);
        $mail->addAddress($destinoEmail, $destinoNombre);
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body = $contenidoHtml;
        $mail->AltBody = $contenidoTexto !== '' ? $contenidoTexto : strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $contenidoHtml));

        return $mail->send();
    } catch (Exception $ex) {
        registrar_error_sistema('Error al enviar email', $mail->ErrorInfo);
        return false;
    }
}

/**
 * Envia el codigo de 6 numeros para activar una cuenta nueva.
 */
function enviar_mail_verificacion(string $destinoEmail, string $destinoNombre, string $codigo): bool
{
    $html = '
        <h1>Verifica tu correo</h1>
        <p>Hola ' . sanear_texto($destinoNombre) . ', gracias por crear tu cuenta en NeutralTees.</p>
        <p>Para activar tu cuenta, ingresa este codigo en la pantalla de verificacion:</p>
        <p style="font-size: 28px; font-weight: bold; letter-spacing: 6px;">' . sanear_texto($codigo) . '</p>
        <p>Este codigo vence en 2 minutos.</p>
    ';

    return enviar_mail(
        $destinoEmail,
        $destinoNombre,
        'Codigo de verificacion - NeutralTees',
        $html,
        'Tu codigo de verificacion es: ' . $codigo . '. Vence en 2 minutos.'
    );
}

/**
 * Envia el codigo temporal para recuperar o cambiar la contrasena por mail.
 */
function enviar_mail_recuperacion_contrasena(string $destinoEmail, string $destinoNombre, string $codigo): bool
{
    $html = '
        <h1>Restablecer contrasena</h1>
        <p>Hola ' . sanear_texto($destinoNombre) . ', recibimos una solicitud para cambiar tu contrasena.</p>
        <p>Ingresa este codigo para crear una nueva contrasena:</p>
        <p style="font-size: 28px; font-weight: bold; letter-spacing: 6px;">' . sanear_texto($codigo) . '</p>
        <p>Este codigo vence en 2 minutos. Si no pediste este cambio, ignora este mensaje.</p>
    ';

    return enviar_mail(
        $destinoEmail,
        $destinoNombre,
        'Codigo para restablecer contrasena - NeutralTees',
        $html,
        'Tu codigo para restablecer la contrasena es: ' . $codigo . '. Vence en 2 minutos.'
    );
}

/**
 * Avisa al usuario cuando su cuenta queda dada de baja.
 */
function enviar_mail_cuenta_dada_baja(string $destinoEmail, string $destinoNombre): bool
{
    $html = '
        <h1>Cuenta dada de baja</h1>
        <p>Hola ' . sanear_texto($destinoNombre) . ', te confirmamos que tu cuenta de NeutralTees fue dada de baja.</p>
        <p>Desde este momento no vas a poder ingresar con ese correo.</p>
        <p>Si crees que esto fue un error, podes contactarnos para revisar tu caso.</p>
    ';

    return enviar_mail(
        $destinoEmail,
        $destinoNombre,
        'Cuenta dada de baja - NeutralTees',
        $html,
        'Tu cuenta de NeutralTees fue dada de baja. Si crees que fue un error, contactanos.'
    );
}

/**
 * Avisa al usuario que su contrasena fue modificada correctamente.
 */
function enviar_mail_contrasena_actualizada(string $destinoEmail, string $destinoNombre): bool
{
    $html = '
        <h1>Contrasena actualizada</h1>
        <p>Hola ' . sanear_texto($destinoNombre) . ', te confirmamos que la contrasena de tu cuenta NeutralTees fue actualizada correctamente.</p>
        <p>Si no realizaste este cambio, contactanos cuanto antes.</p>
    ';

    return enviar_mail(
        $destinoEmail,
        $destinoNombre,
        'Contrasena actualizada - NeutralTees',
        $html,
        'La contrasena de tu cuenta NeutralTees fue actualizada correctamente. Si no realizaste este cambio, contactanos.'
    );
}

/**
 * Junta cabecera, cliente y lineas de un pedido para armar emails.
 */
function obtener_pedido_para_mail(PDO $conexion, int $idPedido): ?array
{
    $sentenciaPedido = $conexion->prepare(
        'SELECT p.*, u.nombre, u.apellido, u.mail
         FROM pedido p
         INNER JOIN usuario u ON u.id_usuario = p.id_usuario
         WHERE p.id_pedido = :id_pedido
         LIMIT 1'
    );
    $sentenciaPedido->execute([':id_pedido' => $idPedido]);
    $pedido = $sentenciaPedido->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        return null;
    }

    $pedido['detalles'] = obtener_detalles_pedido($conexion, $idPedido);

    return $pedido;
}

/**
 * Renderiza las lineas del pedido como tabla HTML compatible con clientes de correo.
 */
function construir_tabla_productos_mail(array $detalles): string
{
    $filas = '';

    foreach ($detalles as $detalle) {
        $filas .= '
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #e5e7eb;">
                    <strong>' . sanear_texto((string) $detalle['nombre_producto']) . '</strong><br>
                    <span style="color: #64748b;">Talle: ' . sanear_texto((string) ($detalle['talle'] ?? 'N/A')) . ' - SKU: ' . sanear_texto((string) ($detalle['sku'] ?? 'N/A')) . '</span>
                </td>
                <td style="padding: 10px; border-bottom: 1px solid #e5e7eb; text-align: center;">' . (int) $detalle['cantidad'] . '</td>
                <td style="padding: 10px; border-bottom: 1px solid #e5e7eb; text-align: right;">' . formatear_precio((float) $detalle['precio_unitario']) . '</td>
                <td style="padding: 10px; border-bottom: 1px solid #e5e7eb; text-align: right;"><strong>' . formatear_precio((float) $detalle['subtotal_linea']) . '</strong></td>
            </tr>
        ';
    }

    return '
        <table style="width: 100%; border-collapse: collapse; margin-top: 14px;">
            <thead>
                <tr>
                    <th style="padding: 10px; border-bottom: 2px solid #cbd5e1; text-align: left;">Producto</th>
                    <th style="padding: 10px; border-bottom: 2px solid #cbd5e1; text-align: center;">Cant.</th>
                    <th style="padding: 10px; border-bottom: 2px solid #cbd5e1; text-align: right;">Precio</th>
                    <th style="padding: 10px; border-bottom: 2px solid #cbd5e1; text-align: right;">Subtotal</th>
                </tr>
            </thead>
            <tbody>' . $filas . '</tbody>
        </table>
    ';
}

/**
 * Notifica al cliente todos los datos de su compra recien confirmada.
 */
function enviar_mail_pedido_creado(PDO $conexion, int $idPedido): bool
{
    $pedido = obtener_pedido_para_mail($conexion, $idPedido);

    if (!$pedido) {
        registrar_error_sistema('No se pudo enviar mail de pedido creado', 'Pedido inexistente #' . $idPedido);
        return false;
    }

    $nombreCliente = trim((string) $pedido['nombre'] . ' ' . (string) $pedido['apellido']);
    $tablaProductos = construir_tabla_productos_mail($pedido['detalles']);
    $descuento = (float) ($pedido['descuento'] ?? 0);

    $html = '
        <h1>Compra confirmada #' . (int) $pedido['id_pedido'] . '</h1>
        <p>Hola ' . sanear_texto($nombreCliente) . ', recibimos tu compra en NeutralTees.</p>
        <h2>Resumen de tu compra</h2>
        <p>
            <strong>Numero de compra:</strong> #' . (int) $pedido['id_pedido'] . '<br>
            <strong>Fecha:</strong> ' . date('d/m/Y H:i', strtotime((string) $pedido['fecha'])) . '<br>
            <strong>Estado del pedido:</strong> ' . sanear_texto(nombre_estado_pedido((string) $pedido['estado_pedido'])) . '<br>
            <strong>Estado del pago:</strong> ' . sanear_texto(nombre_estado_pago((string) $pedido['estado_pago'])) . '<br>
            <strong>Metodo de pago:</strong> ' . sanear_texto(nombre_metodo_pago($pedido['metodo_pago'] ?? null)) . '<br>
            <strong>DNI:</strong> ' . sanear_texto((string) ($pedido['dni_cliente'] ?? 'No informado')) . '<br>
            <strong>Telefono:</strong> ' . sanear_texto((string) ($pedido['telefono_cliente'] ?? 'No informado')) . '
        </p>
        <h2>Productos</h2>
        ' . $tablaProductos . '
        <p style="margin-top: 18px;">
            <strong>Subtotal:</strong> ' . formatear_precio((float) $pedido['subtotal']) . '<br>
            <strong>Descuento:</strong> -' . formatear_precio($descuento) . '<br>
            <strong style="font-size: 20px;">Total:</strong> <strong style="font-size: 20px;">' . formatear_precio((float) $pedido['total']) . '</strong>
        </p>
    ';

    return enviar_mail(
        (string) $pedido['mail'],
        $nombreCliente,
        'Confirmacion de compra #' . (int) $pedido['id_pedido'] . ' - NeutralTees',
        $html,
        'Compra confirmada #' . (int) $pedido['id_pedido'] . '. Total: ' . formatear_precio((float) $pedido['total'])
    );
}

/**
 * Avisa al cliente cuando el pedido avanza de estado.
 */
function enviar_mail_estado_pedido(PDO $conexion, int $idPedido, string $estadoAnterior, string $estadoNuevo): bool
{
    $pedido = obtener_pedido_para_mail($conexion, $idPedido);

    if (!$pedido) {
        registrar_error_sistema('No se pudo enviar mail de cambio de estado', 'Pedido inexistente #' . $idPedido);
        return false;
    }

    $nombreCliente = trim((string) $pedido['nombre'] . ' ' . (string) $pedido['apellido']);
    $estadoAnteriorNombre = nombre_estado_pedido($estadoAnterior);
    $estadoNuevoNombre = nombre_estado_pedido($estadoNuevo);

    $html = '
        <h1>Novedades sobre tu pedido #' . (int) $pedido['id_pedido'] . '</h1>
        <p>Hola ' . sanear_texto($nombreCliente) . ', tenemos una actualizacion sobre tu compra.</p>
        <p>
            <strong>Ahora esta:</strong> ' . sanear_texto($estadoNuevoNombre) . '<br>
            <strong>Total:</strong> ' . formatear_precio((float) $pedido['total']) . '
        </p>
        <p>Numero de compra: <strong>#' . (int) $pedido['id_pedido'] . '</strong></p>
    ';

    return enviar_mail(
        (string) $pedido['mail'],
        $nombreCliente,
        'Pedido #' . (int) $pedido['id_pedido'] . ': ' . $estadoNuevoNombre . ' - NeutralTees',
        $html,
        'Tu pedido #' . (int) $pedido['id_pedido'] . ' ahora esta ' . $estadoNuevoNombre . '.'
    );
}

/**
 * Avisa al cliente cuando su pedido fue cancelado por el cliente o por administracion.
 */
function enviar_mail_pedido_cancelado(PDO $conexion, int $idPedido, string $textoActor): bool
{
    $pedido = obtener_pedido_para_mail($conexion, $idPedido);

    if (!$pedido) {
        registrar_error_sistema('No se pudo enviar mail de pedido cancelado', 'Pedido inexistente #' . $idPedido);
        return false;
    }

    $nombreCliente = trim((string) $pedido['nombre'] . ' ' . (string) $pedido['apellido']);
    $tablaProductos = construir_tabla_productos_mail($pedido['detalles']);

    $html = '
        <h1>Pedido #' . (int) $pedido['id_pedido'] . ' cancelado</h1>
        <p>Hola ' . sanear_texto($nombreCliente) . ', te informamos que tu pedido fue cancelado.</p>
        <p>
            <strong>Cancelacion:</strong> ' . sanear_texto($textoActor) . '<br>
            <strong>Numero de compra:</strong> #' . (int) $pedido['id_pedido'] . '<br>
            <strong>Fecha de compra:</strong> ' . date('d/m/Y H:i', strtotime((string) $pedido['fecha'])) . '<br>
            <strong>Total:</strong> ' . formatear_precio((float) $pedido['total']) . '
        </p>
        <h2>Productos cancelados</h2>
        ' . $tablaProductos . '
        <p>Si tenes dudas sobre esta cancelacion, podes responder este correo o contactarnos desde la tienda.</p>
    ';

    return enviar_mail(
        (string) $pedido['mail'],
        $nombreCliente,
        'Pedido #' . (int) $pedido['id_pedido'] . ' cancelado - NeutralTees',
        $html,
        'Tu pedido #' . (int) $pedido['id_pedido'] . ' fue cancelado. ' . $textoActor . '.'
    );
}

/**
 * Avisa al cliente cuando cambia el estado del pago.
 */
function enviar_mail_estado_pago(PDO $conexion, int $idPedido, string $estadoAnterior, string $estadoNuevo): bool
{
    $pedido = obtener_pedido_para_mail($conexion, $idPedido);

    if (!$pedido) {
        registrar_error_sistema('No se pudo enviar mail de cambio de pago', 'Pedido inexistente #' . $idPedido);
        return false;
    }

    $nombreCliente = trim((string) $pedido['nombre'] . ' ' . (string) $pedido['apellido']);
    $estadoAnteriorNombre = nombre_estado_pago($estadoAnterior);
    $estadoNuevoNombre = nombre_estado_pago($estadoNuevo);

    $html = '
        <h1>Novedades sobre el pago de tu pedido #' . (int) $pedido['id_pedido'] . '</h1>
        <p>Hola ' . sanear_texto($nombreCliente) . ', tenemos una actualizacion sobre el pago de tu compra.</p>
        <p>
            <strong>Pago:</strong> ' . sanear_texto($estadoNuevoNombre) . '<br>
            <strong>Total:</strong> ' . formatear_precio((float) $pedido['total']) . '
        </p>
    ';

    return enviar_mail(
        (string) $pedido['mail'],
        $nombreCliente,
        'Pago del pedido #' . (int) $pedido['id_pedido'] . ': ' . $estadoNuevoNombre . ' - NeutralTees',
        $html,
        'El pago de tu pedido #' . (int) $pedido['id_pedido'] . ' ahora esta ' . $estadoNuevoNombre . '.'
    );
}
