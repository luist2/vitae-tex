<script setup lang="ts">
import { FilePenLine, FileText } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';

export type CvEditorPanel = 'editor' | 'preview';

const activePanel = defineModel<CvEditorPanel>({ required: true });
const editorTab = ref<HTMLButtonElement>();
const previewTab = ref<HTMLButtonElement>();

const selectPanel = (panel: CvEditorPanel, moveFocus = false) => {
    activePanel.value = panel;

    if (moveFocus) {
        void nextTick(() => (panel === 'editor' ? editorTab.value : previewTab.value)?.focus());
    }
};
</script>

<template>
    <div role="tablist" aria-label="Vista del editor" class="grid shrink-0 grid-cols-2 rounded-lg border bg-muted p-1 lg:hidden">
        <button
            id="editor-tab"
            ref="editorTab"
            type="button"
            role="tab"
            :aria-selected="activePanel === 'editor'"
            aria-controls="editor-panel"
            :tabindex="activePanel === 'editor' ? 0 : -1"
            class="inline-flex h-9 items-center justify-center gap-2 rounded-md px-3 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            :class="activePanel === 'editor' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
            @click="selectPanel('editor')"
            @keydown.right.prevent="selectPanel('preview', true)"
            @keydown.end.prevent="selectPanel('preview', true)"
        >
            <FilePenLine class="size-4" aria-hidden="true" />
            Editor
        </button>
        <button
            id="preview-tab"
            ref="previewTab"
            type="button"
            role="tab"
            :aria-selected="activePanel === 'preview'"
            aria-controls="preview-panel"
            :tabindex="activePanel === 'preview' ? 0 : -1"
            class="inline-flex h-9 items-center justify-center gap-2 rounded-md px-3 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            :class="activePanel === 'preview' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
            @click="selectPanel('preview')"
            @keydown.left.prevent="selectPanel('editor', true)"
            @keydown.home.prevent="selectPanel('editor', true)"
        >
            <FileText class="size-4" aria-hidden="true" />
            Preview
        </button>
    </div>
</template>
