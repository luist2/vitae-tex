import type { PageProps as InertiaPageProps } from '@inertiajs/core';
import type { LucideIcon } from 'lucide-vue-next';

export interface ToastFlash {
    type: 'success';
    message: string;
}

declare module '@inertiajs/core' {
    interface InertiaConfig {
        flashDataType: {
            toast?: ToastFlash;
        };
    }
}

export type {
    CvCertificationFormInput,
    CvCertificationInput,
    CvEditorData,
    CvEditorFormData,
    CvEducationFormInput,
    CvEducationInput,
    CvLinkFormInput,
    CvLinkInput,
    CvLinkType,
    CvProjectFormInput,
    CvProjectInput,
    CvSkillGroupInput,
    CvSkillInput,
    CvTemplateDefinition,
    CvTemplateSection,
    CvWorkExperienceFormInput,
    CvWorkExperienceInput,
} from './cv';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface SharedData extends InertiaPageProps {
    auth: Auth;
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface CvSummary {
    id: number;
    title: string;
    template_key: string;
    updated_at: string;
}

export interface User {
    id: number;
    email: string;
    avatar?: string;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
