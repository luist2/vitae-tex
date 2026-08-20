import type { CvEditorData } from '@/types';
import { describe, expect, it } from 'vitest';
import { isProxy, reactive } from 'vue';

import { createCvEditorFormData } from './cvEditorForm';

describe('createCvEditorFormData', () => {
    it('creates cloneable form data from reactive Inertia props', () => {
        const cv = reactive<CvEditorData>({
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
