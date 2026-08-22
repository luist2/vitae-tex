// @vitest-environment jsdom

import CvCertificationsEditor from '@/components/cvs/CvCertificationsEditor.vue';
import CvEducationEditor from '@/components/cvs/CvEducationEditor.vue';
import CvLinksEditor from '@/components/cvs/CvLinksEditor.vue';
import CvProjectsEditor from '@/components/cvs/CvProjectsEditor.vue';
import CvSkillGroupsEditor from '@/components/cvs/CvSkillGroupsEditor.vue';
import CvWorkExperiencesEditor from '@/components/cvs/CvWorkExperiencesEditor.vue';
import type {
    CvCertificationFormInput,
    CvEducationFormInput,
    CvLinkFormInput,
    CvProjectFormInput,
    CvSkillGroupInput,
    CvWorkExperienceFormInput,
} from '@/types';
import { mount } from '@vue/test-utils';
import axe from 'axe-core';
import { afterEach, describe, expect, it } from 'vitest';
import { defineComponent, h, reactive } from 'vue';

afterEach(() => {
    document.body.innerHTML = '';
});

describe('CV collection editors accessibility', () => {
    it('has no detectable semantic violations with every collection populated', async () => {
        const workExperiences = reactive<CvWorkExperienceFormInput[]>([
            {
                employer: 'Acme',
                role: 'Desarrolladora',
                location: 'Santiago',
                start_date: '2024-01',
                end_date: '2025-01',
                is_current: false,
                highlights: ['Construí una aplicación accesible.'],
            },
        ]);
        const educationEntries = reactive<CvEducationFormInput[]>([
            {
                institution: 'Universidad',
                qualification: 'Ingeniería',
                field_of_study: 'Informática',
                location: 'Santiago',
                start_date: '2018-03',
                end_date: '2023-12',
                is_current: false,
                description: 'Formación profesional.',
            },
        ]);
        const skillGroups = reactive<CvSkillGroupInput[]>([{ name: 'Lenguajes', skills: [{ name: 'PHP' }] }]);
        const projects = reactive<CvProjectFormInput[]>([
            {
                name: 'VitaeTex',
                role: 'Desarrollo',
                description: 'Constructor de currículums.',
                url: 'https://example.com',
                start_date: '2026-01',
                end_date: '',
                is_current: true,
                highlights: ['Generación segura de documentos.'],
                technologies: ['Laravel'],
            },
        ]);
        const certifications = reactive<CvCertificationFormInput[]>([
            {
                name: 'Certificación',
                issuer: 'Entidad',
                issued_on: '2025-01',
                expires_on: '',
                credential_id: 'ABC-123',
                credential_url: 'https://example.com/credential',
            },
        ]);
        const links = reactive<CvLinkFormInput[]>([{ type: 'github', label: 'GitHub', url: 'https://github.com/example' }]);
        const AuditHarness = defineComponent({
            setup() {
                const errors = {};

                return () =>
                    h('main', [
                        h(CvLinksEditor, { modelValue: links, errors }),
                        h(CvEducationEditor, { modelValue: educationEntries, errors }),
                        h(CvWorkExperiencesEditor, { modelValue: workExperiences, errors }),
                        h(CvProjectsEditor, { modelValue: projects, errors }),
                        h(CvSkillGroupsEditor, { modelValue: skillGroups, errors }),
                        h(CvCertificationsEditor, { modelValue: certifications, errors }),
                    ]);
            },
        });
        const wrapper = mount(AuditHarness, { attachTo: document.body });
        const result = await axe.run(wrapper.element, {
            rules: {
                'color-contrast': { enabled: false },
            },
        });

        expect(result.violations).toEqual([]);
        wrapper.unmount();
    });
});
