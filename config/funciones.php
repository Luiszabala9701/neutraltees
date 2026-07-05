<?php declare(strict_types=1);

/**
 * Modulo: funciones compartidas.
 * Responsabilidad: centralizar utilidades de sesion, seguridad, carrito,
 * catalogo, pedidos, cupones, stock y codigos temporales.
 */

date_default_timezone_set('America/Argentina/Buenos_Aires');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

verificar_expiracion_sesion_por_inactividad();

/**
 * Guarda errores internos en logs/error.log sin exponer detalles al usuario.
 */
function registrar_error_sistema(string $mensaje, string $detalle = ''): void
{
    $rutaRegistro = __DIR__ . '/../logs/error.log';
    $momento = date('Y-m-d H:i:s');
    $uri = $_SERVER['REQUEST_URI'] ?? 'CLI';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';

    $linea = sprintf(
        "[%s] [%s] [%s] %s %s%s",
        $momento,
        $ip,
        $uri,
        $mensaje,
        $detalle !== '' ? '- ' . $detalle : '',
        PHP_EOL
    );

    @file_put_contents($rutaRegistro, $linea, FILE_APPEND | LOCK_EX);
}

/* Los manejadores globales registran fallos inesperados y muestran un mensaje seguro. */
set_error_handler(function (int $nivel, string $mensaje, string $archivo, int $linea): bool {
    registrar_error_sistema('Error PHP detectado', $mensaje . ' en ' . $archivo . ':' . $linea);
    return false;
});

set_exception_handler(function (Throwable $ex): void {
    registrar_error_sistema('Excepción no controlada', $ex->getMessage() . ' en ' . $ex->getFile() . ':' . $ex->getLine());
    http_response_code(500);
    echo 'Ocurrió un error inesperado. Revisá el registro del sistema.';
});

/**
 * Escapa texto que se va a imprimir en HTML.
 */
function sanear_texto(?string $texto): string
{
    return trim(htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8'));
}

/**
 * Limpia una entrada recibida por formulario sin transformar caracteres HTML.
 */
function limpiar_entrada(?string $texto): string
{
    return trim(strip_tags((string) $texto));
}

/**
 * Indica si la peticion actual llego por POST.
 */
function es_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/**
 * Redirige y corta la ejecucion para evitar salidas parciales.
 */
function redirigir(string $ruta): void
{
    header('Location: ' . $ruta);
    exit;
}

/**
 * Limpia la sesion actual y borra la cookie de PHP.
 * Se usa al cerrar sesion y al desactivar la propia cuenta.
 */
function destruir_sesion_actual(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $parametros = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $parametros['path'],
            $parametros['domain'],
            $parametros['secure'],
            $parametros['httponly']
        );
    }

    session_destroy();
}

/**
 * Devuelve el tiempo maximo de inactividad permitido para una sesion iniciada.
 */
function tiempo_limite_inactividad_sesion(): int
{
    return 600;
}

/**
 * Marca la ultima actividad del usuario logueado.
 */
function registrar_actividad_sesion(): void
{
    if (!empty($_SESSION['usuario_actual'])) {
        $_SESSION['ultima_actividad'] = time();
    }
}

/**
 * Cierra la sesion si supero el tiempo de inactividad configurado.
 */
function verificar_expiracion_sesion_por_inactividad(): void
{
    if (empty($_SESSION['usuario_actual'])) {
        return;
    }

    $limiteSegundos = tiempo_limite_inactividad_sesion();
    $ahora = time();
    $ultimaActividad = (int) ($_SESSION['ultima_actividad'] ?? 0);

    if ($ultimaActividad > 0 && ($ahora - $ultimaActividad) > $limiteSegundos) {
        destruir_sesion_actual();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        guardar_flash('mensaje_error', 'Tu sesión expiró por inactividad. Volvé a iniciar sesión.');
        redirigir('/login.php');
    }

    $_SESSION['ultima_actividad'] = $ahora;
}

/**
 * Lee un mensaje flash de sesion y lo elimina para mostrarlo una sola vez.
 */
function obtener_flash(string $clave): ?string
{
    if (!isset($_SESSION[$clave])) {
        return null;
    }

    $mensaje = (string) $_SESSION[$clave];
    unset($_SESSION[$clave]);
    return $mensaje;
}

/**
 * Guarda mensajes temporales que se imprimen en la siguiente pantalla.
 */
function guardar_flash(string $clave, string $mensaje): void
{
    $_SESSION[$clave] = $mensaje;
}

/**
 * Formatea importes en pesos argentinos para todas las vistas.
 */
function formatear_precio(float $valor): string
{
    return '$' . number_format($valor, 0, ',', '.');
}

/**
 * Devuelve una ruta publica segura para imagenes de producto.
 */
function obtener_ruta_imagen_producto(?string $imagen): string
{
    if ($imagen === null || trim($imagen) === '') {
        return '/assets/img/productos/imagen-placeholder.svg';
    }

    $imagen = str_replace(['"', "'", '<', '>'], '', $imagen);
    return '/' . ltrim($imagen, '/');
}

/**
 * Limite academico de unidades comprables por cada producto.
 */
function limite_unidades_por_producto(): int
{
    return 10;
}

/* Etiquetas, clases y orden de estados usados por panel admin, perfil y emails. */
function nombre_estado_pedido(string $estado): string
{
    $nombres = [
        'pendiente' => 'Pendiente',
        'preparando' => 'En preparacion',
        'preparado' => 'Preparado',
        'enviado' => 'Preparado',
        'entregado' => 'Entregado',
        'cancelado' => 'Cancelado',
    ];

    return $nombres[$estado] ?? ucfirst($estado);
}

function nombre_estado_pago(string $estadoPago): string
{
    $nombres = [
        'pendiente' => 'Pendiente',
        'recibido' => 'Recibido',
        'pagado' => 'Pagado',
    ];

    return $nombres[$estadoPago] ?? ucfirst($estadoPago);
}

function nombre_metodo_pago(?string $metodoPago): string
{
    $nombres = [
        'efectivo' => 'Efectivo',
        'mercado_pago' => 'Mercado Pago',
    ];

    return $nombres[$metodoPago ?? ''] ?? 'No informado';
}

function clase_estado_pedido(string $estado): string
{
    $clases = [
        'pendiente' => 'pedido-chip--pendiente',
        'preparando' => 'pedido-chip--preparando',
        'preparado' => 'pedido-chip--preparado',
        'enviado' => 'pedido-chip--preparado',
        'entregado' => 'pedido-chip--entregado',
        'cancelado' => 'pedido-chip--cancelado',
    ];

    return $clases[$estado] ?? 'pedido-chip--neutro';
}

function clase_estado_pago(string $estadoPago): string
{
    return in_array($estadoPago, ['recibido', 'pagado'], true)
        ? 'pedido-chip--pago-ok'
        : 'pedido-chip--pago-pendiente';
}

function clase_metodo_pago(?string $metodoPago): string
{
    return $metodoPago === 'mercado_pago'
        ? 'pedido-chip--mercado-pago'
        : 'pedido-chip--efectivo';
}

function orden_estado_pedido(string $estado): int
{
    $orden = [
        'pendiente' => 1,
        'preparando' => 2,
        'preparado' => 3,
        'enviado' => 3,
        'entregado' => 4,
    ];

    return $orden[$estado] ?? 0;
}

/**
 * Indica si un pedido todavia puede cancelarse.
 * Los entregados ya estan cerrados y los cancelados no deben repetirse.
 */
function pedido_puede_cancelarse(string $estadoPedido): bool
{
    return !in_array($estadoPedido, ['entregado', 'cancelado'], true);
}

/**
 * Calcula el porcentaje visual de descuento cuando un producto esta en oferta.
 */
function calcular_descuento_porcentaje(float $precioActual, ?float $precioAnterior): ?int
{
    if ($precioAnterior === null || $precioAnterior <= 0 || $precioActual <= 0 || $precioAnterior <= $precioActual) {
        return null;
    }

    return (int) round((1 - ($precioActual / $precioAnterior)) * 100);
}

/**
 * Calcula el monto final que descuenta un cupon sobre un subtotal.
 */
function calcular_descuento_cupon(array $cupon, float $subtotal): float
{
    $descuento = 0.0;

    if ((string) $cupon['tipo_descuento'] === 'porcentaje') {
        $descuento = $subtotal * ((float) $cupon['valor'] / 100);
    } else {
        $descuento = (float) $cupon['valor'];
    }

    if ($cupon['tope_descuento'] !== null) {
        $descuento = min($descuento, (float) $cupon['tope_descuento']);
    }

    return min($subtotal, max(0.0, $descuento));
}

/**
 * Valida si un cupon puede aplicarse para un usuario y una compra concreta.
 */
function validar_cupon_para_usuario(PDO $conexion, array $cupon, int $idUsuario, float $subtotal, bool $validarCompraMinima = true): array
{
    $hoy = date('Y-m-d');

    if ((int) $cupon['activo'] !== 1) {
        return ['ok' => false, 'mensaje' => 'Este cupón ya no está activo.'];
    }

    if (!empty($cupon['fecha_inicio']) && (string) $cupon['fecha_inicio'] > $hoy) {
        return ['ok' => false, 'mensaje' => 'Este cupón todavía no está disponible.'];
    }

    if (!empty($cupon['fecha_fin']) && (string) $cupon['fecha_fin'] < $hoy) {
        return ['ok' => false, 'mensaje' => 'Cupón expirado.'];
    }

    if ($validarCompraMinima && $subtotal < (float) $cupon['compra_minima']) {
        return ['ok' => false, 'mensaje' => 'Requiere una compra mínima de ' . formatear_precio((float) $cupon['compra_minima']) . '.'];
    }

    $sentenciaUsoUsuario = $conexion->prepare('SELECT COUNT(*) FROM uso_cupon WHERE id_usuario = :id_usuario AND id_cupon = :id_cupon');
    $sentenciaUsoUsuario->execute([
        ':id_usuario' => $idUsuario,
        ':id_cupon' => (int) $cupon['id_cupon'],
    ]);

    if ((int) $sentenciaUsoUsuario->fetchColumn() > 0) {
        return ['ok' => false, 'mensaje' => 'Ya usaste este cupón en una compra anterior.'];
    }

    if ($cupon['max_usos_total'] !== null) {
        $sentenciaUsosTotales = $conexion->prepare('SELECT COUNT(*) FROM uso_cupon WHERE id_cupon = :id_cupon');
        $sentenciaUsosTotales->execute([':id_cupon' => (int) $cupon['id_cupon']]);

        if ((int) $sentenciaUsosTotales->fetchColumn() >= (int) $cupon['max_usos_total']) {
            return ['ok' => false, 'mensaje' => 'Este cupón alcanzó su límite de usos.'];
        }
    }

    return ['ok' => true, 'mensaje' => 'Cupón disponible.'];
}

/**
 * Recupera los cupones que el cliente cargo en su perfil y aun puede intentar usar.
 */
function obtener_cupones_ingresados_usuario(PDO $conexion, int $idUsuario): array
{
    $idsCupones = array_map('intval', array_keys($_SESSION['cupones_usuario'][$idUsuario] ?? []));

    if ($idsCupones === []) {
        return [];
    }

    $marcadores = implode(',', array_fill(0, count($idsCupones), '?'));
    $sentencia = $conexion->prepare("SELECT * FROM cupon WHERE id_cupon IN ($marcadores) ORDER BY codigo ASC");
    $sentencia->execute($idsCupones);

    return $sentencia->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/* Helpers de autenticacion y permisos. */
function usuario_actual(): ?array
{
    return $_SESSION['usuario_actual'] ?? null;
}

/**
 * Crea un token nuevo para dejar una sola sesion valida por usuario.
 * Si la cuenta inicia sesion en otro navegador, este valor reemplaza al anterior.
 */
function crear_token_sesion_usuario(PDO $conexion, int $idUsuario): string
{
    $token = bin2hex(random_bytes(32));
    $sentencia = $conexion->prepare(
        'UPDATE usuario
         SET sesion_activa_token = :token,
             fecha_actualizacion = NOW()
         WHERE id_usuario = :id_usuario'
    );
    $sentencia->execute([
        ':token' => $token,
        ':id_usuario' => $idUsuario,
    ]);

    return $token;
}

/**
 * Guarda los datos basicos del usuario en sesion junto con el token activo.
 */
function iniciar_sesion_usuario(PDO $conexion, array $usuario): void
{
    $idUsuario = (int) $usuario['id_usuario'];
    $tokenSesion = crear_token_sesion_usuario($conexion, $idUsuario);

    $_SESSION['usuario_actual'] = [
        'id_usuario' => $idUsuario,
        'nombre' => $usuario['nombre'],
        'apellido' => $usuario['apellido'],
        'mail' => $usuario['mail'],
        'is_admin' => (int) $usuario['is_admin'] === 1,
        'email_verificado' => (int) ($usuario['email_verificado'] ?? 0) === 1,
        'sesion_token' => $tokenSesion,
    ];

    registrar_actividad_sesion();
}

/**
 * Limpia el token activo solo si coincide con la sesion que esta cerrando.
 * Asi una sesion vieja no puede cerrar la sesion nueva abierta en otro equipo.
 */
function invalidar_token_sesion_actual(PDO $conexion): void
{
    $usuario = usuario_actual();

    if (!$usuario) {
        return;
    }

    $idUsuario = (int) ($usuario['id_usuario'] ?? 0);
    $tokenSesion = (string) ($usuario['sesion_token'] ?? '');

    if ($idUsuario <= 0 || $tokenSesion === '') {
        return;
    }

    $sentencia = $conexion->prepare(
        'UPDATE usuario
         SET sesion_activa_token = NULL,
             fecha_actualizacion = NOW()
         WHERE id_usuario = :id_usuario
           AND sesion_activa_token = :token'
    );
    $sentencia->execute([
        ':id_usuario' => $idUsuario,
        ':token' => $tokenSesion,
    ]);
}

/**
 * Verifica que la sesion actual siga siendo la ultima sesion iniciada.
 */
function validar_sesion_unica(PDO $conexion): void
{
    $usuario = usuario_actual();

    if (!$usuario) {
        return;
    }

    $idUsuario = (int) ($usuario['id_usuario'] ?? 0);
    $tokenSesion = (string) ($usuario['sesion_token'] ?? '');

    if ($idUsuario <= 0 || $tokenSesion === '') {
        destruir_sesion_actual();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        guardar_flash('mensaje_error', 'Tu sesion ya no es valida. Volve a iniciar sesion.');
        redirigir('/login.php');
    }

    $sentencia = $conexion->prepare('SELECT activo, sesion_activa_token FROM usuario WHERE id_usuario = :id_usuario LIMIT 1');
    $sentencia->execute([':id_usuario' => $idUsuario]);
    $datosUsuario = $sentencia->fetch(PDO::FETCH_ASSOC);

    if (!$datosUsuario || (int) $datosUsuario['activo'] !== 1 || !hash_equals((string) $datosUsuario['sesion_activa_token'], $tokenSesion)) {
        destruir_sesion_actual();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        guardar_flash('mensaje_error', 'Tu cuenta se inicio en otro dispositivo. Volve a iniciar sesion para continuar.');
        redirigir('/login.php');
    }
}

/**
 * Indica si el usuario actual tiene rol administrador.
 */
function es_admin(): bool
{
    return !empty($_SESSION['usuario_actual']['is_admin']);
}

/**
 * Obliga a iniciar sesion antes de continuar con una pagina privada.
 */
function requiere_login(): void
{
    if (!usuario_actual()) {
        guardar_flash('mensaje_error', 'Debés iniciar sesión para continuar.');
        redirigir('/login.php');
    }

    validar_sesion_unica(obtener_conexion_db());
}

/**
 * Obliga a tener sesion admin; si falla redirige fuera del dashboard.
 */
function requiere_admin(): void
{
    if (!usuario_actual()) {
        guardar_flash('mensaje_error', 'Debés iniciar sesión como administrador.');
        redirigir('/login.php');
    }

    validar_sesion_unica(obtener_conexion_db());

    if (!es_admin()) {
        guardar_flash('mensaje_error', 'No tenés permisos para acceder a esta sección.');
        redirigir('/index.php');
    }
}

function validar_contrasena_segura(string $contrasena, string $campo = 'Contraseña'): array
{
    $errores = [];
    $longitud = mb_strlen($contrasena);

    if ($longitud < 8) {
        $errores[] = $campo . ' debe tener al menos 8 caracteres.';
    }

    if ($longitud > 16) {
        $errores[] = $campo . ' no puede superar los 16 caracteres.';
    }

    if (!preg_match('/[0-9]/', $contrasena)) {
        $errores[] = $campo . ' debe incluir al menos un número.';
    }

    if (!preg_match('/[a-zA-ZÁÉÍÓÚÜÑáéíóúüñ]/u', $contrasena)) {
        $errores[] = $campo . ' debe incluir al menos una letra.';
    }

    if (!preg_match('/[^a-zA-Z0-9ÁÉÍÓÚÜÑáéíóúüñ]/u', $contrasena)) {
        $errores[] = $campo . ' debe incluir al menos un carácter especial.';
    }

    return $errores;
}

/* Helpers del carrito guardado en sesion. */
function inicializar_carrito(): void
{
    if (!isset($_SESSION['carrito'])) {
        $_SESSION['carrito'] = [];
    }
}

/**
 * Da de baja logicamente la cuenta indicada.
 * No borra compras ni datos relacionados para conservar el historial.
 */
function dar_baja_cuenta_usuario(PDO $conexion, int $idUsuario): void
{
    $sentencia = $conexion->prepare(
        'UPDATE usuario
         SET activo = 0,
             sesion_activa_token = NULL,
             fecha_actualizacion = NOW()
         WHERE id_usuario = :id_usuario'
    );
    $sentencia->execute([':id_usuario' => $idUsuario]);
}

/**
 * Agrega una cantidad positiva de una variante al carrito en sesion.
 */
function agregar_al_carrito(int $idVariante, int $cantidad = 1): void
{
    inicializar_carrito();

    if (!isset($_SESSION['carrito'][$idVariante])) {
        $_SESSION['carrito'][$idVariante] = 0;
    }

    $_SESSION['carrito'][$idVariante] += max(1, $cantidad);
}

/**
 * Reemplaza la cantidad de una variante o la elimina si queda en cero.
 */
function actualizar_carrito(int $idVariante, int $cantidad): void
{
    inicializar_carrito();

    if ($cantidad <= 0) {
        unset($_SESSION['carrito'][$idVariante]);
        return;
    }

    $_SESSION['carrito'][$idVariante] = $cantidad;
}

/**
 * Quita una variante puntual del carrito.
 */
function eliminar_del_carrito(int $idVariante): void
{
    inicializar_carrito();
    unset($_SESSION['carrito'][$idVariante]);
}

/**
 * Limpia por completo el carrito de la sesion actual.
 */
function vaciar_carrito(): void
{
    $_SESSION['carrito'] = [];
}

/**
 * Devuelve la suma de unidades guardadas en el carrito.
 */
function obtener_cantidad_carrito(): int
{
    inicializar_carrito();
    return array_sum($_SESSION['carrito']);
}

/**
 * Devuelve cuantas unidades de una variante ya estan en el carrito.
 */
function obtener_cantidad_variante_en_carrito(int $idVariante): int
{
    inicializar_carrito();

    return (int) ($_SESSION['carrito'][$idVariante] ?? 0);
}

/**
 * Suma las unidades de todas las variantes de un mismo producto en el carrito.
 */
function obtener_cantidad_producto_en_carrito(PDO $conexion, int $idProducto, ?int $idVarianteExcluida = null): int
{
    inicializar_carrito();

    if ($_SESSION['carrito'] === []) {
        return 0;
    }

    $idsVariantes = array_map('intval', array_keys($_SESSION['carrito']));
    $marcadores = implode(',', array_fill(0, count($idsVariantes), '?'));
    $sentencia = $conexion->prepare("SELECT id_variante FROM producto_variante WHERE id_producto = ? AND id_variante IN ($marcadores)");
    $sentencia->execute(array_merge([$idProducto], $idsVariantes));
    $idsDelProducto = $sentencia->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $total = 0;

    foreach ($idsDelProducto as $idVarianteProducto) {
        $idVarianteProducto = (int) $idVarianteProducto;

        if ($idVarianteExcluida !== null && $idVarianteProducto === $idVarianteExcluida) {
            continue;
        }

        $total += (int) ($_SESSION['carrito'][$idVarianteProducto] ?? 0);
    }

    return $total;
}

/**
 * Calcula cuanto stock queda considerando el stock real y el limite por producto.
 */
function obtener_stock_disponible_variante(PDO $conexion, int $idVariante): int
{
    $variante = obtener_variante_por_id($conexion, $idVariante);

    if (!$variante || $variante['estado'] !== 'activo') {
        return 0;
    }

    $restantePorStock = max(0, (int) $variante['stock'] - obtener_cantidad_variante_en_carrito($idVariante));
    $restantePorLimiteProducto = max(0, limite_unidades_por_producto() - obtener_cantidad_producto_en_carrito($conexion, (int) $variante['id_producto']));

    return min($restantePorStock, $restantePorLimiteProducto);
}

/* Consultas de pedidos, catalogo y stock reutilizadas por vistas publicas y admin. */
function obtener_detalles_pedido(PDO $conexion, int $idPedido): array
{
    $sentencia = $conexion->prepare(
        'SELECT
            dp.id_variante,
            dp.cantidad,
            dp.precio_unitario,
            dp.subtotal_linea,
            v.talle,
            v.sku,
            p.nombre_producto,
            p.imagen
         FROM detalle_pedido dp
         INNER JOIN producto_variante v ON v.id_variante = dp.id_variante
         INNER JOIN producto p ON p.id_producto = v.id_producto
         WHERE dp.id_pedido = :id_pedido
         ORDER BY dp.id_detalle ASC'
    );
    $sentencia->execute([':id_pedido' => $idPedido]);

    return $sentencia->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Lista variantes activas con producto padre para formularios de stock.
 */
function obtener_variantes_con_producto(PDO $conexion): array
{
    $sentencia = $conexion->query(
        'SELECT
            v.id_variante,
            v.id_producto,
            v.talle,
            v.stock,
            v.sku,
            v.estado,
            p.nombre_producto
         FROM producto_variante v
         INNER JOIN producto p ON p.id_producto = v.id_producto
         WHERE v.estado = "activo"
           AND v.sku IS NOT NULL
           AND v.sku <> ""
         ORDER BY p.nombre_producto ASC, v.talle ASC'
    );

    return $sentencia->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Registra un ingreso/egreso de stock y actualiza la variante en una transaccion.
 */
function registrar_movimiento_stock(PDO $conexion, int $idVariante, string $tipo, int $cantidad, string $observacion): array
{
    $variante = obtener_variante_por_id($conexion, $idVariante);

    if (!$variante || $variante['estado'] !== 'activo') {
        throw new RuntimeException('La variante seleccionada no existe o no está activa.');
    }

    if ($cantidad <= 0) {
        throw new RuntimeException('La cantidad debe ser mayor a cero.');
    }

    $stockAnterior = (int) $variante['stock'];
    $stockResultado = $tipo === 'ingreso' ? $stockAnterior + $cantidad : $stockAnterior - $cantidad;

    if ($stockResultado < 0) {
        throw new RuntimeException('No hay stock suficiente para realizar el egreso.');
    }

    $conexion->beginTransaction();

    try {
        $sentenciaMovimiento = $conexion->prepare(
            'INSERT INTO movimiento_stock
                (id_variante, tipo_movimiento, cantidad, stock_anterior, stock_resultante, observacion, fecha_movimiento)
             VALUES
                (:id_variante, :tipo_movimiento, :cantidad, :stock_anterior, :stock_resultante, :observacion, NOW())'
        );
        $sentenciaMovimiento->execute([
            ':id_variante' => $idVariante,
            ':tipo_movimiento' => $tipo,
            ':cantidad' => $cantidad,
            ':stock_anterior' => $stockAnterior,
            ':stock_resultante' => $stockResultado,
            ':observacion' => $observacion,
        ]);

        $sentenciaStock = $conexion->prepare(
            'UPDATE producto_variante
             SET stock = :stock, fecha_actualizacion = NOW()
             WHERE id_variante = :id_variante'
        );
        $sentenciaStock->execute([
            ':stock' => $stockResultado,
            ':id_variante' => $idVariante,
        ]);

        $conexion->commit();

        return [
            'stock_anterior' => $stockAnterior,
            'stock_resultante' => $stockResultado,
        ];
    } catch (Throwable $ex) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        throw $ex;
    }
}

/**
 * Cancela un pedido, devuelve el stock de cada linea y deja trazabilidad en el historial.
 *
 * @return array{estado_anterior:string, estado_nuevo:string, pedido:array}
 */
function cancelar_pedido(PDO $conexion, int $idPedido, array $actor, string $tipoActor, ?int $idUsuarioEsperado = null): array
{
    if ($idPedido <= 0) {
        throw new RuntimeException('Pedido invalido.');
    }

    $conexion->beginTransaction();

    try {
        $sentenciaPedido = $conexion->prepare(
            'SELECT p.*, u.nombre, u.apellido, u.mail
             FROM pedido p
             INNER JOIN usuario u ON u.id_usuario = p.id_usuario
             WHERE p.id_pedido = :id_pedido
             LIMIT 1
             FOR UPDATE'
        );
        $sentenciaPedido->execute([':id_pedido' => $idPedido]);
        $pedido = $sentenciaPedido->fetch(PDO::FETCH_ASSOC);

        if (!$pedido) {
            throw new RuntimeException('El pedido no existe.');
        }

        if ($idUsuarioEsperado !== null && (int) $pedido['id_usuario'] !== $idUsuarioEsperado) {
            throw new RuntimeException('No tenes permiso para cancelar este pedido.');
        }

        $estadoAnterior = (string) $pedido['estado_pedido'];

        if (!pedido_puede_cancelarse($estadoAnterior)) {
            throw new RuntimeException('Este pedido no se puede cancelar.');
        }

        $sentenciaDetalles = $conexion->prepare(
            'SELECT id_variante, cantidad
             FROM detalle_pedido
             WHERE id_pedido = :id_pedido'
        );
        $sentenciaDetalles->execute([':id_pedido' => $idPedido]);
        $detalles = $sentenciaDetalles->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($detalles === []) {
            throw new RuntimeException('El pedido no tiene productos para restaurar.');
        }

        $nombreActor = trim((string) ($actor['nombre'] ?? '') . ' ' . (string) ($actor['apellido'] ?? ''));
        $nombreActor = $nombreActor !== '' ? $nombreActor : 'Usuario';
        $textoActor = $tipoActor === 'admin'
            ? 'Cancelado por ' . $nombreActor . '(admin)'
            : 'Cancelado por cliente ' . $nombreActor;
        $observacion = 'Cancelacion de la orden #' . $idPedido . '. ' . $textoActor . '.';

        $sentenciaStockActual = $conexion->prepare(
            'SELECT stock
             FROM producto_variante
             WHERE id_variante = :id_variante
             LIMIT 1
             FOR UPDATE'
        );
        $sentenciaActualizarStock = $conexion->prepare(
            'UPDATE producto_variante
             SET stock = :stock, fecha_actualizacion = NOW()
             WHERE id_variante = :id_variante'
        );
        $sentenciaMovimiento = $conexion->prepare(
            'INSERT INTO movimiento_stock
                (id_variante, tipo_movimiento, cantidad, stock_anterior, stock_resultante, observacion, fecha_movimiento)
             VALUES
                (:id_variante, :tipo_movimiento, :cantidad, :stock_anterior, :stock_resultante, :observacion, NOW())'
        );

        foreach ($detalles as $detalle) {
            $idVariante = (int) $detalle['id_variante'];
            $cantidad = (int) $detalle['cantidad'];

            $sentenciaStockActual->execute([':id_variante' => $idVariante]);
            $stockAnterior = $sentenciaStockActual->fetchColumn();

            if ($stockAnterior === false) {
                throw new RuntimeException('No se encontro una variante del pedido.');
            }

            $stockAnterior = (int) $stockAnterior;
            $stockResultado = $stockAnterior + $cantidad;

            $sentenciaActualizarStock->execute([
                ':stock' => $stockResultado,
                ':id_variante' => $idVariante,
            ]);

            $sentenciaMovimiento->execute([
                ':id_variante' => $idVariante,
                ':tipo_movimiento' => 'ingreso',
                ':cantidad' => $cantidad,
                ':stock_anterior' => $stockAnterior,
                ':stock_resultante' => $stockResultado,
                ':observacion' => $observacion,
            ]);
        }

        $sentenciaActualizarPedido = $conexion->prepare(
            'UPDATE pedido
             SET estado_pedido = :estado_pedido, fecha_actualizacion = NOW()
             WHERE id_pedido = :id_pedido'
        );
        $sentenciaActualizarPedido->execute([
            ':estado_pedido' => 'cancelado',
            ':id_pedido' => $idPedido,
        ]);

        $conexion->commit();

        return [
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => 'cancelado',
            'pedido' => $pedido,
        ];
    } catch (Throwable $ex) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        throw $ex;
    }
}

/**
 * Lista productos con totales de variantes y stock para catalogo o administracion.
 */
function obtener_productos(PDO $conexion, string $busqueda = '', bool $soloDisponibles = true): array
{
    $sql = "
        SELECT
            p.id_producto,
            p.nombre_producto,
            p.descripcion,
            p.precio,
            p.precio_anterior,
            p.imagen,
            p.estado,
            p.oferta,
            COALESCE(v.total_variantes, 0) AS total_variantes,
            COALESCE(v.stock_total, 0) AS stock_total
        FROM producto p
        LEFT JOIN (
            SELECT
                id_producto,
                COUNT(*) AS total_variantes,
                SUM(CASE WHEN estado = 'activo' THEN stock ELSE 0 END) AS stock_total
            FROM producto_variante
            GROUP BY id_producto
        ) v ON v.id_producto = p.id_producto
        WHERE 1 = 1
    ";

    $parametros = [];

    if ($soloDisponibles) {
        $sql .= " AND p.estado = 'disponible'";
    }

    if ($busqueda !== '') {
        $sql .= " AND (p.nombre_producto LIKE :busqueda_nombre OR p.descripcion LIKE :busqueda_descripcion)";
        $parametros[':busqueda_nombre'] = '%' . $busqueda . '%';
        $parametros[':busqueda_descripcion'] = '%' . $busqueda . '%';
    }

    $sql .= " ORDER BY p.fecha_creacion DESC";

    $sentencia = $conexion->prepare($sql);
    $sentencia->execute($parametros);

    return $sentencia->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Obtiene todas las variantes de un producto para formularios y detalle publico.
 */
function obtener_variantes_producto(PDO $conexion, int $idProducto): array
{
    $sentencia = $conexion->prepare(
        'SELECT * FROM producto_variante WHERE id_producto = :id_producto ORDER BY fecha_creacion ASC, id_variante ASC'
    );
    $sentencia->execute([':id_producto' => $idProducto]);

    return $sentencia->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Busca un producto por ID y devuelve null si no existe.
 */
function obtener_producto_por_id(PDO $conexion, int $idProducto): ?array
{
    $sentencia = $conexion->prepare('SELECT * FROM producto WHERE id_producto = :id_producto LIMIT 1');
    $sentencia->execute([':id_producto' => $idProducto]);

    $producto = $sentencia->fetch(PDO::FETCH_ASSOC);
    return $producto ?: null;
}

/**
 * Devuelve un producto junto con sus variantes para pantallas de edicion/detalle.
 */
function obtener_producto_con_variantes(PDO $conexion, int $idProducto): ?array
{
    $producto = obtener_producto_por_id($conexion, $idProducto);

    if (!$producto) {
        return null;
    }

    $producto['variantes'] = obtener_variantes_producto($conexion, $idProducto);
    return $producto;
}

/**
 * Busca una variante y adjunta datos basicos del producto padre.
 */
function obtener_variante_por_id(PDO $conexion, int $idVariante): ?array
{
    $sentencia = $conexion->prepare(
        'SELECT v.*, p.nombre_producto, p.precio, p.precio_anterior, p.descripcion, p.imagen, p.oferta, p.estado AS estado_producto
         FROM producto_variante v
         INNER JOIN producto p ON p.id_producto = v.id_producto
         WHERE v.id_variante = :id_variante LIMIT 1'
    );
    $sentencia->execute([':id_variante' => $idVariante]);

    $variante = $sentencia->fetch(PDO::FETCH_ASSOC);
    return $variante ?: null;
}

/**
 * Convierte el carrito de sesion en filas completas listas para renderizar.
 */
function obtener_detalles_carrito(PDO $conexion): array
{
    inicializar_carrito();

    if ($_SESSION['carrito'] === []) {
        return [];
    }

    $ids = array_keys($_SESSION['carrito']);
    $marcadores = implode(',', array_fill(0, count($ids), '?'));

    $sentencia = $conexion->prepare(
        "SELECT v.id_variante, v.id_producto, v.talle, v.stock, v.sku, v.estado, p.nombre_producto, p.precio, p.precio_anterior, p.imagen
         FROM producto_variante v
         INNER JOIN producto p ON p.id_producto = v.id_producto
         WHERE v.id_variante IN ($marcadores)"
    );
    $sentencia->execute($ids);

    $filas = $sentencia->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $detalle = [];

    foreach ($filas as $fila) {
        $cantidad = (int) ($_SESSION['carrito'][(int) $fila['id_variante']] ?? 0);
        $precio = (float) $fila['precio'];
        $detalle[] = [
            'id_variante' => (int) $fila['id_variante'],
            'id_producto' => (int) $fila['id_producto'],
            'nombre_producto' => $fila['nombre_producto'],
            'talle' => $fila['talle'],
            'sku' => $fila['sku'],
            'stock' => (int) $fila['stock'],
            'imagen' => $fila['imagen'],
            'precio' => $precio,
            'precio_anterior' => $fila['precio_anterior'] !== null ? (float) $fila['precio_anterior'] : null,
            'cantidad' => $cantidad,
            'subtotal' => $precio * $cantidad,
        ];
    }

    return $detalle;
}

/**
 * Calcula los indicadores simples que se muestran en el inicio del dashboard.
 */
function obtener_resumen_admin(PDO $conexion): array
{
    $resumen = [];

    $resumen['productos'] = (int) $conexion->query("SELECT COUNT(*) FROM producto WHERE estado <> 'eliminado'")->fetchColumn();
    $resumen['pedidos'] = (int) $conexion->query('SELECT COUNT(*) FROM pedido')->fetchColumn();
    $resumen['usuarios'] = (int) $conexion->query('SELECT COUNT(*) FROM usuario')->fetchColumn();
    $resumen['ventas'] = (float) $conexion->query('SELECT COALESCE(SUM(total),0) FROM pedido')->fetchColumn();

    return $resumen;
}

/**
 * Busca usuarios por correo para login, registro y recuperacion de acceso.
 */
function obtener_usuario_por_correo(PDO $conexion, string $correo): ?array
{
    $sentencia = $conexion->prepare('SELECT * FROM usuario WHERE mail = :mail LIMIT 1');
    $sentencia->execute([':mail' => $correo]);

    $usuario = $sentencia->fetch(PDO::FETCH_ASSOC);
    return $usuario ?: null;
}

/**
 * Crea un codigo numerico temporal, lo guarda hasheado y devuelve el codigo real para enviarlo por mail.
 */
function crear_codigo_usuario(PDO $conexion, int $idUsuario, string $tipo, int $minutosValidez = 2): string
{
    $codigo = (string) random_int(100000, 999999);
    $codigoHash = password_hash($codigo, PASSWORD_DEFAULT);
    $fechaVencimiento = date('Y-m-d H:i:s', time() + ($minutosValidez * 60));

    /* Antes de crear un codigo nuevo, anulamos los codigos pendientes del
       mismo usuario y tipo. Esto evita que queden varios codigos validos. */
    $sentenciaInvalidar = $conexion->prepare(
        'UPDATE token_usuario
         SET usado = 1, fecha_uso = NOW()
         WHERE id_usuario = :id_usuario AND tipo = :tipo AND usado = 0'
    );
    $sentenciaInvalidar->execute([
        ':id_usuario' => $idUsuario,
        ':tipo' => $tipo,
    ]);

    /* Guardamos el codigo hasheado, no el numero real. Si alguien mira la base,
       no puede usar el codigo directamente para verificar una cuenta. */
    $sentenciaCrear = $conexion->prepare(
        'INSERT INTO token_usuario
            (id_usuario, tipo, token, fecha_creacion, fecha_vencimiento, usado, fecha_uso)
         VALUES
            (:id_usuario, :tipo, :token, NOW(), :fecha_vencimiento, 0, NULL)'
    );
    $sentenciaCrear->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
    $sentenciaCrear->bindValue(':tipo', $tipo);
    $sentenciaCrear->bindValue(':token', $codigoHash);
    $sentenciaCrear->bindValue(':fecha_vencimiento', $fechaVencimiento);
    $sentenciaCrear->execute();

    return $codigo;
}

/**
 * Recupera el ultimo codigo vigente de un tipo para un usuario.
 */
function obtener_ultimo_codigo_activo_usuario(PDO $conexion, int $idUsuario, string $tipo): ?array
{
    $sentencia = $conexion->prepare(
        'SELECT tu.*, u.nombre, u.apellido, u.mail, u.email_verificado, u.activo
         FROM token_usuario tu
         INNER JOIN usuario u ON u.id_usuario = tu.id_usuario
         WHERE tu.id_usuario = :id_usuario
           AND tu.tipo = :tipo
           AND tu.usado = 0
         ORDER BY tu.fecha_creacion DESC, tu.id_token DESC
         LIMIT 1'
    );
    $sentencia->execute([
        ':id_usuario' => $idUsuario,
        ':tipo' => $tipo,
    ]);

    $fila = $sentencia->fetch(PDO::FETCH_ASSOC);
    return $fila ?: null;
}

/**
 * Verifica formato, vencimiento y hash del codigo ingresado por el usuario.
 */
function verificar_codigo_usuario(PDO $conexion, int $idUsuario, string $tipo, string $codigo): ?array
{
    $codigo = trim($codigo);

    if (!preg_match('/^[0-9]{6}$/', $codigo)) {
        return null;
    }

    $datosCodigo = obtener_ultimo_codigo_activo_usuario($conexion, $idUsuario, $tipo);

    if (!$datosCodigo || (int) $datosCodigo['activo'] !== 1) {
        return null;
    }

    if ((string) $datosCodigo['fecha_vencimiento'] < date('Y-m-d H:i:s')) {
        return null;
    }

    if (!password_verify($codigo, (string) $datosCodigo['token'])) {
        return null;
    }

    return $datosCodigo;
}

/**
 * Marca un codigo/token como consumido para que no pueda reutilizarse.
 */
function marcar_token_usado(PDO $conexion, int $idToken): void
{
    $sentencia = $conexion->prepare('UPDATE token_usuario SET usado = 1, fecha_uso = NOW() WHERE id_token = :id_token');
    $sentencia->execute([':id_token' => $idToken]);
}

/**
 * Busca SKUs repetidos en otros productos para prevenir colisiones.
 */
function obtener_productos_por_sku(PDO $conexion, array $skus, int $idProductoExcluir = 0): array
{
    $skus = array_values(array_unique(array_filter($skus, static fn ($sku) => $sku !== '')));

    if ($skus === []) {
        return [];
    }

    $marcadores = implode(',', array_fill(0, count($skus), '?'));
    $sql = "
        SELECT DISTINCT pv.sku, p.id_producto, p.nombre_producto
        FROM producto_variante pv
        INNER JOIN producto p ON p.id_producto = pv.id_producto
        WHERE pv.sku IN ($marcadores)
    ";

    $parametros = $skus;

    if ($idProductoExcluir > 0) {
        $sql .= ' AND p.id_producto <> ?';
        $parametros[] = $idProductoExcluir;
    }

    $sentencia = $conexion->prepare($sql);
    $sentencia->execute($parametros);

    return $sentencia->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Atajo booleano para la validacion rapida de SKU desde formularios.
 */
function existe_sku_en_otro_producto(PDO $conexion, string $sku, int $idProductoExcluir = 0): bool
{
    // Si el SKU viene vacío, no hay nada para validar.
    // La base de datos permite variantes sin SKU usando NULL.
    $sku = trim($sku);
    if ($sku === '') {
        return false;
    }

    // Reutilizamos la búsqueda general de SKU para que la regla sea la misma
    // en el formulario normal y en la validación rápida por AJAX.
    return obtener_productos_por_sku($conexion, [$sku], $idProductoExcluir) !== [];
}
