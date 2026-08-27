// @vitest-environment jsdom

import { cvEditorErrorSummaryItems, focusCvEditorError, focusFirstCvEditorError } from '@/lib/cvEditorAccessibility';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

describe('focusFirstCvEditorError', () => {
    const scrollIntoView = vi.fn();
    const scrollTo = vi.fn();

    beforeEach(() => {
        document.body.innerHTML = '';
        HTMLElement.prototype.scrollIntoView = scrollIntoView;
        HTMLElement.prototype.scrollTo = scrollTo;
        vi.stubGlobal('matchMedia', vi.fn().mockReturnValue({ matches: false }));
    });

    afterEach(() => {
        vi.clearAllMocks();
        vi.unstubAllGlobals();
    });

    it('focuses the first invalid control in DOM order instead of error key order', () => {
        document.body.innerHTML = '<input id="cv-title"><input id="cv-full-name">';

        expect(focusFirstCvEditorError({ full_name: 'El nombre es obligatorio.', title: 'El título es obligatorio.' })).toBe(true);
        expect(document.activeElement).toBe(document.getElementById('cv-title'));
        expect(scrollIntoView).toHaveBeenCalledWith({ block: 'center' });
    });

    it('maps nested editor errors to their rendered controls', () => {
        document.body.innerHTML = '<input id="skill-group-2-skill-3">';

        expect(focusFirstCvEditorError({ 'skill_groups.2.skills.3.name': 'La habilidad es obligatoria.' })).toBe(true);
        expect(document.activeElement).toBe(document.getElementById('skill-group-2-skill-3'));
    });

    it('orders summary items by their targets and leaves unlinked errors visible at the end', () => {
        document.body.innerHTML = '<input id="link-0-url"><input id="education-0-institution">';

        expect(
            cvEditorErrorSummaryItems({
                'education_entries.0.institution': 'La institución es obligatoria.',
                unexpected_path: 'Hay un error general.',
                'links.0.url': 'La URL no es válida.',
            }),
        ).toEqual([
            { path: 'links.0.url', message: 'La URL no es válida.', targetId: 'link-0-url' },
            {
                path: 'education_entries.0.institution',
                message: 'La institución es obligatoria.',
                targetId: 'education-0-institution',
            },
            { path: 'unexpected_path', message: 'Hay un error general.', targetId: undefined },
        ]);
    });

    it('focuses a summary destination by validation path', () => {
        document.body.innerHTML = '<input id="cv-full-name">';

        expect(focusCvEditorError('full_name')).toBe(true);
        expect(document.activeElement).toBe(document.getElementById('cv-full-name'));
        expect(scrollIntoView).toHaveBeenCalledWith({ block: 'center' });
    });

    it('uses an enabled collection fallback for collection-level errors', () => {
        document.body.innerHTML = '<button id="add-project" type="button">Añadir proyecto</button>';

        expect(focusFirstCvEditorError({ projects: 'Hay demasiados proyectos.' })).toBe(true);
        expect(document.activeElement).toBe(document.getElementById('add-project'));
    });

    it('falls back to the collection when a nested target is not rendered', () => {
        document.body.innerHTML = '<button id="add-work-experience" type="button">Añadir experiencia</button>';

        expect(focusFirstCvEditorError({ 'work_experiences.0.highlights': 'La lista es obligatoria.' })).toBe(true);
        expect(document.activeElement).toBe(document.getElementById('add-work-experience'));
    });

    it('skips disabled controls and reports when no error target exists', () => {
        document.body.innerHTML = '<button id="add-link" type="button" disabled>Añadir enlace</button>';

        expect(focusFirstCvEditorError({ links: 'Hay demasiados enlaces.' })).toBe(false);
        expect(document.activeElement).toBe(document.body);
        expect(scrollIntoView).not.toHaveBeenCalled();
    });

    it('scrolls only the editor panel in the desktop layout', () => {
        document.body.innerHTML = '<section id="editor-panel"><input id="cv-title"></section><section id="preview-panel"></section>';
        vi.mocked(window.matchMedia).mockReturnValue({ matches: true } as MediaQueryList);

        const panel = document.getElementById('editor-panel') as HTMLElement;
        const target = document.getElementById('cv-title') as HTMLElement;
        Object.defineProperty(panel, 'clientHeight', { configurable: true, value: 400 });
        Object.defineProperty(panel, 'scrollTop', { configurable: true, value: 600, writable: true });
        vi.spyOn(panel, 'getBoundingClientRect').mockReturnValue({ top: 100, height: 400 } as DOMRect);
        vi.spyOn(target, 'getBoundingClientRect').mockReturnValue({ top: 220, height: 40 } as DOMRect);
        const focus = vi.spyOn(target, 'focus');

        expect(focusFirstCvEditorError({ title: 'El título es obligatorio.' })).toBe(true);
        expect(focus).toHaveBeenCalledWith({ preventScroll: true });
        expect(scrollTo).toHaveBeenCalledWith({ top: 540 });
        expect(scrollIntoView).not.toHaveBeenCalled();
    });
});
