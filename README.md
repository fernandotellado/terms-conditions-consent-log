# Terms & Conditions Consent Log

> 🇬🇧 English (you are here) · [🇪🇸 Versión en español](#-versión-en-español)

[![License: GPL-2.0-or-later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4)
![WooCommerce](https://img.shields.io/badge/WooCommerce-compatible-7f54b3)

A WordPress plugin that turns the WooCommerce terms checkbox into defensible GDPR evidence.

WooCommerce stores a boolean for the terms checkbox, but article 7.1 of the GDPR asks for more: timestamp, IP, user agent, the document version in force at that moment and the exact text the customer was shown. This plugin captures all of it in a dedicated indexed table, seals each record with a SHA-256 hash so any later edit is detectable, integrates with the native WordPress Privacy Tools and produces a one-page printable A4 certificate per record (your browser exports it to PDF in one click).

## Features

- **Captures** the WooCommerce native terms checkbox at checkout — timestamp UTC, IP, user agent, document version and the exact text shown to the customer.
- **Custom indexed table** — no `wp_postmeta` bloat.
- **Tamper-evident**: every record is sealed with a SHA-256 hash of the accepted text the moment it is written. Any later change to the stored text breaks the seal and is reported as `TAMPERED` in the records list, in the order metabox and on the certificate view.
- **Printable A4 certificate** per record, with a built-in "Print / Save as PDF" button — your browser exports the certificate to PDF natively, no external library bundled.
- **Native Privacy Tools integration** — `Tools > Export Personal Data` and `Tools > Erase Personal Data` both include consent records. Erasure anonymises rather than deletes, since the record itself is the lawful basis to keep the proof.
- **Both texts are optional** — the checkbox text and the pre-checkout informational paragraph default to empty, so a fresh install respects the WooCommerce native text. Whatever the customer is actually shown is what gets stored.
- **Live partial-match filters** (email, order, date range, type) with debounced auto-submit and filtered CSV export.
- **Configurable retention** with one-click anonymise (records kept; PII scrubbed). Anonymise filtered results from the Records tab.
- **Optional consent line in order emails** (admin and customer, both off by default).
- **Optional plugin-data deletion on uninstall** (off by default — uninstalling does not destroy consent evidence unless you explicitly opt in).
- **HPOS** (custom order tables) compatible.
- **Public API** — `tccl_save_consent()` to log consents from anywhere (contact forms, custom flows, REST endpoints).
- **Translation ready** — Spanish (es_ES) bundled.

## Installation

### From a release ZIP

1. Download the ZIP from the [latest Release](https://github.com/fernandot/terms-conditions-consent-log/releases/latest).
2. WordPress admin → Plugins → Add New → Upload Plugin.
3. Activate.

### From a clone

```bash
cd wp-content/plugins
git clone https://github.com/fernandot/terms-conditions-consent-log.git
```

Activate from `Plugins` in the admin.

### Coming soon

WordPress.org listing.

## Quick start

1. After activation, go to `WooCommerce > Consent log`.
2. By default, both texts are empty: WooCommerce shows the native checkbox text, and the plugin records exactly that text with each consent.
3. Optionally edit the checkbox text and / or add a pre-checkout informational paragraph (GDPR notice). Both fields accept basic HTML.
4. Set the document version (format `MAJOR.MINOR-YYYY-MM-DD`) — the plugin auto-bumps it when the checkbox text changes.
5. Place a test order. Open the order edit screen: the metabox shows the consent summary and the integrity badge.
6. Open `WooCommerce > Consent log > Records` to see the log, filter, export, verify integrity or open the printable certificate of any record.

## Screenshots

Stored under [`screenshots/`](screenshots/) and excluded from the WordPress.org release ZIP.

| | |
|:---:|:---:|
| ![Records list](screenshots/01-records-list.jpg) | ![Settings tab](screenshots/02-settings.jpg) |
| Records list with live filters, integrity column and CSV export. | Settings tab with editable texts, version control, retention, email options and uninstall control. |
| ![Order metabox](screenshots/03-order-metabox.jpg) | ![Consent column on orders list](screenshots/04-orders-column.jpg) |
| Order metabox with consent summary, integrity badge and certificate button. | Consent column on the orders list. |
| ![Printable certificate](screenshots/05-certificate.jpg) | ![Exported CSV](screenshots/06-csv.jpg) |
| Printable A4 certificate (the browser saves it as PDF). | Exported CSV file with metadata header and translated column names. |

## FAQ

**Does it support the WooCommerce Block Checkout?**
Not yet. Classic checkout is fully supported. Block Checkout is on the roadmap.

**Where is the data stored?**
In a dedicated, indexed table `wp_tccl_consents`. Order meta keys (`_tccl_terms_accepted`, `_tccl_terms_version`, `_tccl_recorded_at`) mirror the summary so the order edit screen does not need to query the log table.

**Can I log consents from contact forms?**
Yes. `tccl_save_consent()` is a public function — pass the email, type, version, text and value, and the plugin handles IP, user agent, hashing and timestamping. See `readme.txt` for a Contact Form 7 example.

**What happens if I uninstall the plugin?**
By default, nothing is deleted. The consent evidence survives uninstall. Only if you explicitly enable "Delete all data on uninstall" in Settings does the table get dropped.

**Does the plugin trust forwarded IP headers?**
No. It reads `REMOTE_ADDR` only — forwarded headers can be spoofed without a verified reverse proxy. If your hosting puts the proxy IP in `REMOTE_ADDR`, you will record the proxy IP for every customer.

**Is the certificate a real PDF?**
The plugin renders a one-page A4 view with print-optimised CSS and a "Print / Save as PDF" button. Modern browsers (Chrome, Safari, Firefox, Edge) export that view to a real PDF natively — same fidelity as a server-side library would produce, with the added benefit that it respects your site's language and fonts. No external library bundled, so the plugin stays small.

## Roadmap

- WooCommerce Block Checkout support.
- Multiple configurable consent checkboxes (privacy, marketing, age verification, custom).
- Version history and diff viewer for the legal text.
- REST API endpoints (read and write).
- WP-CLI commands.
- Form-builder helpers (Contact Form 7, Gravity Forms, WPForms).
- Continuous integration (WPCS, Plugin Check, PHPUnit).
- More translations (fr_FR, de_DE, it_IT, pt_BR).

## Contributing

Issues, ideas and pull requests are welcome. Please open an issue first if you plan a non-trivial change.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

---

## 🇪🇸 Versión en español

> [🇬🇧 English version](#terms--conditions-consent-log) · 🇪🇸 Español (aquí estás)

Plugin de WordPress que convierte el checkbox de términos de WooCommerce en una prueba de consentimiento defendible bajo el RGPD.

WooCommerce guarda solo un booleano del checkbox, pero el artículo 7.1 del RGPD pide más: fecha y hora, IP, user-agent, versión del documento en vigor en ese momento y el texto exacto que vio el cliente. Este plugin captura todo eso en una tabla propia indexada, sella cada registro con un hash SHA-256 para detectar cualquier modificación posterior, se integra con las Herramientas de Privacidad nativas de WordPress y genera un certificado A4 imprimible por registro (tu navegador lo guarda como PDF en un clic).

### Características

- **Captura** el checkbox nativo de términos de WooCommerce en el checkout — fecha y hora UTC, IP, user-agent, versión del documento y el texto exacto que se mostró al cliente.
- **Tabla custom indexada** — sin saturar `wp_postmeta`.
- **A prueba de manipulación**: cada registro se sella con un hash SHA-256 del texto aceptado en el momento del consentimiento. Cualquier cambio posterior del texto rompe el sello y se reporta como `MANIPULADO` en el listado, en el metabox del pedido y en la vista del certificado.
- **Certificado A4 imprimible** por registro, con botón "Imprimir / Guardar como PDF" — el navegador lo exporta a PDF de forma nativa, sin librerías externas en el plugin.
- **Integración nativa con Herramientas de Privacidad** — `Herramientas > Exportar datos personales` y `Herramientas > Borrar datos personales` incluyen los consentimientos. El borrado anonimiza (no elimina), porque el propio registro es la base legítima para conservar la prueba.
- **Ambos textos son opcionales** — el del checkbox y el párrafo informativo previo al checkout vienen vacíos por defecto, así una instalación nueva respeta el texto nativo de WooCommerce. Lo que ve el cliente es lo que se guarda.
- **Filtros de búsqueda parcial en vivo** (email, pedido, rango de fechas, tipo) con auto-submit con debounce y exportación CSV filtrada.
- **Retención configurable** con anonimización en un clic (los registros se conservan; los datos personales se borran). Anonimización por filtro desde la pestaña de Registros.
- **Línea de consentimiento opcional en los emails** del pedido (admin y cliente, ambas desactivadas por defecto).
- **Eliminación de datos al desinstalar opcional** (desactivado por defecto — desinstalar no destruye la prueba salvo que lo actives explícitamente).
- **Compatible con HPOS** (custom order tables).
- **API pública** — `tccl_save_consent()` para registrar consentimientos desde cualquier sitio (formularios, flujos custom, endpoints REST).
- **Listo para traducción** — incluye traducción al español (es_ES).

### Instalación

#### Desde un ZIP de Release

1. Descarga el ZIP desde la [última Release](https://github.com/fernandot/terms-conditions-consent-log/releases/latest).
2. Admin de WordPress → Plugins → Añadir nuevo → Subir plugin.
3. Activa.

#### Desde un clon

```bash
cd wp-content/plugins
git clone https://github.com/fernandot/terms-conditions-consent-log.git
```

Activa desde `Plugins` en el admin.

#### Próximamente

Listado en WordPress.org.

### Inicio rápido

1. Tras activar, ve a `WooCommerce > Registro de consentimientos`.
2. Por defecto los dos textos están vacíos: WooCommerce muestra el texto nativo del checkbox, y el plugin guarda exactamente ese texto con cada consentimiento.
3. Opcionalmente edita el texto del checkbox y/o añade un párrafo informativo previo al checkout (aviso RGPD). Ambos campos aceptan HTML básico.
4. Indica la versión del documento (formato `MAJOR.MINOR-YYYY-MM-DD`) — el plugin la incrementa automáticamente cuando cambia el texto del checkbox.
5. Haz un pedido de prueba. Abre la edición del pedido: el metabox muestra el resumen del consentimiento y el indicador de integridad.
6. Abre `WooCommerce > Registro de consentimientos > Registros` para ver el log, filtrar, exportar, verificar integridad o abrir el certificado imprimible de un registro.

### Capturas

Guardadas en [`screenshots/`](screenshots/) y excluidas del ZIP que se publica en WordPress.org.

| | |
|:---:|:---:|
| ![Listado de registros](screenshots/01-records-list.jpg) | ![Pestaña Ajustes](screenshots/02-settings.jpg) |
| Listado de registros con filtros en vivo, columna de integridad y exportación CSV. | Pestaña Ajustes con textos editables, control de versión, retención, emails y desinstalación. |
| ![Metabox del pedido](screenshots/03-order-metabox.jpg) | ![Columna Consentimiento en pedidos](screenshots/04-orders-column.jpg) |
| Metabox del pedido con resumen, badge de integridad y botón al certificado. | Columna Consentimiento en el listado de pedidos. |
| ![Certificado imprimible](screenshots/05-certificate.jpg) | ![CSV exportado](screenshots/06-csv.jpg) |
| Certificado A4 imprimible (el navegador lo guarda como PDF). | CSV exportado con cabecera de metadatos y columnas traducidas. |

### Preguntas frecuentes

**¿Soporta el Checkout de bloques de WooCommerce?**
Aún no. El checkout clásico está totalmente soportado. El de bloques está en el roadmap.

**¿Dónde se guardan los datos?**
En una tabla propia indexada `wp_tccl_consents`. Los meta del pedido (`_tccl_terms_accepted`, `_tccl_terms_version`, `_tccl_recorded_at`) duplican el resumen para que la pantalla de edición del pedido no consulte la tabla de log.

**¿Puedo registrar consentimientos desde formularios de contacto?**
Sí. `tccl_save_consent()` es una función pública — pasa el email, tipo, versión, texto y valor, y el plugin se encarga de IP, user-agent, hashing y timestamp. En `readme.txt` hay un ejemplo para Contact Form 7.

**¿Qué pasa si desinstalo el plugin?**
Por defecto no se borra nada. La prueba del consentimiento sobrevive a la desinstalación. Solo si activas explícitamente "Borrar todos los datos al desinstalar" en Ajustes se elimina la tabla.

**¿El plugin se fía de las cabeceras IP reenviadas?**
No. Solo lee `REMOTE_ADDR` — las cabeceras reenviadas se pueden falsear sin un proxy inverso verificado. Si tu hosting deja la IP del proxy en `REMOTE_ADDR`, vas a guardar la del proxy para todos los clientes.

**¿El certificado es un PDF real?**
El plugin renderiza una vista A4 de una página con CSS optimizado para impresión y un botón "Imprimir / Guardar como PDF". Los navegadores modernos (Chrome, Safari, Firefox, Edge) exportan esa vista a un PDF de verdad, de forma nativa — con la misma fidelidad que daría una librería en servidor, y con la ventaja de respetar el idioma y las tipografías de tu sitio. El plugin no carga librerías externas, así que se mantiene ligero.

### Roadmap

- Soporte del Checkout de bloques de WooCommerce.
- Múltiples checkboxes de consentimiento configurables (privacidad, marketing, edad, personalizados).
- Histórico de versiones y visor de diff del texto legal.
- Endpoints REST API (lectura y escritura).
- Comandos de WP-CLI.
- Helpers para form builders (Contact Form 7, Gravity Forms, WPForms).
- Integración continua (WPCS, Plugin Check, PHPUnit).
- Más traducciones (fr_FR, de_DE, it_IT, pt_BR).

### Contribuir

Issues, ideas y pull requests son bienvenidos. Si planeas un cambio no trivial, abre una issue antes para alinear.

### Licencia

GPL-2.0-or-later. Ver [LICENSE](LICENSE).
