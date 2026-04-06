<x-filament-panels::page>
    <div class="fi-bp-ios-doc w-full max-w-none space-y-8 pb-16">
        <header class="fi-bp-ios-doc__hero">
            <p class="fi-bp-ios-doc__hero-eyebrow">Ayuda</p>
            <h1 class="fi-bp-ios-doc__hero-title">Cómo usar su panel de aliado</h1>
            <p class="fi-bp-ios-doc__hero-lead">
                Esta guía está pensada para personas que usan el panel todos los días, con o sin experiencia previa.
                Aquí verá qué significa cada zona, qué ocurre al pulsar y qué partes solo aplican si su empresa trabaja con crédito.
            </p>
        </header>

        <nav class="fi-bp-ios-doc__toc" aria-label="Contenidos">
            <p class="fi-bp-ios-doc__toc-title">Ir a una sección</p>
            <ul class="fi-bp-ios-doc__toc-list">
                <li><a href="#inicio" class="fi-bp-ios-doc__toc-link">Tablero de inicio</a></li>
                <li><a href="#calificar" class="fi-bp-ios-doc__toc-link">Calificar la entrega</a></li>
                <li><a href="#creditos" class="fi-bp-ios-doc__toc-link">Con crédito y sin crédito</a></li>
                <li><a href="#pedidos" class="fi-bp-ios-doc__toc-link">Lista de pedidos</a></li>
                <li><a href="#clics" class="fi-bp-ios-doc__toc-link">Clics en la tabla y paneles</a></li>
                <li><a href="#detalle" class="fi-bp-ios-doc__toc-link">Ver un pedido</a></li>
                <li><a href="#historico" class="fi-bp-ios-doc__toc-link">Histórico de movimientos</a></li>
                <li><a href="#consejos" class="fi-bp-ios-doc__toc-link">Consejos rápidos</a></li>
            </ul>
        </nav>

        <section id="calificar" class="fi-bp-ios-doc__section fi-bp-ios-doc__section--highlight" aria-labelledby="fi-bp-doc-calificar-heading">
            <div class="fi-bp-ios-doc__rating-callout">
                <div class="fi-bp-ios-doc__rating-callout-icon" aria-hidden="true">
                    <x-filament::icon icon="heroicon-o-star" class="size-6" />
                </div>
                <h2 id="fi-bp-doc-calificar-heading" class="fi-bp-ios-doc__rating-callout-title">Por qué conviene calificar el servicio de entrega</h2>
                <p>
                    Cuando un pedido queda <strong>finalizado</strong>, puede dejar una calificación con estrellas (1 a 5) desde
                    <strong>Acciones → Calificar entrega</strong> en la tabla o con el botón <strong>Calificar servicio de entrega</strong> en la vista del pedido.
                </p>
                <p>
                    Esa valoración <strong>no es solo opinión</strong>: en Farmadoc se usa como <strong>métrica</strong> para ver cómo se percibe el reparto,
                    detectar desajustes y <strong>tomar decisiones</strong> (formación, rutas, tiempos de respuesta y mejoras del servicio).
                    Cuanto más pedidos finalizados tengan calificación, más fiables son los indicadores y las acciones de mejora.
                </p>
                <p>
                    Si la entrega fue buena o mala, <strong>califíquela igualmente</strong>: las dos informaciones ayudan al equipo a corregir o reforzar lo que haga falta.
                    Puede <strong>cambiar la calificación más adelante</strong> si lo considera necesario.
                </p>
            </div>
        </section>

        <section id="inicio" class="fi-bp-ios-doc__section">
            <h2 class="fi-bp-ios-doc__h2">Tablero de inicio</h2>
            <p class="fi-bp-ios-doc__p">
                Al entrar verá un resumen en tarjetas con estilo claro, tipo iPhone: fondo suave, bordes redondeados y números grandes.
                No tiene que configurar nada: los datos son solo de <strong>su compañía</strong>.
            </p>
            <ul class="fi-bp-ios-doc__list">
                <li><strong>Bienvenida:</strong> confirma que está en la cuenta correcta.</li>
                <li><strong>Pedidos por estado:</strong> tres cajas — Pendiente, En proceso y Finalizado — con el número de pedidos en cada etapa. Puede pulsar una caja para ir al listado de pedidos.</li>
                <li><strong>Gráfico mensual:</strong> muestra solo pedidos ya finalizados del año en curso. Las barras suman el total en dólares (US$) por mes; la línea teal indica cuántos pedidos finalizados hubo cada mes.</li>
            </ul>
            @include('filament.business-partners.pages.partials.partner-doc-diagram-dashboard', ['hasAssignedCredit' => $hasAssignedCredit])
            <p class="fi-bp-ios-doc__caption">El dibujo resume el orden; en pantalla los bloques tienen el mismo estilo visual que el resto del panel.</p>
        </section>

        <section id="creditos" class="fi-bp-ios-doc__section">
            <h2 class="fi-bp-ios-doc__h2">Con crédito y sin crédito</h2>
            <p class="fi-bp-ios-doc__p">
                Farmadoc distingue dos situaciones. Si no está seguro, mire el menú lateral: lo que no ve, no aplica a su empresa.
            </p>
            <div class="fi-bp-ios-doc__compare">
                <div class="fi-bp-ios-doc__compare-card fi-bp-ios-doc__compare-card--on">
                    <h3 class="fi-bp-ios-doc__h3">Empresa con línea de crédito</h3>
                    <ul class="fi-bp-ios-doc__list fi-bp-ios-doc__list--compact">
                        <li>En el inicio aparece la tarjeta <strong>«Crédito disponible»</strong> con tope, consumido y saldo.</li>
                        <li>Esa tarjeta se refresca sola cada pocos segundos (útil cuando un pedido pasa a «En proceso» y consume cupo).</li>
                        <li>En el menú <strong>Operaciones</strong> verá además <strong>«Histórico de movimientos»</strong> (solo lectura) con el detalle contable de su línea.</li>
                    </ul>
                </div>
                <div class="fi-bp-ios-doc__compare-card fi-bp-ios-doc__compare-card--off">
                    <h3 class="fi-bp-ios-doc__h3">Empresa sin línea de crédito</h3>
                    <ul class="fi-bp-ios-doc__list fi-bp-ios-doc__list--compact">
                        <li>No verá la tarjeta de crédito en el inicio.</li>
                        <li>No aparecerá la opción <strong>«Histórico de movimientos»</strong> en el menú.</li>
                        <li>El resto — pedidos, gráficos y estados — funciona igual.</li>
                    </ul>
                </div>
            </div>
            @if ($hasAssignedCredit)
                <p class="fi-bp-ios-doc__note fi-bp-ios-doc__note--info">
                    <span class="fi-bp-ios-doc__note-label">Su cuenta ahora:</span>
                    según nuestros datos, <strong>sí</strong> tiene línea de crédito asignada. Debería ver la tarjeta de cupo y el histórico si Farmadoc los habilitó para usted.
                </p>
            @else
                <p class="fi-bp-ios-doc__note fi-bp-ios-doc__note--muted">
                    <span class="fi-bp-ios-doc__note-label">Su cuenta ahora:</span>
                    no detectamos línea de crédito activa para su usuario. Si cree que es un error, contacte a su contacto en Farmadoc.
                </p>
            @endif
        </section>

        <section id="pedidos" class="fi-bp-ios-doc__section">
            <h2 class="fi-bp-ios-doc__h2">Lista de pedidos</h2>
            <p class="fi-bp-ios-doc__p">
                En <strong>Operaciones → Pedidos</strong> está la tabla principal. Cada fila es un pedido de su compañía.
            </p>
            <ul class="fi-bp-ios-doc__list">
                <li><strong>Cliente y sucursal:</strong> texto fijo; sirve para ubicar el pedido.</li>
                <li><strong>Estado:</strong> colores tipo semáforo — rojo pendiente, amarillo en proceso, verde finalizado.</li>
                <li><strong>Total:</strong> monto del pedido.</li>
                <li><strong>Filtros arriba de la tabla:</strong> puede filtrar por estado o tipo de convenio sin borrar datos; son solo vistas.</li>
            </ul>
        </section>

        <section id="clics" class="fi-bp-ios-doc__section">
            <h2 class="fi-bp-ios-doc__h2">Clics en la tabla: qué se abre y dónde</h2>
            <p class="fi-bp-ios-doc__p">
                Parte de la información no está en columnas abiertas: aparece al pulsar ciertas celdas. Eso abre un <strong>panel lateral</strong> (desliza desde la derecha, estilo iOS) o un cuadro de diálogo, sin cambiar de página.
            </p>
            @include('filament.business-partners.pages.partials.partner-doc-diagram-table')
            @include('filament.business-partners.pages.partials.partner-doc-diagram-slideover')
            <dl class="fi-bp-ios-doc__dl">
                <div class="fi-bp-ios-doc__dl-row">
                    <dt>Nº pedido (badge amarillo)</dt>
                    <dd>
                        Abre información de entrega. Si el pedido está <strong>en proceso</strong>, verá nombre, correo, documento, teléfono y foto del repartidor cuando Farmadoc los haya registrado.
                        Si está <strong>finalizado</strong>, verá el mensaje de pedido completado y la fecha de entrega cuando exista.
                        En otros estados, el panel explica que los datos del repartidor estarán disponibles al pasar a «En proceso».
                    </dd>
                </div>
                <div class="fi-bp-ios-doc__dl-row">
                    <dt>Ítems (número en la columna)</dt>
                    <dd>
                        Lista cada producto pedido, referencia (SKU) y cantidad. Si el pedido es al mayor, las cantidades se muestran en <strong>cajas</strong>; si es al detalle, en <strong>unidades</strong>.
                    </dd>
                </div>
                <div class="fi-bp-ios-doc__dl-row">
                    <dt>Menú «Acciones» (tres puntos o botones)</dt>
                    <dd>
                        <ul class="fi-bp-ios-doc__list fi-bp-ios-doc__list--compact">
                            <li>
                                <strong>Calificar entrega:</strong> solo en pedidos <strong>finalizados</strong>. Guarda estrellas (1 a 5) sobre el delivery.
                                Le pedimos que lo haga siempre que pueda: esos datos alimentan <strong>métricas internas</strong> y ayudan a Farmadoc a
                                <strong>medir y mejorar</strong> el servicio (capacitación, seguimiento y ajustes operativos). Puede actualizar la calificación cuando quiera.
                            </li>
                            <li><strong>Ver comprobante:</strong> solo si en su momento subió archivo de pago en efectivo. Abre el comprobante en el mismo panel lateral.</li>
                            <li><strong>Ver pedido:</strong> página de detalle con toda la ficha.</li>
                            <li><strong>Editar:</strong> solo mientras el pedido está <strong>pendiente</strong>; después ya no se puede modificar desde aquí.</li>
                        </ul>
                    </dd>
                </div>
                <div class="fi-bp-ios-doc__dl-row">
                    <dt>Clic en el resto de la fila</dt>
                    <dd>
                        Lo lleva a la vista de detalle del pedido (como «Ver pedido»). Si intenta abrir el número de pedido o ítems, pulse solo sobre esa celda para no mezclar acciones.
                    </dd>
                </div>
            </dl>
        </section>

        <section id="detalle" class="fi-bp-ios-doc__section">
            <h2 class="fi-bp-ios-doc__h2">Pantalla «Ver pedido»</h2>
            <p class="fi-bp-ios-doc__p">
                En la parte superior verá botones según permisos. Si el pedido está finalizado, aparece <strong>«Calificar servicio de entrega»</strong>
                (igual que en la tabla): úselo para que su experiencia quede registrada en las métricas de calidad de entrega.
                El botón <strong>Editar</strong> solo se muestra en pendientes.
            </p>
        </section>

        <section id="historico" class="fi-bp-ios-doc__section">
            <h2 class="fi-bp-ios-doc__h2">Histórico de movimientos</h2>
            @if ($hasAssignedCredit)
                <p class="fi-bp-ios-doc__p">
                    Si su menú incluye esta sección, es un listado de solo lectura de movimientos vinculados a su línea de crédito.
                    Use filtros y búsqueda de la tabla igual que en pedidos. Si no ve el menú, su perfil no tiene crédito asignado o no está habilitado.
                </p>
            @else
                <p class="fi-bp-ios-doc__p">
                    Esta sección <strong>no está en su menú</strong> porque su usuario no tiene línea de crédito asignada en el sistema.
                    Si en el futuro le asignan crédito, puede aparecer automáticamente sin que usted instale nada.
                </p>
            @endif
        </section>

        <section id="consejos" class="fi-bp-ios-doc__section">
            <h2 class="fi-bp-ios-doc__h2">Consejos rápidos</h2>
            <ul class="fi-bp-ios-doc__list">
                <li>Tras cada entrega finalizada, <strong>califique el servicio</strong>: contribuye a estadísticas útiles para mejorar el reparto (véase la sección <a href="#calificar" class="fi-bp-ios-doc__inline-link">Calificar la entrega</a>).</li>
                <li>Pase el cursor sobre el encabezado de una columna con icono de ojo: muchas columnas extra están ocultas por defecto y puede activarlas.</li>
                <li>Los números del tablero y del listado se actualizan solos; si acaba de cambiar un estado, espere unos segundos.</li>
                <li>Si una ventana lateral no cierra, use el botón <strong>Listo</strong>, <strong>Cerrar</strong> o la X según lo que muestre el panel.</li>
                <li>Para crear un pedido nuevo, use el botón de crear en la lista de pedidos (si su rol lo permite).</li>
            </ul>
        </section>
    </div>
</x-filament-panels::page>
