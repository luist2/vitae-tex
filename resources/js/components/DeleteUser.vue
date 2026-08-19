<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

// Components
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const passwordInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    password: '',
});

const deleteUser = (e: Event) => {
    e.preventDefault();

    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value?.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    form.clearErrors();
    form.reset();
};
</script>

<template>
    <div class="space-y-6">
        <HeadingSmall title="Eliminar cuenta" description="Elimina permanentemente tu cuenta y todos tus currículums." />
        <div class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
            <div class="relative space-y-0.5 text-red-600 dark:text-red-100">
                <p class="font-medium">Esta acción es permanente</p>
                <p class="text-sm">No podrás recuperar la cuenta ni sus datos después de eliminarlos.</p>
            </div>
            <Dialog>
                <DialogTrigger as-child>
                    <Button variant="destructive">Eliminar cuenta</Button>
                </DialogTrigger>
                <DialogContent>
                    <form class="space-y-6" @submit="deleteUser">
                        <DialogHeader class="space-y-3">
                            <DialogTitle>¿Quieres eliminar tu cuenta?</DialogTitle>
                            <DialogDescription>
                                Se eliminarán permanentemente tu cuenta y todos tus currículums. Ingresa tu contraseña para confirmar.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-2">
                            <Label for="delete-account-password" class="sr-only">Contraseña</Label>
                            <Input
                                id="delete-account-password"
                                ref="passwordInput"
                                v-model="form.password"
                                type="password"
                                name="password"
                                autocomplete="current-password"
                                placeholder="Contraseña"
                            />
                            <InputError :message="form.errors.password" />
                        </div>

                        <DialogFooter>
                            <DialogClose as-child>
                                <Button type="button" variant="secondary" @click="closeModal">Cancelar</Button>
                            </DialogClose>

                            <Button type="submit" variant="destructive" :disabled="form.processing">
                                {{ form.processing ? 'Eliminando…' : 'Eliminar cuenta permanentemente' }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
