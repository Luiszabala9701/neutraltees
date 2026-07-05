<?php

/**
 * Modulo: ayuda publica.
 * Responsabilidad: guiar al cliente por las funciones principales de la tienda.
 */

require_once __DIR__ . '/config/conexion_DB.php';
$conexion = obtener_conexion_db();

require_once __DIR__ . '/includes/cabecera_publica.php';
?>

<section class="tarjeta-formulario">
    <h1 class="tarjeta-formulario__titulo">Ayuda</h1>
    <p class="tarjeta-formulario__texto">Guia rapida para usar NeutralTees como cliente.</p>

    <div class="lista-admin">
        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Crear una cuenta</h2>
            <p class="tarjeta-resumen__texto">Entra al icono de perfil, elegi crear cuenta y completa tus datos. Vas a necesitar nombre, apellido, correo, contrasena y aceptar los documentos legales.</p>
        </article>

        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Verificar el correo</h2>
            <p class="tarjeta-resumen__texto">Despues del registro, te enviamos un codigo de 6 numeros por email. Escribilo en la pantalla de verificacion antes de que venza.</p>
        </article>

        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Iniciar sesion</h2>
            <p class="tarjeta-resumen__texto">Usa tu correo y contrasena. Si todavia no verificaste tu email, primero vas a tener que ingresar el codigo que recibiste por correo.</p>
        </article>

        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Recuperar contrasena</h2>
            <p class="tarjeta-resumen__texto">Desde el acceso o desde Seguridad podes pedir un codigo por mail. Primero confirmas el codigo y despues cargas una nueva contrasena.</p>
        </article>

        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Buscar productos</h2>
            <p class="tarjeta-resumen__texto">Podes navegar el catalogo, entrar a Ofertas o usar el buscador para encontrar una remera por nombre o descripcion.</p>
        </article>

        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Ver un producto</h2>
            <p class="tarjeta-resumen__texto">Presiona Ver producto para consultar imagen, descripcion, precio, oferta, talles disponibles y guia de talles.</p>
        </article>

        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Agregar al carrito</h2>
            <p class="tarjeta-resumen__texto">Elegi talle y cantidad desde el detalle del producto. Solo vas a poder sumar unidades disponibles en stock, con un maximo de 10 por producto.</p>
        </article>

        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Modificar el carrito</h2>
            <p class="tarjeta-resumen__texto">Desde el carrito podes aumentar o disminuir cantidades, eliminar productos y revisar el total antes de finalizar la compra.</p>
        </article>

        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Usar cupones</h2>
            <p class="tarjeta-resumen__texto">Con sesion iniciada, abri el menu de perfil y entra a Cupones. Carga el codigo disponible y luego seleccionalo en el checkout.</p>
        </article>

        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Finalizar compra</h2>
            <p class="tarjeta-resumen__texto">Desde el carrito presiona Finalizar compra. En el checkout completa DNI, telefono y metodo de pago: Efectivo o Mercado Pago.</p>
        </article>

        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Confirmacion de pago</h2>
            <p class="tarjeta-resumen__texto">Al confirmar, vas a ver una ventana de procesamiento y luego la compra queda realizada. Tambien recibis un email con el detalle del pedido.</p>
        </article>

        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Ver pedidos</h2>
            <p class="tarjeta-resumen__texto">Entra a Perfil para consultar tus compras realizadas, productos incluidos, totales y estado del pedido.</p>
        </article>

        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Eliminar cuenta</h2>
            <p class="tarjeta-resumen__texto">Con sesion iniciada, abri el menu de perfil y entra a Seguridad. Al final de esa pantalla vas a encontrar la seccion Eliminar cuenta, debajo del formulario para cambiar contraseña. Antes de confirmar vas a ver una ventana de aviso. Si aceptas, la cuenta queda dada de baja y se cierra la sesion.</p>
        </article>

        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Cancelar un pedido</h2>
            <p class="tarjeta-resumen__texto">Desde Perfil podes cancelar una orden mientras no figure como entregada. Antes de cancelar, vas a ver una confirmacion. La orden queda marcada como cancelada y recibis un aviso por email.</p>
        </article>

        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Estados del pedido</h2>
            <p class="tarjeta-resumen__texto">Los pedidos pueden figurar como pendiente, en preparacion, preparado, entregado o cancelado. Cada avance importante se informa por email.</p>
        </article>

        <article class="tarjeta-resumen">
            <h2 class="tarjeta-formulario__texto u-mb-0">Contacto</h2>
            <p class="tarjeta-resumen__texto">Podes escribir a contacto.neutraltees@gmail.com o comunicarte al +54 11 41474412.</p>
        </article>
    </div>
</section>

<?php require_once __DIR__ . '/includes/pie_publico.php'; ?>
