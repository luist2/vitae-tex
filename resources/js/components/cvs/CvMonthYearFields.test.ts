// @vitest-environment jsdom

import CvCertificationsEditor from '@/components/cvs/CvCertificationsEditor.vue';
import CvEducationEditor from '@/components/cvs/CvEducationEditor.vue';
import CvProjectsEditor from '@/components/cvs/CvProjectsEditor.vue';
import CvWorkExperiencesEditor from '@/components/cvs/CvWorkExperiencesEditor.vue';
import MonthYearInput from '@/components/cvs/MonthYearInput.vue';
import type { CvCertificationFormInput, CvEducationFormInput, CvProjectFormInput, CvWorkExperienceFormInput } from '@/types';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import { defineComponent, h, reactive } from 'vue';

const workExperiences = (): CvWorkExperienceFormInput[] => [
    {
        employer: 'Acme',
        role: 'Desarrolladora',
        location: '',
        start_date: '2024-01',
        end_date: '2025-02',
        is_current: false,
        highlights: [],
    },
];

const educationEntries = (): CvEducationFormInput[] => [
    {
        institution: 'Universidad',
        qualification: 'Ingeniería',
        field_of_study: '',
        location: '',
        start_date: '2018-03',
        end_date: '2023-12',
        is_current: false,
        description: '',
    },
];

const projects = (): CvProjectFormInput[] => [
    {
        name: 'VitaeTex',
        role: '',
        description: '',
        url: '',
        start_date: '2025-04',
        end_date: '2026-05',
        is_current: false,
        highlights: [],
        technologies: [],
    },
];

const certifications = (): CvCertificationFormInput[] => [
    {
        name: 'Certificación',
        issuer: 'Entidad',
        issued_on: '2022-06',
        expires_on: '2027-07',
        credential_id: '',
        credential_url: '',
    },
];

const mountEditors = () => {
    const state = {
        workExperiences: reactive(workExperiences()),
        educationEntries: reactive(educationEntries()),
        projects: reactive(projects()),
        certifications: reactive(certifications()),
    };
    const Harness = defineComponent({
        setup() {
            return () =>
                h('main', [
                    h(CvWorkExperiencesEditor, { modelValue: state.workExperiences, errors: {} }),
                    h(CvEducationEditor, { modelValue: state.educationEntries, errors: {} }),
                    h(CvProjectsEditor, { modelValue: state.projects, errors: {} }),
                    h(CvCertificationsEditor, { modelValue: state.certifications, errors: {} }),
                ]);
        },
    });

    return { wrapper: mount(Harness, { attachTo: document.body }), state };
};

afterEach(() => {
    document.body.innerHTML = '';
});

describe('CV month and year fields', () => {
    it('uses the shared control for all eight persisted monthly dates', () => {
        const { wrapper } = mountEditors();
        const fields = wrapper.findAllComponents(MonthYearInput);

        expect(fields).toHaveLength(8);
        expect(fields.map((field) => field.props('id'))).toEqual([
            'work-experience-0-start-date',
            'work-experience-0-end-date',
            'education-0-start-date',
            'education-0-end-date',
            'project-0-start-date',
            'project-0-end-date',
            'certification-0-issued-on',
            'certification-0-expires-on',
        ]);
        expect(wrapper.find('input[type="month"]').exists()).toBe(false);
        expect(wrapper.get<HTMLSelectElement>('#work-experience-0-start-date').element.value).toBe('01');
        expect(wrapper.get<HTMLInputElement>('#work-experience-0-start-date-year').element.value).toBe('2024');

        wrapper.unmount();
    });

    it('clears and disables each applicable end date when marked as current', async () => {
        const { wrapper, state } = mountEditors();

        await wrapper.get('#work-experience-0-is-current').trigger('click');
        await wrapper.get('#education-0-is-current').trigger('click');
        await wrapper.get('#project-0-is-current').trigger('click');
        await flushPromises();

        expect(state.workExperiences[0]?.end_date).toBe('');
        expect(state.educationEntries[0]?.end_date).toBe('');
        expect(state.projects[0]?.end_date).toBe('');
        expect(wrapper.get('#work-experience-0-end-date').attributes()).toHaveProperty('disabled');
        expect(wrapper.get('#education-0-end-date').attributes()).toHaveProperty('disabled');
        expect(wrapper.get('#project-0-end-date').attributes()).toHaveProperty('disabled');

        wrapper.unmount();
    });
});
