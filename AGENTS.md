# Instrucciones del repositorio

## Contexto

Church SaaS es una plataforma Laravel 12 + Inertia v3 + React 19 + TypeScript para gestión
pastoral. PostgreSQL es obligatorio para validar el esquema. El aislamiento por iglesia se
resuelve en servidor; el frontend nunca es fuente de autorización.

## Comandos

- `php artisan test`: suite completa. Requiere PostgreSQL de pruebas configurado.
- `vendor/bin/pint --dirty`: formato PHP antes de cerrar un cambio.
- `npm run typecheck`: TypeScript estricto; no debe fallar.
- `npm run build`: compila assets con Vite. Ejecutarlo tras cambios de frontend.
- `npm run dev`: Vite en desarrollo.
- `composer dev`: servidor, cola, logs y Vite en paralelo.

No ejecutar migraciones ni conectarse a PostgreSQL sin configuración explícita. Consultar
`README.md` y `docs/architecture/` antes de tocar tenancy, datos o workflows.

## Estructura de frontend

```
resources/
├── css/app.css                 # Tailwind v4 y tokens de diseño
├── js/
│   ├── app.tsx                 # Bootstrap de Inertia y resolución de páginas
│   ├── pages/                  # Componentes de página (uno por ruta Inertia)
│   │   ├── auth/login.tsx
│   │   ├── dashboard.tsx
│   │   └── churches/show.tsx
│   ├── layouts/app-layout.tsx  # Shell autenticado: sidebar, topbar y bottom nav
│   ├── components/             # Componentes compartidos de producto
│   │   └── ui/                 # Primitivas shadcn/ui editables
│   ├── lib/utils.ts            # Utilidades (cn)
│   └── types/index.ts          # Contratos de props y tipos compartidos
└── views/app.blade.php         # Único root view; no agregar vistas Blade de producto
```

- Las páginas se registran en `Inertia::render('<ruta>')` con el mismo nombre relativo a
  `resources/js/pages`. Ejemplo: `Inertia::render('churches/show')` carga
  `pages/churches/show.tsx`.
- No crear rutas de producto que devuelvan `view()`. El HTML de producto vive en React.
- Usar el alias `@/` para imports internos (`@/components/ui/button`).
- Archivos en kebab-case; componentes y tipos en PascalCase.

## Convenciones de React

### Componentes

- Componentes de función. Las páginas usan `export default` (requisito de Inertia); los
  componentes reutilizables usan export nombrado.
- Tipar props explícitamente. Reutilizar tipos de `@/types`. Prohibido `any`; si un tipo
  externo es difícil, aislar el cast en un adaptador y justificarlo.
- Preferir `ComponentProps<'div'>`, `ComponentProps<typeof Primitive>` para extender
  primitivas.
- Un componente por archivo. Extraer cuando supere ~150 líneas, tenga más de una
  responsabilidad o se repita en dos páginas.
- Mantener los componentes presentacionales: sin consultas a base de datos, sin reglas de
  autorización y sin cálculo financiero. Esas reglas viven en Laravel.
- No agregar `useEffect` para datos que ya llegan como props. Los props de Inertia son la
  fuente de verdad del estado de servidor.
- Derivar valores durante el render; no duplicar estado que pueda calcularse. Usar
  `useMemo`/`useCallback` solo ante un costo medido o estabilidad requerida por una API.
- Evitar `index` como `key` en listas reordenables.
- No usar `dangerouslySetInnerHTML`.

### Inertia

- Navegación interna con `Link`; navegación programática con `router`; nunca `<a href>`
  para rutas de la app.
- Formularios con `useForm` de `@inertiajs/react`. Mostrar `form.errors` y deshabilitar el
  botón con `form.processing`.
- Cada página define su `<Head title="..." />`.
- El layout autenticado se compone con `<AppLayout>` dentro de la página.
- Los props compartidos (`auth`, `currentChurch`, `flash`, `appName`, `errors`) están
  tipados en `PageProps`. Ampliar ese tipo al agregar props compartidos.
- Mapear en el controlador solo los campos necesarios. No enviar modelos Eloquent completos
  ni datos de otras iglesias.
- Usar camelCase en props de Inertia aunque la columna sea snake_case.

### Estado y datos

- Estado de servidor: props de Inertia y recargas parciales (`router.reload({ only: [...] })`).
- Estado de formulario: `useForm`.
- Estado de UI: `useState` local. No introducir Zustand/Redux hasta que exista una
  necesidad transversal documentada.
- Para permisos, ocultar controles es solo UX; la decisión real se toma en servidor.
- Nunca guardar tokens, datos pastorales ni secretos en `localStorage`. El tema visual es
  la única preferencia persistida.

### Estilo y diseño

- Tailwind v4 con tokens semánticos definidos en `resources/css/app.css`. Usar
  `bg-background`, `text-foreground`, `border-border`, `bg-primary`, `text-accent`, etc.
- Prohibidos los colores hexadecimales sueltos en componentes; si falta un token, agregarlo
  en `app.css` para claro y oscuro.
- Usar `cn()` para combinar clases. Variantes con `class-variance-authority` cuando un
  componente tenga más de dos variantes.
- Primitivas accesibles de Radix/shadcn en `components/ui`; no reimplementar focus traps,
  checkboxes o labels desde cero.
- Radios: `--radius` (12px), `rounded-lg` en controles y `rounded-xl` en tarjetas/paneles.
- Tipografía: Instrument Sans para UI y Fraunces (`font-display`) para títulos.
- Paleta: verde profundo (`primary`), dorado cálido (`accent`) y papel cálido
  (`background`). Soportar claro y oscuro con ambos juegos de tokens.
- Layout autenticado: sidebar + topbar en escritorio; bottom nav en móvil. Mantener
  `pb-28` en el contenido móvil para no tapar la navegación.
- Textos de interfaz en español; identificadores y código en inglés.

### Accesibilidad

- HTML semántico (`header`, `nav`, `main`, `section`, `form`).
- Todo control tiene `Label` o `aria-label`. Errores con `aria-invalid` y texto asociado.
- Mantener foco visible; no eliminar `outline` sin reemplazo.
- Botones con icono necesitan nombre accesible (`aria-label`/`title`).
- No depender solo del color para estados.

## Contratos con Laravel

- Rutas y controladores resuelven tenant y autorización; el cliente no envía `church_id`
  como prueba de acceso.
- El middleware `church` establece `CurrentChurch` y `currentMembership`. Los props
  compartidos se construyen desde esa resolución.
- Validación de entrada en `FormRequest` o `$request->validate()`, no solo en React.
- Errores de validación llegan en `errors`; mostrarlos junto al campo.
- Nunca exponer notas pastorales, montos de otras iglesias ni identificadores internos
  innecesarios en props compartidos.
- Montos: enteros en unidad menor y código de moneda explícito; no usar `float` en UI.

## Pruebas y verificación

- Cambios de frontend: `npm run typecheck` y `npm run build`.
- Rutas/controladores Inertia: probar con `assertInertia` (`Inertia\Testing\AssertableInertia`)
  el componente y los props relevantes; no depender de texto renderizado en servidor.
- Cambios de tenancy o datos: probar acceso permitido y denegado entre dos iglesias.
- Cambios de esquema: PostgreSQL real; SQLite no valida JSONB, GIN ni CHECK.
- Antes de cerrar: `vendor/bin/pint --dirty` y `php artisan test`.

## Dependencias

- No agregar librerías de UI, estado, formularios o fetching sin justificar el caso de uso.
- Preferir las primitivas existentes y shadcn/ui antes de sumar un paquete nuevo.
- Mantener `react`, `react-dom` y `@inertiajs/react` alineados con sus peers.
