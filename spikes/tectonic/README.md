# Spike de Tectonic

Este entregable valida el fixture estático de compatibilidad de Jake's Resume en español y A4. La aplicación mantiene por separado su plantilla dinámica en `resources/views/latex/jakes-resume.blade.php`; ambos documentos comparten el mismo conjunto de paquetes y se compilan con la cache precalentada por la imagen.

La imagen fija FrankenPHP 1.12.7 sobre PHP 8.4.24 mediante su digest multi-arquitectura y Tectonic 0.16.9 mediante checksums separados para `amd64` y `arm64`.

## Qué comprueba

- descarga verificada de Tectonic 0.16.9 para `amd64` o `arm64`;
- ejecución como usuario sin privilegios y con modo no confiable activado por flag y entorno;
- compilación fría con cache vacío;
- segunda compilación usando la misma cache y sin red;
- imagen final con los recursos TeX precalentados durante el build;
- compilación de esa imagen con red deshabilitada y filesystem raíz de solo lectura;
- cache efímero de Fontconfig en un `tmpfs` separado de la cache TeX de solo lectura;
- papel A4, Unicode español y texto extraíble del PDF;
- límite defensivo de 5 MiB para el PDF del fixture;
- eliminación de PDFs y caches temporales creados por la ejecución.

## Ejecutar

Desde la raíz del repositorio:

```sh
./spikes/tectonic/scripts/run-spike.sh
```

El script requiere Docker. Construye dos imágenes locales llamadas `vitaetex-tectonic-spike:compiler-0.16.9` y `vitaetex-tectonic-spike:runtime-0.16.9`. Los artefactos de cada ejecución viven bajo un directorio aleatorio de `/tmp` y se eliminan al terminar.

## Procedencia y licencia

El fixture adapta [Jake's Resume](https://github.com/jakegut/resume), de Jake Gutierrez, basado en [sb2nov/resume](https://github.com/sb2nov/resume). La licencia MIT original se conserva en `LICENSE-JAKES-RESUME` y en la cabecera del fuente.
