    </main>
    <!-- Pie publico: contacto, documentos legales y modales compartidas. -->
    <footer class="pie-publico" id="ayuda">
        <div class="contenedor pie-publico__contenido">
            <section>
                <h3>NeutralTees</h3>
                <p>Remeras unisex de alta calidad, con estética limpia y minimalista.</p>
            </section>
            <section>
                <h3>Contacto</h3>
                <p>Email: contacto.neutraltees@gmail.com</p>
                <p>Teléfono: +54 11 41474412</p>
            </section>
            <section>
                <h3>Enlaces</h3>
                <p><button class="boton-documento-legal" type="button" data-abrir-documento-legal data-documento-url="/src/Terminos-condiciones-NeutralTees.pdf" data-documento-titulo="Términos y condiciones">Términos y condiciones</button></p>
                <p><button class="boton-documento-legal" type="button" data-abrir-documento-legal data-documento-url="/src/Politicas_privacidad-NeutralTees.pdf" data-documento-titulo="Política de privacidad">Política de privacidad</button></p>
            </section>
        </div>
        <div class="pie-publico__cierre">© 2026 NeutralTees. Todos los derechos reservados.</div>
    </footer>

    <div class="modal-documento" data-modal-documento aria-hidden="true">
        <div class="modal-documento__tarjeta" role="dialog" aria-modal="true" aria-labelledby="titulo-modal-documento">
            <div class="modal-documento__barra">
                <h2 class="modal-documento__titulo" id="titulo-modal-documento" data-modal-documento-titulo>Documento</h2>
                <button class="modal-documento__cerrar" type="button" data-modal-documento-cerrar>Cerrar</button>
            </div>
            <iframe class="modal-documento__visor" data-modal-documento-iframe title="Documento legal" loading="lazy" referrerpolicy="no-referrer"></iframe>
        </div>
    </div>

    <div class="modal-confirmacion" data-modal-confirmacion aria-hidden="true">
        <div class="modal-confirmacion__tarjeta" role="dialog" aria-modal="true" aria-labelledby="titulo-modal-confirmacion">
            <h2 class="modal-confirmacion__titulo" id="titulo-modal-confirmacion">Confirmar acción</h2>
            <p class="modal-confirmacion__texto" data-modal-texto>¿Querés continuar?</p>
            <div class="modal-confirmacion__acciones">
                <button class="modal-confirmacion__boton modal-confirmacion__boton--cancelar" type="button" data-modal-cancelar>Cancelar</button>
                <button class="modal-confirmacion__boton modal-confirmacion__boton--confirmar" type="button" data-modal-confirmar>Confirmar</button>
            </div>
        </div>
    </div>
</body>
</html>
