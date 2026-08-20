import type {
    CvCertificationFormInput,
    CvEditorData,
    CvEditorFormData,
    CvEducationFormInput,
    CvLinkFormInput,
    CvProjectFormInput,
    CvWorkExperienceFormInput,
} from '@/types';

export type BasicEditorFormData = Omit<
    CvEditorFormData,
    | 'professional_headline'
    | 'contact_email'
    | 'phone'
    | 'location'
    | 'professional_summary'
    | 'work_experiences'
    | 'education_entries'
    | 'projects'
    | 'certifications'
    | 'links'
> & {
    professional_headline: string;
    contact_email: string;
    phone: string;
    location: string;
    professional_summary: string;
    work_experiences: CvWorkExperienceFormInput[];
    education_entries: CvEducationFormInput[];
    projects: CvProjectFormInput[];
    certifications: CvCertificationFormInput[];
    links: CvLinkFormInput[];
};

export const createCvEditorFormData = (cv: CvEditorData): BasicEditorFormData => ({
    title: cv.title,
    template_key: cv.template_key,
    full_name: cv.full_name,
    professional_headline: cv.professional_headline ?? '',
    contact_email: cv.contact_email ?? '',
    phone: cv.phone ?? '',
    location: cv.location ?? '',
    professional_summary: cv.professional_summary ?? '',
    work_experiences: cv.work_experiences.map((experience) => ({
        ...experience,
        location: experience.location ?? '',
        end_date: experience.end_date ?? '',
        highlights: [...experience.highlights],
    })),
    education_entries: cv.education_entries.map((entry) => ({
        ...entry,
        field_of_study: entry.field_of_study ?? '',
        location: entry.location ?? '',
        end_date: entry.end_date ?? '',
        description: entry.description ?? '',
    })),
    skill_groups: cv.skill_groups.map((group) => ({
        name: group.name,
        skills: group.skills.map((skill) => ({ name: skill.name })),
    })),
    projects: cv.projects.map((project) => ({
        ...project,
        role: project.role ?? '',
        description: project.description ?? '',
        url: project.url ?? '',
        start_date: project.start_date ?? '',
        end_date: project.end_date ?? '',
        highlights: [...project.highlights],
        technologies: [...project.technologies],
    })),
    certifications: cv.certifications.map((certification) => ({
        ...certification,
        issued_on: certification.issued_on ?? '',
        expires_on: certification.expires_on ?? '',
        credential_id: certification.credential_id ?? '',
        credential_url: certification.credential_url ?? '',
    })),
    links: cv.links.map((link) => ({
        ...link,
        label: link.label ?? '',
    })),
});
