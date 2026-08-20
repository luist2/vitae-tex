import type { CvEditorData } from '@/types';
import { describe, expect, it, vi } from 'vitest';

import { createCvEditorFormData } from './cvEditorForm';
import { createCvExampleContent, hasCvEditorContent, replaceCvContentWithExample } from './cvExample';

const emptyCv = (): CvEditorData => ({
    id: 7,
    revision: 1,
    title: 'Mi CV',
    template_key: 'jakes-resume',
    full_name: '',
    professional_headline: null,
    contact_email: null,
    phone: null,
    location: null,
    professional_summary: null,
    work_experiences: [],
    education_entries: [],
    skill_groups: [],
    projects: [],
    certifications: [],
    links: [],
    updated_at: '2026-08-20T00:00:00.000000Z',
});

describe('CV example content', () => {
    it('loads the example without confirmation when only the internal title exists', () => {
        const formData = createCvEditorFormData(emptyCv());
        const confirmReplacement = vi.fn();

        const replaced = replaceCvContentWithExample(formData, confirmReplacement);

        expect(replaced).toBe(true);
        expect(confirmReplacement).not.toHaveBeenCalled();
        expect(formData.title).toBe('Mi CV');
        expect(formData.template_key).toBe('jakes-resume');
        expect(formData.full_name).toBe('Camila Torres Rojas');
        expect(formData.work_experiences).toHaveLength(2);
        expect(formData.education_entries).toHaveLength(1);
        expect(formData.skill_groups).toHaveLength(4);
        expect(formData.projects).toHaveLength(2);
        expect(formData.certifications).toHaveLength(1);
        expect(formData.links).toHaveLength(3);
    });

    it('keeps existing content when the user cancels replacement', () => {
        const formData = createCvEditorFormData({
            ...emptyCv(),
            full_name: 'Nombre existente',
        });
        const confirmReplacement = vi.fn().mockReturnValue(false);

        const replaced = replaceCvContentWithExample(formData, confirmReplacement);

        expect(replaced).toBe(false);
        expect(confirmReplacement).toHaveBeenCalledOnce();
        expect(formData.full_name).toBe('Nombre existente');
        expect(formData.work_experiences).toEqual([]);
    });

    it('replaces existing content after confirmation while preserving CV identity', () => {
        const formData = createCvEditorFormData({
            ...emptyCv(),
            full_name: 'Nombre existente',
            skill_groups: [{ name: 'Anterior', skills: [{ name: 'Otra habilidad' }] }],
        });
        const confirmReplacement = vi.fn().mockReturnValue(true);

        const replaced = replaceCvContentWithExample(formData, confirmReplacement);

        expect(replaced).toBe(true);
        expect(confirmReplacement).toHaveBeenCalledOnce();
        expect(formData.title).toBe('Mi CV');
        expect(formData.template_key).toBe('jakes-resume');
        expect(formData.full_name).toBe('Camila Torres Rojas');
        expect(formData.skill_groups[0].name).toBe('Backend');
    });

    it('returns fresh, complete content on every load', () => {
        const first = createCvExampleContent();
        const second = createCvExampleContent();

        expect(hasCvEditorContent(first)).toBe(true);
        expect(() => structuredClone(first)).not.toThrow();

        first.skill_groups[0].skills[0].name = 'Modificada';
        first.projects[0].technologies.push('Otra');

        expect(second.skill_groups[0].skills[0].name).toBe('PHP');
        expect(second.projects[0].technologies).not.toContain('Otra');
    });
});
