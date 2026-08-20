export type CvTemplateSection = 'professional_summary' | 'education' | 'work_experience' | 'projects' | 'skills' | 'certifications';

export interface CvTemplateDefinition {
    key: string;
    name: string;
    sections: CvTemplateSection[];
}

export interface CvWorkExperienceInput {
    employer: string;
    role: string;
    location: string | null;
    start_date: string;
    end_date: string | null;
    is_current: boolean;
    highlights: string[];
}

export interface CvWorkExperienceFormInput extends Omit<CvWorkExperienceInput, 'location' | 'end_date'> {
    location: string;
    end_date: string;
}

export interface CvEducationInput {
    institution: string;
    qualification: string;
    field_of_study: string | null;
    location: string | null;
    start_date: string;
    end_date: string | null;
    is_current: boolean;
    description: string | null;
}

export interface CvEducationFormInput extends Omit<CvEducationInput, 'field_of_study' | 'location' | 'end_date' | 'description'> {
    field_of_study: string;
    location: string;
    end_date: string;
    description: string;
}

export interface CvSkillInput {
    name: string;
}

export interface CvSkillGroupInput {
    name: string;
    skills: CvSkillInput[];
}

export interface CvProjectInput {
    name: string;
    role: string | null;
    description: string | null;
    url: string | null;
    start_date: string | null;
    end_date: string | null;
    is_current: boolean;
    highlights: string[];
    technologies: string[];
}

export interface CvProjectFormInput extends Omit<CvProjectInput, 'role' | 'description' | 'url' | 'start_date' | 'end_date'> {
    role: string;
    description: string;
    url: string;
    start_date: string;
    end_date: string;
}

export interface CvCertificationInput {
    name: string;
    issuer: string;
    issued_on: string | null;
    expires_on: string | null;
    credential_id: string | null;
    credential_url: string | null;
}

export interface CvCertificationFormInput extends Omit<CvCertificationInput, 'issued_on' | 'expires_on' | 'credential_id' | 'credential_url'> {
    issued_on: string;
    expires_on: string;
    credential_id: string;
    credential_url: string;
}

export type CvLinkType = 'linkedin' | 'github' | 'portfolio' | 'other';

export interface CvLinkInput {
    type: CvLinkType;
    label: string | null;
    url: string;
}

export interface CvLinkFormInput extends Omit<CvLinkInput, 'label'> {
    label: string;
}

export interface CvEditorFormData {
    title: string;
    template_key: string;
    full_name: string;
    professional_headline: string | null;
    contact_email: string | null;
    phone: string | null;
    location: string | null;
    professional_summary: string | null;
    work_experiences: CvWorkExperienceInput[];
    education_entries: CvEducationInput[];
    skill_groups: CvSkillGroupInput[];
    projects: CvProjectInput[];
    certifications: CvCertificationInput[];
    links: CvLinkInput[];
}

export interface CvEditorData extends CvEditorFormData {
    id: number;
    updated_at: string;
}
