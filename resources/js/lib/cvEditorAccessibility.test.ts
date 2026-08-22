// @vitest-environment jsdom

import { focusFirstCvEditorError } from '@/lib/cvEditorAccessibility';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

describe('focusFirstCvEditorError', () => {
    const scrollIntoView = vi.fn();

    beforeEach(() => {
        document.body.innerHTML = '';
        HTMLElement.prototype.scrollIntoView = scrollIntoView;
    });

    afterEach(() => {
        vi.clearAllMocks();
    });

    it('focuses and scrolls to the first invalid basic field', () => {
        document.body.innerHTML = '<input id="cv-title"><input id="cv-full-name">';

        expect(focusFirstCvEditorError({ full_name: 'El nombre es obligatorio.', title: 'El título es obligatorio.' })).toBe(true);
        expect(document.activeElement).toBe(document.getElementById('cv-full-name'));
        expect(scrollIntoView).toHaveBeenCalledWith({ block: 'center' });
    });

    it('maps nested editor errors to their rendered controls', () => {
        document.body.innerHTML = '<input id="skill-group-2-skill-3">';

        expect(focusFirstCvEditorError({ 'skill_groups.2.skills.3.name': 'La habilidad es obligatoria.' })).toBe(true);
        expect(document.activeElement).toBe(document.getElementById('skill-group-2-skill-3'));
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
});
