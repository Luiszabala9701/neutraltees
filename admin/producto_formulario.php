<?php

/**
 * Modulo: formulario de producto.
 * Responsabilidad: crear/modificar productos y validar SKUs de variantes.
 */

require_once __DIR__ . '/../config/conexion_DB.php';
$conexion = obtener_conexion_db();
requiere_admin();

if (($_GET['ajax'] ?? '') === 'validar_sku') {
    // Respuesta liviana para JavaScript: valida si el SKU ya pertenece a otro producto.
    header('Content-Type: application/json; charset=utf-8');

    $skuAjax = trim((string) ($_GET['sku'] ?? ''));
    $idProductoAjax = isset($_GET['id_producto']) ? (int) $_GET['id_producto'] : 0;

    echo json_encode([
        'existe' => existe_sku_en_otro_producto($conexion, $skuAjax, $idProductoAjax),
    ]);
    exit;
}

$idProducto = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$esEdicion = $idProducto > 0;
$errores = [];

$datos = [
    'nombre_producto' => '',
    'descripcion' => '',
    'precio' => '',
    'precio_anterior' => '',
    'estado' => 'disponible',
    'oferta' => 0,
    'imagen' => '',
];

$variantesFormulario = [
    ['talle' => 'S', 'stock' => '', 'sku' => ''],
    ['talle' => 'M', 'stock' => '', 'sku' => ''],
    ['talle' => 'L', 'stock' => '', 'sku' => ''],
    ['talle' => 'XL', 'stock' => '', 'sku' => ''],
];
$variantesExistentes = [];

if ($esEdicion) {
    $producto = obtener_producto_por_id($conexion, $idProducto);
    if (!$producto) {
        guardar_flash('mensaje_error', 'El producto no existe.');
        redirigir('/admin/productos.php');
    }

    $datos = $producto;
    if (empty($datos['oferta']) && empty($datos['precio_anterior']) && !empty($datos['precio'])) {
        $datos['precio_anterior'] = $datos['precio'];
        $datos['precio'] = '';
    }
    $variantesExistentes = obtener_variantes_producto($conexion, $idProducto);
    $variantesFormulario = array_values(array_filter($variantesExistentes, static function (array $variante): bool {
        return trim((string) ($variante['sku'] ?? '')) !== '';
    }));
    if ($variantesFormulario === [] && $variantesExistentes === []) {
        $variantesFormulario = [
            ['talle' => 'S', 'stock' => '', 'sku' => ''],
            ['talle' => 'M', 'stock' => '', 'sku' => ''],
            ['talle' => 'L', 'stock' => '', 'sku' => ''],
            ['talle' => 'XL', 'stock' => '', 'sku' => ''],
        ];
    }
}

if (es_post()) {
    $datos['nombre_producto'] = trim(strip_tags((string) ($_POST['nombre_producto'] ?? '')));
    $datos['descripcion'] = trim((string) ($_POST['descripcion'] ?? ''));
    $datos['precio'] = (float) ($_POST['precio'] ?? 0);
    $datos['precio_anterior'] = ($_POST['precio_anterior'] ?? '') !== '' ? (float) $_POST['precio_anterior'] : null;
    $datos['estado'] = trim(strip_tags((string) ($_POST['estado'] ?? 'disponible')));
    $datos['oferta'] = isset($_POST['oferta']) ? 1 : 0;
    $variantesRecibidas = $_POST['variantes'] ?? [];
    $archivoImagen = $_FILES['imagen'] ?? null;
    $variantesFormularioPost = [];
    $precioNormal = ($_POST['precio_anterior'] ?? '') !== '' ? (float) $_POST['precio_anterior'] : 0;
    $precioDescuento = ($_POST['precio'] ?? '') !== '' ? (float) $_POST['precio'] : null;

    foreach ($variantesRecibidas as $varianteRecibida) {
        $variantesFormularioPost[] = [
            'talle' => (string) ($varianteRecibida['talle'] ?? ''),
            'stock' => (string) ($varianteRecibida['stock'] ?? ''),
            'sku' => (string) ($varianteRecibida['sku'] ?? ''),
        ];
    }

    if ($variantesFormularioPost !== []) {
        $variantesFormulario = $variantesFormularioPost;
    }

    if ($datos['nombre_producto'] === '' || $precioNormal <= 0) {
        $errores[] = 'Completá los campos obligatorios del producto.';
    }

    if ($datos['oferta'] === 1 && ($precioDescuento === null || $precioDescuento <= 0)) {
        $errores[] = 'Completá el precio con descuento cuando el producto está en oferta.';
    }

    if ($datos['oferta'] === 1 && $precioDescuento !== null && $precioNormal <= $precioDescuento) {
        $errores[] = 'El precio normal debe ser mayor al precio con descuento cuando el producto está en oferta.';
    }

    if ($datos['oferta'] === 0) {
        $datos['precio'] = $precioNormal;
        $datos['precio_anterior'] = null;
    } else {
        $datos['precio'] = $precioDescuento ?? 0;
        $datos['precio_anterior'] = $precioNormal;
    }

    $tallesPermitidos = ['S', 'M', 'L', 'XL'];
    $variantesNormalizadas = [];
    $tallesDetectados = [];
    $hayVarianteConStock = false;
    $debeProcesarVariantes = $variantesRecibidas !== [];

    foreach ($debeProcesarVariantes ? $variantesRecibidas : [] as $varianteRecibida) {
        $talle = strtoupper(trim(strip_tags((string) ($varianteRecibida['talle'] ?? ''))));
        $stock = (int) ($varianteRecibida['stock'] ?? 0);
        $sku = trim(strip_tags((string) ($varianteRecibida['sku'] ?? '')));

        if ($talle === '' && $stock === 0 && $sku === '') {
            continue;
        }

        if (!in_array($talle, $tallesPermitidos, true)) {
            $errores[] = 'Los talles permitidos son S, M, L y XL.';
            continue;
        }

        if ($stock < 0) {
            $errores[] = 'El stock de cada variante no puede ser negativo.';
            continue;
        }

        if (in_array($talle, $tallesDetectados, true)) {
            $errores[] = 'No puede haber talles repetidos en variantes.';
            continue;
        }

        $tallesDetectados[] = $talle;

        $variantesNormalizadas[] = [
            'talle' => $talle,
            'stock' => $stock,
            'sku' => $sku,
        ];

        if ($stock > 0) {
            $hayVarianteConStock = true;
        }
    }

    if ($debeProcesarVariantes && $variantesNormalizadas === []) {
        $errores[] = 'Debés crear al menos una variante (S, M, L o XL).';
    }

    if ($debeProcesarVariantes && !$esEdicion && $variantesNormalizadas !== [] && !$hayVarianteConStock) {
        $errores[] = 'Debés cargar stock en al menos una variante para crear el producto.';
    }

    $skusFormulario = array_values(array_filter(array_map(static fn (array $variante): string => $variante['sku'], $variantesNormalizadas)));
    $skusRepetidosFormulario = array_filter(array_count_values($skusFormulario), static fn (int $repeticiones): bool => $repeticiones > 1);

    if ($skusRepetidosFormulario !== []) {
        $errores[] = 'No se pueden repetir los SKU entre variantes del mismo producto.';
    }

    if (obtener_productos_por_sku($conexion, $skusFormulario, $esEdicion ? $idProducto : 0) !== []) {
        $errores[] = 'No se pueden repetir los SKU con otro producto existente.';
    }

    if ($errores === []) {
        try {
            $conexion->beginTransaction();

            $rutaImagen = $datos['imagen'] ?? '';

            if ($archivoImagen && is_array($archivoImagen) && ($archivoImagen['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'webp'];
                $mimesPermitidos = ['image/jpeg', 'image/png', 'image/webp'];
                $extension = strtolower(pathinfo((string) $archivoImagen['name'], PATHINFO_EXTENSION));
                $mimeDetectado = (string) (mime_content_type((string) $archivoImagen['tmp_name']) ?: '');

                if (!in_array($extension, $extensionesPermitidas, true) || !in_array($mimeDetectado, $mimesPermitidos, true)) {
                    throw new RuntimeException('Formato de imagen no permitido.');
                }

                $nombreArchivo = 'producto_' . uniqid('', true) . '.' . $extension;
                $destino = __DIR__ . '/../assets/img/productos/' . $nombreArchivo;

                if (!is_dir(dirname($destino))) {
                    mkdir(dirname($destino), 0777, true);
                }

                if (!move_uploaded_file((string) $archivoImagen['tmp_name'], $destino)) {
                    throw new RuntimeException('No se pudo guardar la imagen.');
                }

                $rutaImagen = 'assets/img/productos/' . $nombreArchivo;
            }

            if ($esEdicion) {
                $sentencia = $conexion->prepare(
                    'UPDATE producto
                     SET nombre_producto = :nombre_producto,
                         descripcion = :descripcion,
                         precio = :precio,
                         precio_anterior = :precio_anterior,
                         imagen = :imagen,
                         estado = :estado,
                         oferta = :oferta,
                         fecha_actualizacion = NOW()
                     WHERE id_producto = :id_producto'
                );
                $sentencia->execute([
                    ':nombre_producto' => $datos['nombre_producto'],
                    ':descripcion' => $datos['descripcion'],
                    ':precio' => $datos['precio'],
                    ':precio_anterior' => $datos['precio_anterior'],
                    ':imagen' => $rutaImagen,
                    ':estado' => $datos['estado'],
                    ':oferta' => $datos['oferta'],
                    ':id_producto' => $idProducto,
                ]);

            } else {
                $sentencia = $conexion->prepare(
                    'INSERT INTO producto
                     (nombre_producto, descripcion, precio, precio_anterior, imagen, estado, oferta, fecha_creacion, fecha_actualizacion)
                     VALUES
                     (:nombre_producto, :descripcion, :precio, :precio_anterior, :imagen, :estado, :oferta, NOW(), NOW())'
                );
                $sentencia->execute([
                    ':nombre_producto' => $datos['nombre_producto'],
                    ':descripcion' => $datos['descripcion'],
                    ':precio' => $datos['precio'],
                    ':precio_anterior' => $datos['precio_anterior'],
                    ':imagen' => $rutaImagen,
                    ':estado' => $datos['estado'],
                    ':oferta' => $datos['oferta'],
                ]);

                $idProducto = (int) $conexion->lastInsertId();
            }

            if ($debeProcesarVariantes) {
                $conexion->prepare('DELETE FROM producto_variante WHERE id_producto = :id_producto')->execute([':id_producto' => $idProducto]);

                foreach ($variantesNormalizadas as $varianteRecibida) {
                    $sentenciaVariante = $conexion->prepare(
                        'INSERT INTO producto_variante (id_producto, talle, stock, sku, estado, fecha_creacion, fecha_actualizacion)
                         VALUES (:id_producto, :talle, :stock, :sku, :estado, NOW(), NOW())'
                    );
                    $sentenciaVariante->execute([
                        ':id_producto' => $idProducto,
                        ':talle' => $varianteRecibida['talle'],
                        ':stock' => $varianteRecibida['stock'],
                        ':sku' => $varianteRecibida['sku'] !== '' ? $varianteRecibida['sku'] : null,
                        ':estado' => 'activo',
                    ]);
                }
            }

            $conexion->commit();
            guardar_flash('mensaje_exito', $esEdicion ? 'Producto editado.' : 'Producto creado.');
            redirigir('/admin/productos.php');
        } catch (Throwable $ex) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }

            registrar_error_sistema('Error al guardar producto', $ex->getMessage());
            $errores[] = 'No se pudo guardar el producto.';
        }
    }
}

require_once __DIR__ . '/../includes/cabecera_admin.php';
?>

<header class="encabezado-panel">
    <h1><?php echo $esEdicion ? 'Editar producto' : 'Alta de Producto'; ?></h1>
    <p><?php echo $esEdicion ? 'Actualizar información existente' : 'Agregar nuevas remeras al inventario'; ?></p>
</header>

<section class="panel-seccion">
    <?php foreach ($errores as $error): ?>
        <div class="contenido-vacio-admin mensaje-vacio--error u-mb-18 u-text-left"><?php echo sanear_texto($error); ?></div>
    <?php endforeach; ?>

    <form class="formulario-producto" method="post" enctype="multipart/form-data" <?php echo $esEdicion ? 'data-validar-variantes-stock' : ''; ?> data-formulario-oferta data-formulario-producto data-producto-id="<?php echo (int) $idProducto; ?>">
        <div class="grupo-campo">
            <label for="nombre_producto">Nombre</label>
            <input class="campo-texto" id="nombre_producto" type="text" name="nombre_producto" value="<?php echo sanear_texto($datos['nombre_producto'] ?? ''); ?>" required placeholder="Ej: Remera Básica Negra">
        </div>

        <div class="grupo-campo">
            <label for="descripcion">Descripción</label>
            <textarea class="campo-textarea" id="descripcion" name="descripcion" required placeholder="Descripción detallada del producto..."><?php echo sanear_texto($datos['descripcion'] ?? ''); ?></textarea>
        </div>

        <div class="rejilla-campos">
            <div class="grupo-campo">
                <label for="precio_anterior">Precio normal</label>
                <input class="campo-texto" id="precio_anterior" type="number" name="precio_anterior" step="0.01" min="0" value="<?php echo sanear_texto((string) ($datos['precio_anterior'] ?? '')); ?>" required data-campo-precio-normal>
            </div>
            <div class="grupo-campo">
                <label for="precio">Precio con descuento</label>
                <input class="campo-texto" id="precio" type="number" name="precio" step="0.01" min="0" value="<?php echo sanear_texto((string) ($datos['precio'] ?? '')); ?>" data-campo-precio-descuento <?php echo !empty($datos['oferta']) ? '' : 'disabled'; ?>>
                <p class="tarjeta-formulario__texto u-mt-4 u-mb-0" data-vista-descuento></p>
                <p class="campo-error" data-error-precio-descuento hidden></p>
            </div>
        </div>

        <div class="rejilla-campos">
            <div class="grupo-campo">
                <label for="estado">Estado</label>
                <select class="campo-select" id="estado" name="estado">
                    <option value="disponible" <?php echo ($datos['estado'] ?? '') === 'disponible' ? 'selected' : ''; ?>>Disponible</option>
                    <option value="inactivo" <?php echo ($datos['estado'] ?? '') === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                </select>
            </div>
        </div>

        <div class="casilla-aceptacion campos-aceptacion--espaciado">
            <input type="checkbox" name="oferta" <?php echo !empty($datos['oferta']) ? 'checked' : ''; ?> data-campo-oferta>
            <span>Producto en oferta</span>
        </div>

        <div class="grupo-campo">
            <label for="imagen">Imagen</label>
            <input class="campo-texto" id="imagen" type="file" name="imagen" accept="image/*">
            <?php if (!empty($datos['imagen'])): ?>
                <div class="vista-imagen-actual">
                    <p class="tarjeta-formulario__texto u-mt-8 u-mb-8">Imagen actual</p>
                    <img class="vista-imagen-actual__img" src="<?php echo obtener_ruta_imagen_producto($datos['imagen'] ?? null); ?>" alt="Imagen actual del producto">
                </div>
            <?php endif; ?>
        </div>

        <?php if ($esEdicion): ?>
        <div class="fila-variantes">
            <h3>Variantes por talle</h3>

            <?php if ($variantesFormulario === []): ?>
                <div class="contenido-vacio-admin">Este producto todavía no tiene variantes creadas con SKU.</div>
            <?php else: ?>
                <div class="variante-visor" data-variante-visor>
                    <div class="grupo-campo">
                        <label for="variante_activa">Talle</label>
                        <select class="campo-select" id="variante_activa" data-variante-selector>
                            <?php foreach ($variantesFormulario as $indice => $variante): ?>
                                <option
                                    value="<?php echo (int) $indice; ?>"
                                    data-talle="<?php echo sanear_texto((string) ($variante['talle'] ?? '')); ?>"
                                    data-stock="<?php echo (int) ($variante['stock'] ?? 0); ?>"
                                    data-sku="<?php echo sanear_texto((string) ($variante['sku'] ?? '')); ?>"
                                    <?php echo $indice === 0 ? 'selected' : ''; ?>
                                ><?php echo sanear_texto((string) ($variante['talle'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="rejilla-campos">
                        <div class="grupo-campo">
                            <span class="etiqueta-campo">Stock actual</span>
                            <p class="valor-variante" data-variante-stock-texto>0</p>
                        </div>
                        <div class="grupo-campo">
                            <span class="etiqueta-campo">SKU</span>
                            <p class="valor-variante" data-variante-sku-texto>Sin SKU</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="acciones-fila acciones-fila--arriba">
            <button class="boton-principal boton-principal--verde" type="submit"><?php echo $esEdicion ? 'Guardar cambios' : 'Crear producto'; ?></button>
            <a class="boton-secundario boton-secundario--gris" href="/admin/productos.php">Cancelar</a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/pie_admin.php'; ?>
