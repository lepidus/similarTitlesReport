**Español** | [Português Brasileiro](/README.md) | [English](/docs/README-en.md)

# Informe de títulos similares

Módulo **generic** para OJS que añade una página en `Estadísticas` > `Títulos similares`, listando pares de envíos activos cuyos títulos son similares.

## Compatibilidad

Este módulo es compatible con OJS 3.5.0.x.

## Instalación

Para la instalación en producción, utilice el paquete `.tar.gz` publicado en la página de lanzamientos del módulo.

En OJS, acceda a `Configuración` > `Sitio web` > `Módulos` > `Subir un nuevo módulo`, seleccione el paquete del módulo y confirme la instalación.

## Uso

Después de habilitar el módulo, la página queda disponible en `Estadísticas` > `Títulos similares`.

### Acceso

Los gestores y administradores del sitio consultan los envíos activos del contexto. Los editores de sección consultan solamente los envíos en los que tienen una asignación de etapa como editor de sección, siguiendo el ámbito de acceso del flujo editorial de OJS.

### Criterio de similitud

El listado compara títulos con `similar_text` y muestra pares con similitud mayor o igual al 70%. El único pre-filtro compara la longitud de los títulos y descarta solamente los pares que matemáticamente no pueden alcanzar el umbral.

Para mantener límites previsibles de tiempo y memoria, el informe compara como máximo los 200 envíos activos accesibles más recientes y muestra como máximo los 1.000 pares con mayor similitud. La página muestra un aviso cuando se alcanza cualquiera de estos límites.

La similitud calculada se guarda en la caché de la aplicación durante 30 días. La clave incluye los IDs de los envíos y el `sha256` de los títulos normalizados; si un título cambia, la comparación se recalcula automáticamente.

## Desarrollo

Clone el módulo dentro de `plugins/generic/similarTitlesReport` en una instalación local de OJS 3.5.

### Pruebas PHPUnit

Ejecute desde la raíz de OJS:

```bash
TESTS=$(find -L plugins/generic/similarTitlesReport -name tests -type d -maxdepth 1) \
  && lib/pkp/lib/vendor/bin/phpunit --no-coverage --configuration lib/pkp/tests/phpunit.xml $TESTS
```

### PHP CS Fixer

Ejecute desde la raíz de OJS antes de considerar listos los cambios PHP:

```bash
php lib/pkp/lib/vendor/bin/php-cs-fixer fix \
  --config .php-cs-fixer.php --allow-risky=yes \
  plugins/generic/similarTitlesReport/
```

### Pruebas Cypress

Ejecute desde la raíz de OJS:

```bash
npx cypress run \
  --config 'baseUrl=http://localhost:8000,specPattern=plugins/generic/similarTitlesReport/cypress/tests/**/*.cy.js' \
  --browser chrome
```

Las pruebas Cypress del módulo no son idempotentes. Ejecute la suite completa contra una base de datos nueva, en el orden de los specs existentes.

## Licencia

Este módulo está licenciado bajo la GNU General Public License v3.0.

_Copyright (c) 2026 Lepidus Tecnologia_
