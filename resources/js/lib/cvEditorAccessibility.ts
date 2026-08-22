const simpleFieldIds: Record<string, string> = {
    title: 'cv-title',
    template_key: 'cv-title',
    full_name: 'cv-full-name',
    professional_headline: 'cv-professional-headline',
    contact_email: 'cv-contact-email',
    phone: 'cv-phone',
    location: 'cv-location',
    professional_summary: 'cv-professional-summary',
};

const collectionFallbackIds: Record<string, string[]> = {
    work_experiences: ['work-experience-0-employer', 'add-work-experience'],
    education_entries: ['education-0-institution', 'add-education'],
    skill_groups: ['skill-group-0-name', 'add-skill-group'],
    projects: ['project-0-name', 'add-project'],
    certifications: ['certification-0-name', 'add-certification'],
    links: ['link-0-type', 'add-link'],
};

const nestedFieldId = (path: string): string | undefined => {
    let match = path.match(/^work_experiences\.(\d+)\.highlights\.(\d+)$/);

    if (match) {
        return `work-experience-${match[1]}-highlight-${match[2]}`;
    }

    match = path.match(/^work_experiences\.(\d+)\.highlights$/);

    if (match) {
        return `work-experience-${match[1]}-highlight-0`;
    }

    match = path.match(/^work_experiences\.(\d+)\.(employer|role|location|start_date|end_date|is_current)$/);

    if (match) {
        return `work-experience-${match[1]}-${match[2]?.replaceAll('_', '-')}`;
    }

    match = path.match(/^education_entries\.(\d+)\.(institution|qualification|field_of_study|location|start_date|end_date|is_current|description)$/);

    if (match) {
        return `education-${match[1]}-${match[2]?.replaceAll('_', '-')}`;
    }

    match = path.match(/^skill_groups\.(\d+)\.skills\.(\d+)\.name$/);

    if (match) {
        return `skill-group-${match[1]}-skill-${match[2]}`;
    }

    match = path.match(/^skill_groups\.(\d+)\.skills$/);

    if (match) {
        return `skill-group-${match[1]}-skill-0`;
    }

    match = path.match(/^skill_groups\.(\d+)\.name$/);

    if (match) {
        return `skill-group-${match[1]}-name`;
    }

    match = path.match(/^projects\.(\d+)\.(highlights|technologies)\.(\d+)$/);

    if (match) {
        const item = match[2] === 'highlights' ? 'highlight' : 'technology';

        return `project-${match[1]}-${item}-${match[3]}`;
    }

    match = path.match(/^projects\.(\d+)\.(highlights|technologies)$/);

    if (match) {
        const item = match[2] === 'highlights' ? 'highlight' : 'technology';

        return `project-${match[1]}-${item}-0`;
    }

    match = path.match(/^projects\.(\d+)\.(name|role|description|url|start_date|end_date|is_current)$/);

    if (match) {
        return `project-${match[1]}-${match[2]?.replaceAll('_', '-')}`;
    }

    match = path.match(/^certifications\.(\d+)\.(name|issuer|issued_on|expires_on|credential_id|credential_url)$/);

    if (match) {
        return `certification-${match[1]}-${match[2]?.replaceAll('_', '-')}`;
    }

    match = path.match(/^links\.(\d+)\.(type|label|url)$/);

    if (match) {
        return `link-${match[1]}-${match[2]}`;
    }
};

const focusableElement = (id: string) => {
    const element = document.getElementById(id);

    return element instanceof HTMLElement && !element.hasAttribute('disabled') ? element : undefined;
};

export const focusFirstCvEditorError = (errors: Partial<Record<string, string>>): boolean => {
    for (const path of Object.keys(errors)) {
        const exactId = simpleFieldIds[path] ?? nestedFieldId(path);
        const fallbackIds = collectionFallbackIds[path.split('.')[0] ?? ''] ?? [];
        const candidates = exactId ? [exactId, ...fallbackIds] : fallbackIds;

        for (const id of candidates) {
            const element = focusableElement(id);

            if (element) {
                element.focus();
                element.scrollIntoView({ block: 'center' });

                return true;
            }
        }
    }

    return false;
};
