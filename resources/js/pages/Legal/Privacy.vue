<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    contactEmail: string | null;
}>();
</script>

<template>
    <Head title="Privacidad" />

    <main class="min-h-svh bg-background px-6 py-10 text-foreground sm:py-14">
        <article class="mx-auto max-w-3xl">
            <header class="mb-10 border-b pb-8">
                <Link
                    :href="route('home')"
                    class="mb-8 inline-flex items-center gap-3 rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <span class="flex size-10 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                        <AppLogoIcon class="size-6" aria-hidden="true" />
                    </span>
                    <span class="font-semibold">VitaeTex</span>
                </Link>

                <h1 class="text-3xl font-semibold tracking-tight">Política de privacidad de la demo</h1>
                <p class="mt-3 text-sm text-muted-foreground">Vigente desde el 24 de agosto de 2026.</p>
            </header>

            <div class="space-y-9 text-[0.95rem] leading-7">
                <section aria-labelledby="privacy-scope">
                    <h2 id="privacy-scope" class="text-xl font-semibold">Alcance</h2>
                    <p class="mt-3">
                        VitaeTex es una demostración de portfolio para crear currículums. No se presenta como un servicio de producción ni ofrece
                        garantías de disponibilidad o recuperación.
                    </p>
                </section>

                <section aria-labelledby="privacy-data">
                    <h2 id="privacy-data" class="text-xl font-semibold">Datos tratados</h2>
                    <p class="mt-3">La aplicación conserva únicamente los datos necesarios para sus funciones:</p>
                    <ul class="mt-3 list-disc space-y-2 pl-6">
                        <li>email de acceso y contraseña almacenada mediante hash;</li>
                        <li>sesiones y tokens temporales de recuperación de contraseña;</li>
                        <li>contenido estructurado de los CVs, incluidos los datos personales que decidas incorporar;</li>
                        <li>metadatos técnicos mínimos necesarios para seguridad y diagnóstico, sin registrar el contenido de los CVs.</li>
                    </ul>
                </section>

                <section aria-labelledby="privacy-purpose">
                    <h2 id="privacy-purpose" class="text-xl font-semibold">Finalidad</h2>
                    <p class="mt-3">
                        Los datos se utilizan exclusivamente para autenticar tu cuenta, guardar y administrar tus CVs, generar los documentos que
                        solicites y proteger la aplicación frente a usos abusivos.
                    </p>
                </section>

                <section aria-labelledby="privacy-documents">
                    <h2 id="privacy-documents" class="text-xl font-semibold">Documentos generados</h2>
                    <p class="mt-3">
                        Las fuentes LaTeX, los PDFs y los archivos auxiliares se generan bajo demanda en almacenamiento temporal. Se eliminan al
                        completar o fallar la petición y no forman parte de backups ni de un historial de documentos.
                    </p>
                </section>

                <section aria-labelledby="privacy-retention">
                    <h2 id="privacy-retention" class="text-xl font-semibold">Retención y eliminación</h2>
                    <ul class="mt-3 list-disc space-y-2 pl-6">
                        <li>La información activa se conserva mientras mantengas tu cuenta.</li>
                        <li>Eliminar un CV o una cuenta borra permanentemente sus filas de la base activa.</li>
                        <li>Sesiones y tokens temporales expiran según sus límites configurados y se limpian periódicamente.</li>
                        <li>
                            Una copia eliminada puede permanecer en puntos de recuperación o snapshots durante un máximo operativo de siete días, tras
                            el cual se elimina o reemplaza.
                        </li>
                    </ul>
                </section>

                <section aria-labelledby="privacy-providers">
                    <h2 id="privacy-providers" class="text-xl font-semibold">Infraestructura</h2>
                    <p class="mt-3">
                        La demo utiliza Render para ejecutar la aplicación y Neon para alojar PostgreSQL. Estos proveedores procesan los datos
                        únicamente como infraestructura necesaria para prestar las funciones descritas.
                    </p>
                    <p class="mt-3">
                        Brevo procesa la dirección de email de acceso y el contenido del mensaje únicamente cuando se solicita entregar un enlace de
                        recuperación de contraseña.
                    </p>
                </section>

                <section aria-labelledby="privacy-contact">
                    <h2 id="privacy-contact" class="text-xl font-semibold">Contacto</h2>
                    <p v-if="contactEmail" class="mt-3">
                        Para consultas sobre privacidad o eliminación de datos, escribe a
                        <a class="underline underline-offset-4" :href="`mailto:${contactEmail}`">{{ contactEmail }}</a
                        >.
                    </p>
                    <p v-else class="mt-3 rounded-md border border-amber-500/40 bg-amber-500/10 p-4">
                        El canal de contacto se publicará antes de abrir el registro de la demo. Mientras este aviso permanezca visible, la demo no
                        debe admitir datos reales.
                    </p>
                </section>
            </div>

            <footer class="mt-12 border-t pt-6 text-sm text-muted-foreground">
                <Link :href="route('home')" class="underline underline-offset-4">Volver a VitaeTex</Link>
            </footer>
        </article>
    </main>
</template>
