import type { CvEditorData } from '@/types';
import { describe, expect, it } from 'vitest';
import { isProxy, reactive } from 'vue';

import { cloneCvEditorFormData, createCvEditorFormData, synchronizeCvEditorFormAfterSave } from './cvEditorForm';

const editorData = (): CvEditorData => ({
    id: 7,
    revision: 1,
    title: 'CV Backend',
    template_key: 'jakes-resume',
    full_name: '',
    professional_headline: null,
    contact_email: null,
    phone: null,
    location: null,
    professional_summary: null,
    work_experiences: [
        {
            employer: 'VitaeTex',
            role: 'Desarrollador',
            location: null,
            start_date: '2026-08',
            end_date: null,
            is_current: true,
            highlights: ['Construcción del editor'],
        },
    ],
    education_entries: [],
    skill_groups: [
        {
            name: 'Backend',
            skills: [{ name: 'Laravel' }],
        },
    ],
    projects: [
        {
            name: 'VitaeTex',
            role: null,
            description: null,
            url: null,
            start_date: null,
            end_date: null,
            is_current: false,
            highlights: ['PDF seguro'],
            technologies: ['PHP'],
        },
    ],
    certifications: [],
    links: [],
    updated_at: '2026-08-20T00:00:00.000000Z',
});

describe('createCvEditorFormData', () => {
    it('creates cloneable form data from reactive Inertia props', () => {
        const cv = reactive<CvEditorData>(editorData());

        const formData = createCvEditorFormData(cv);

        expect(isProxy(cv)).toBe(true);
        expect(isProxy(formData)).toBe(false);
        expect(isProxy(formData.work_experiences)).toBe(false);
        expect(isProxy(formData.work_experiences[0].highlights)).toBe(false);
        expect(isProxy(formData.skill_groups)).toBe(false);
        expect(isProxy(formData.skill_groups[0].skills)).toBe(false);
        expect(isProxy(formData.projects[0].highlights)).toBe(false);
        expect(isProxy(formData.projects[0].technologies)).toBe(false);
        expect(() => structuredClone(formData)).not.toThrow();
        expect(formData).toMatchObject({
            professional_headline: '',
            contact_email: '',
            skill_groups: [{ name: 'Backend', skills: [{ name: 'Laravel' }] }],
        });
    });
});

describe('synchronizeCvEditorFormAfterSave', () => {
    it('adopts every persisted value when the form did not change during the request', () => {
        const submitted = createCvEditorFormData(editorData());
        submitted.title = '  CV normalizado  ';
        submitted.work_experiences[0].employer = '  VitaeTex  ';
        const persisted = cloneCvEditorFormData(submitted);
        persisted.title = 'CV normalizado';
        persisted.work_experiences[0].employer = 'VitaeTex';

        const synchronization = synchronizeCvEditorFormAfterSave(submitted, cloneCvEditorFormData(submitted), persisted);

        expect(synchronization.values).toEqual(persisted);
        expect(synchronization.defaults).toEqual(persisted);
        expect(synchronization.values).not.toBe(synchronization.defaults);
        expect(synchronization.values.work_experiences).not.toBe(synchronization.defaults.work_experiences);
    });

    it('preserves concurrent scalar changes while normalizing untouched fields', () => {
        const submitted = createCvEditorFormData(editorData());
        submitted.title = '  CV normalizado  ';
        submitted.full_name = '  Nombre enviado  ';
        const current = cloneCvEditorFormData(submitted);
        current.full_name = 'Cambio realizado durante el guardado';
        const persisted = cloneCvEditorFormData(submitted);
        persisted.title = 'CV normalizado';
        persisted.full_name = 'Nombre enviado';

        const synchronization = synchronizeCvEditorFormAfterSave(submitted, current, persisted);

        expect(synchronization.values.title).toBe('CV normalizado');
        expect(synchronization.values.full_name).toBe('Cambio realizado durante el guardado');
        expect(synchronization.defaults.full_name).toBe('Nombre enviado');
    });

    it('preserves a whole collection when its indexed state changed during the request', () => {
        const submitted = createCvEditorFormData(editorData());
        submitted.work_experiences[0].employer = '  VitaeTex  ';
        const current = cloneCvEditorFormData(submitted);
        current.work_experiences.unshift({
            employer: 'Cambio concurrente',
            role: 'QA',
            location: '',
            start_date: '2026-08',
            end_date: '',
            is_current: true,
            highlights: [],
        });
        const persisted = cloneCvEditorFormData(submitted);
        persisted.work_experiences[0].employer = 'VitaeTex';

        const synchronization = synchronizeCvEditorFormAfterSave(submitted, current, persisted);

        expect(synchronization.values.work_experiences).toEqual(current.work_experiences);
        expect(synchronization.defaults.work_experiences).toEqual(persisted.work_experiences);
    });
});
