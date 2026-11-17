{{-- 
    GRIFO MUNICHIS
    Correccion UI v1.1
    Alcance:
      - Paginacion compacta.
      - Switches booleanos de formularios.

    Nota tecnica:
    No se usan ::before/::after sobre <input>, porque los pseudo-elementos
    en controles reemplazados no se renderizan de forma consistente.
--}}
<style id="gm-correcciones-paginacion-switches">
    /* ==========================================================
       PAGINACION
       ========================================================== */

    nav[role="navigation"] svg,
    .pagination svg,
    .paginacion svg {
        width: 16px !important;
        height: 16px !important;
        min-width: 16px !important;
        min-height: 16px !important;
        max-width: 16px !important;
        max-height: 16px !important;
        display: block !important;
        flex: 0 0 16px !important;
    }

    nav[role="navigation"] a,
    nav[role="navigation"] span,
    .pagination .page-link,
    .paginacion .page-link {
        box-sizing: border-box;
    }

    nav[role="navigation"] a[rel="prev"],
    nav[role="navigation"] a[rel="next"],
    nav[role="navigation"] span[aria-current="page"] > span,
    nav[role="navigation"] span[aria-disabled="true"] > span,
    .pagination .page-link,
    .paginacion .page-link {
        min-width: 34px !important;
        min-height: 34px !important;
        height: 34px !important;
        padding: 0 10px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: 13px !important;
        line-height: 1 !important;
    }

    .pagination,
    .paginacion {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 4px;
    }

    /* ==========================================================
       SWITCHES BOOLEANOS
       ========================================================== */

    /*
     * Se aplica a checkboxes dentro de los campos de formulario.
     * Esto cubre Trabajador activo, Cliente activo, Credito habilitado,
     * Usuario activo y otros booleanos de los formularios CRUD.
     */
    .campo-formulario input[type="checkbox"] {
        -webkit-appearance: none !important;
        appearance: none !important;

        box-sizing: border-box !important;
        display: inline-block !important;
        position: relative !important;
        float: none !important;

        width: 44px !important;
        min-width: 44px !important;
        max-width: 44px !important;

        height: 24px !important;
        min-height: 24px !important;
        max-height: 24px !important;

        flex: 0 0 44px !important;

        padding: 0 !important;
        margin: 0 !important;

        border: 1px solid #cbd5e1 !important;
        border-radius: 999px !important;

        /*
         * El circulo blanco es un background, no un pseudo-elemento.
         * Esto evita el defecto visual observado en Chrome.
         */
        background-color: #cbd5e1 !important;
        background-image:
            radial-gradient(
                circle at 11px 50%,
                #ffffff 0,
                #ffffff 8px,
                transparent 8.5px
            ) !important;
        background-repeat: no-repeat !important;
        background-size: 44px 24px !important;
        background-position: 0 0 !important;

        vertical-align: middle !important;
        cursor: pointer !important;
        outline: none !important;

        box-shadow:
            inset 0 0 0 1px rgba(15, 23, 42, .02),
            0 1px 2px rgba(15, 23, 42, .08) !important;

        transition:
            background-color .16s ease,
            border-color .16s ease,
            box-shadow .16s ease !important;
    }

    .campo-formulario input[type="checkbox"]:checked {
        border-color: #15803d !important;
        background-color: #15803d !important;
        background-image:
            radial-gradient(
                circle at 32px 50%,
                #ffffff 0,
                #ffffff 8px,
                transparent 8.5px
            ) !important;
    }

    /*
     * Neutraliza cualquier pseudo-elemento de estilos anteriores.
     */
    .campo-formulario input[type="checkbox"]::before,
    .campo-formulario input[type="checkbox"]::after {
        content: none !important;
        display: none !important;
    }

    .campo-formulario input[type="checkbox"]:focus-visible {
        box-shadow:
            0 0 0 3px rgba(21, 128, 61, .18),
            0 1px 2px rgba(15, 23, 42, .08) !important;
    }

    .campo-formulario input[type="checkbox"]:disabled {
        cursor: not-allowed !important;
        opacity: .55 !important;
    }

    /*
     * La etiqueta que contiene el switch y su texto se convierte en una
     * fila flexible. Evita que el switch quede encima de "Trabajador activo"
     * o "Activo / habilitado".
     */
    .campo-formulario label:has(input[type="checkbox"]) {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: flex-start !important;
        gap: 10px !important;

        width: auto !important;
        max-width: 100% !important;
        min-height: 28px !important;

        margin: 4px 0 0 0 !important;
        padding: 0 !important;

        line-height: 1.25 !important;
        white-space: normal !important;
        cursor: pointer !important;
    }

    .campo-formulario label:has(input[type="checkbox"]) input[type="checkbox"] {
        order: 0;
    }

    /*
     * Compatibilidad si el formulario usa un contenedor especifico para
     * interruptores en lugar de poner el checkbox directamente en el label.
     */
    .campo-formulario .switch,
    .campo-formulario .interruptor,
    .campo-formulario .toggle,
    .campo-formulario .campo-switch {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: flex-start !important;
        gap: 10px !important;
        width: auto !important;
        min-height: 28px !important;
    }

    @media (max-width: 640px) {
        nav[role="navigation"] a[rel="prev"],
        nav[role="navigation"] a[rel="next"],
        nav[role="navigation"] span[aria-current="page"] > span,
        nav[role="navigation"] span[aria-disabled="true"] > span,
        .pagination .page-link,
        .paginacion .page-link {
            min-width: 32px !important;
            min-height: 32px !important;
            height: 32px !important;
            padding: 0 8px !important;
        }
    }
</style>
