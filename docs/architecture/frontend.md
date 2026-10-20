# Frontend: Inertia, React y sistema de diseño

## Alcance

Este documento fija las decisiones de frontend del producto: cómo se renderizan las
páginas, cómo viajan los datos desde Laravel, qué reglas de estado y seguridad aplican y
qué lenguaje visual se usa. Las convenciones operativas para agentes y desarrolladores
están en [`AGENTS.md`](../../AGENTS.md); aquí se explica el porqué y la arquitectura.

El frontend es una SPA de Inertia: Laravel conserva el enrutamiento, la sesión, el CSRF, la
validación y la autorización; React renderiza las páginas. No existe una API JSON separada
para el producto ni un cliente que decida permisos o tenant.

## Stack aprobado

| Capa | Decisión |
| --- | --- |
| Enrutamiento y transporte | Inertia Laravel v3 + `@inertiajs/react` v3 |
| UI | React 19 con TypeScript estricto |
| Build | Vite 7 + `@vitejs/plugin-react` |
| Estilos | Tailwind CSS v4 con tokens semánticos |
| Primitivas | shadcn/ui editable sobre Radix UI |
| Iconos | Lucide React |
| Tipografía | Instrument Sans (UI) y Fraunces (títulos), autoalojadas con Fontsource |
| Validación cliente/servidor | `useForm` + reglas Laravel; el servidor siempre revalida |

Decisiones asociadas:

- **TypeScript estricto.** `strict`, `noUncheckedIndexedAccess`, `noUnusedLocals` y
  `noUnusedParameters` activos. Sin `any` en código de producto.
- **Sin librería de estado global.** Los props de Inertia son el estado de servidor; el
  formulario usa `useForm`; la UI usa `useState` local. Se introduce una librería solo con
  una necesidad transversal documentada.
- **Sin SSR por ahora.** La configuración de Inertia queda preparada, pero no se publica un
  bundle SSR. Si se habilita, debe probarse el renderizado de páginas autenticadas y el
  manejo de `document`/`localStorage`.
- **Sin sistema de componentes propietario.** shadcn/ui se copia al repositorio y se adapta;
  no se depende de un tema cerrado ni de actualizaciones que rompan la marca.

## Ciclo de una petición

1. El navegador navega con `Link` o `router`.
2. Laravel resuelve la ruta y ejecuta `web`, `auth`, `church` y `HandleInertiaRequests`.
3. `SetCurrentChurch` valida la membresía y publica `CurrentChurch` y `currentMembership`.
4. El controlador mapea solo los campos necesarios y llama `Inertia::render('pages/name')`.
5. La primera visita carga `resources/views/app.blade.php`; las siguientes reciben JSON de
   Inertia y solo se reemplaza el componente de página.
6. `resources/js/app.tsx` resuelve `pages/<nombre>.tsx` con `import.meta.glob`, lo que genera
   code splitting por página.

Reglas del contrato:

- La ruta Inertia coincide con la ruta del archivo bajo `resources/js/pages`, sin extensión.
  `Inertia::render('churches/show')` → `pages/churches/show.tsx`.
- Los props se envían ya normalizados en camelCase. No se serializan modelos Eloquent
  completos ni relaciones no utilizadas.
- La página declara su `<Head title="..." />` y envuelve el contenido autenticado con
  `<AppLayout>`.
- Los errores de validación llegan en `errors` y se muestran junto al campo.

## Props compartidos

`app/Http/Middleware/HandleInertiaRequests.php` comparte:

```ts
type PageProps = {
    appName: string;
    auth: { user: { id: number; name: string; email: string } | null };
    platformAdmin: { id: number; name: string; email: string } | null;
    currentChurch: {
        name: string;
        slug: string;
        countryCode: string | null;
        currencyCode: string | null;
        timezone: string | null;
        role: string | null;
    } | null;
    flash: { success?: string | null; error?: string | null };
    errors: Record<string, string>;
};
```

Criterios:

- `auth.user` expone solo identidad mínima de sesión de iglesia.
- `platformAdmin` solo se comparte cuando el guard `platform` está autenticado. Es una
  identidad separada de `auth.user` y no habilita rutas de iglesia.
- `currentChurch` se deriva de la iglesia resuelta por el middleware, nunca de un
  identificador enviado por el cliente. Solo se comparte cuando existe membresía resuelta en
  la petición, por lo que las páginas de `/admin` no reciben contexto tenant.
- No agregar notas pastorales, montos, listas de miembros ni identificadores internos a los
  props compartidos. Cada página recibe solo lo que necesita.
- Al ampliar los props compartidos, actualizar `PageProps` en `resources/js/types/index.ts`.

## Estado y formularios

- **Servidor:** props de Inertia, recargas parciales con `router.reload({ only: [...] })` y
  visitas con `preserveScroll` cuando corresponda.
- **Formularios:** `useForm` con tipado inferido. Enviar con `form.post(...)`, mostrar
  `form.errors.<campo>`, deshabilitar con `form.processing` y usar `onFinish` para limpiar
  campos sensibles como la contraseña.
- **UI:** `useState` para diálogos, pestañas, filtros locales y preferencias de vista.
- **Permisos:** ocultar un control es solo UX. Toda lectura y escritura se autoriza en
  Laravel; el cliente no envía `church_id` como prueba de acceso.
- **Persistencia local:** solo el tema visual (`localStorage.theme`). Prohibido guardar
  tokens, datos pastorales, montos o secretos.

## Sistema de diseño

### Principios

- Cálido y pastoral, no corporativo frío: papel cálido, verde profundo y dorado medido.
- Jerarquía editorial: Fraunces para títulos y cifras destacadas; Instrument Sans para todo
  el texto funcional.
- Superficies tranquilas: bordes sutiles, sombras mínimas y radios de 10–14 px.
- El color comunica categoría y énfasis, nunca es el único indicador de estado.

### Tokens

La fuente de verdad es `resources/css/app.css`. Los componentes usan utilidades semánticas,
no valores crudos.

| Token | Uso | Claro | Oscuro |
| --- | --- | --- | --- |
| `background` | Fondo de app | `#f6f3ea` | `#0e1512` |
| `foreground` | Texto principal | `#1c2a24` | `#ece7db` |
| `card` | Tarjetas y campos | `#fffdf7` | `#141d19` |
| `primary` | Acción y marca | `#1f4d3f` | `#3c7c65` |
| `accent` | Énfasis y detalles | `#b8892f` | `#d3a95a` |
| `secondary` | Superficie alterna | `#e9e3d4` | `#1d2a24` |
| `muted-foreground` | Texto secundario | `#6b6a5e` | `#a3a89c` |
| `border` | Bordes | `#ded7c7` | `#2a3730` |
| `destructive` | Error | `#b42318` | `#f97066` |

- Radios: `--radius: 0.75rem`; `rounded-lg` en botones, inputs y navegación; `rounded-xl`
  en tarjetas, paneles y el hero de iglesia.
- Tipografía: `font-sans` (Instrument Sans Variable) por defecto; `font-display` (Fraunces
  Variable) para `h1`, títulos de tarjeta y cifras editoriales.
- Sombras: solo una sombra ambiental muy baja en tarjetas; evitar elevaciones fuertes.
- Estados: hover/active cambian superficie o borde; focus usa `ring` con contraste; los
  estados críticos incluyen texto o icono, no solo color.

### Layout

- **Escritorio (≥1024 px):** sidebar fija de 272 px con marca, contexto de iglesia, navegación
  y bloque de usuario; topbar pegajoso con contexto, usuario y tema.
- **Móvil:** topbar compacta con marca y tema; bottom nav fija de cuatro destinos con
  `safe-area-inset`. El contenido conserva `pb-28` para no quedar tapado.
- **Contenido:** ancho máximo 6xl, padding fluido y jerarquía `eyebrow → título → descripción`.
- **Estados vacíos:** tarjeta centrada con explicación y siguiente acción; nunca una tabla
  vacía sin contexto.
- **Backoffice:** `/admin` usa `AdminLayout`, una navegación propia de plataforma y las mismas
  primitivas visuales. No comparte `AppLayout` ni muestra navegación de producto de iglesia.

### Componentes base

`resources/js/components/ui` contiene primitivas editables:

- `button.tsx`: variantes `default`, `secondary`, `outline`, `ghost`, `destructive`, `link` y
  tamaños `sm`, `default`, `lg`, `icon`.
- `input.tsx`, `label.tsx`, `checkbox.tsx`: estados `focus-visible`, `aria-invalid` y
  `disabled` consistentes.
- `card.tsx`: composición `Card`, `CardHeader`, `CardTitle`, `CardDescription`, `CardContent`,
  `CardFooter`.

Los componentes de producto viven en `components/` (por ejemplo, `brand.tsx`,
`theme-toggle.tsx`). No crear componentes de página reutilizables dentro de `pages/` si se
usan en más de una ruta.

## Seguridad en el cliente

- React escapa texto por defecto; prohibido `dangerouslySetInnerHTML`.
- No confiar en validaciones de UI. Laravel valida, autoriza y aplica el scope de iglesia.
- No incluir datos de otras iglesias en props, logs del navegador, URLs ni almacenamiento
  local.
- No exponer tokens, contraseñas ni claves en mensajes de error o `console`.
- Enlaces externos, si se agregan, con `rel="noopener noreferrer"` y validación de destino.
- Descargas y exportaciones deben seguir las reglas de autorización del servidor; el
  frontend no construye la consulta de datos.

## Rendimiento

- El `import.meta.glob` de `app.tsx` separa cada página en un chunk.
- Evitar props compartidos grandes o que cambian con cada visita.
- Paginar listas en servidor; no cargar colecciones completas para filtrar en React.
- Usar `useMemo`/`useCallback` solo con una razón medida; el render derivado es preferible.
- Prefetch y polling de Inertia solo cuando el flujo lo justifique.
- Las fuentes se autoalojan con subsets de Fontsource; no agregar scripts de terceros para
  tipografía.

## Accesibilidad

- HTML semántico y landmarks (`header`, `nav`, `main`).
- Todo input tiene `Label` asociado o `aria-label`; los errores se anuncian con texto y
  `aria-invalid`.
- Foco visible en todos los controles; no se elimina `outline` sin reemplazo.
- Los botones de icono llevan nombre accesible.
- La navegación activa usa `aria-current="page"`.
- Los estados no dependen solo del color; incluir texto, icono o patrón.

## Pruebas y verificación

- Backend de páginas: `assertInertia` para componente, props y estado de sesión. No usar
  `assertSee` sobre HTML renderizado por React.
- Frontend: `npm run typecheck` y `npm run build` son obligatorios.
- Cambios de tenancy: pruebas positivas y negativas entre dos iglesias.
- Pendiente de adoptar: pruebas de componentes (Vitest + Testing Library) y E2E
  (Playwright). Al introducirlas, priorizar login, creación de iglesia, membresías y
  monedas.

## Decisiones abiertas

- ¿Se habilita SSR para mejorar primera pintura y SEO de páginas públicas?
- ¿Se adopta Vitest/Testing Library y Playwright en CI?
- ¿Se necesita i18n multi-idioma o el producto permanece en español?
- ¿Se agrega una librería de tablas/consultas cuando existan listados grandes?
- ¿Qué módulos requieren actualizaciones en tiempo real y con qué mecanismo?
