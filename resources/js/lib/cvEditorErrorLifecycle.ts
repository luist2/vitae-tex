const simpleControlPaths: Record<string, string> = {
    'cv-title': 'title',
    'cv-full-name': 'full_name',
    'cv-professional-headline': 'professional_headline',
    'cv-contact-email': 'contact_email',
    'cv-phone': 'phone',
    'cv-location': 'location',
    'cv-professional-summary': 'professional_summary',
};

const fieldName = (value: string) => value.replaceAll('-', '_');

export const cvEditorErrorPathForControlId = (controlId: string): string | undefined => {
    const normalizedId = controlId.endsWith('-year') ? controlId.slice(0, -'-year'.length) : controlId;
    const simplePath = simpleControlPaths[normalizedId];

    if (simplePath) {
        return simplePath;
    }

    let match = normalizedId.match(/^work-experience-(\d+)-highlight-(\d+)$/);

    if (match) {
        return `work_experiences.${match[1]}.highlights.${match[2]}`;
    }

    match = normalizedId.match(/^work-experience-(\d+)-(employer|role|location|start-date|end-date|is-current)$/);

    if (match) {
        return `work_experiences.${match[1]}.${fieldName(match[2] ?? '')}`;
    }

    match = normalizedId.match(/^education-(\d+)-(institution|qualification|field-of-study|location|start-date|end-date|is-current|description)$/);

    if (match) {
        return `education_entries.${match[1]}.${fieldName(match[2] ?? '')}`;
    }

    match = normalizedId.match(/^skill-group-(\d+)-skill-(\d+)$/);

    if (match) {
        return `skill_groups.${match[1]}.skills.${match[2]}.name`;
    }

    match = normalizedId.match(/^skill-group-(\d+)-name$/);

    if (match) {
        return `skill_groups.${match[1]}.name`;
    }

    match = normalizedId.match(/^project-(\d+)-(highlight|technology)-(\d+)$/);

    if (match) {
        const collection = match[2] === 'highlight' ? 'highlights' : 'technologies';

        return `projects.${match[1]}.${collection}.${match[3]}`;
    }

    match = normalizedId.match(/^project-(\d+)-(name|role|description|url|start-date|end-date|is-current)$/);

    if (match) {
        return `projects.${match[1]}.${fieldName(match[2] ?? '')}`;
    }

    match = normalizedId.match(/^certification-(\d+)-(name|issuer|issued-on|expires-on|credential-id|credential-url)$/);

    if (match) {
        return `certifications.${match[1]}.${fieldName(match[2] ?? '')}`;
    }

    match = normalizedId.match(/^link-(\d+)-(type|label|url)$/);

    if (match) {
        return `links.${match[1]}.${match[2]}`;
    }
};

export const cvEditorErrorPathsForFieldChange = (path: string): string[] => {
    const paths = new Set([path]);
    let match = path.match(/^(work_experiences|education_entries)\.(\d+)\.(start_date|end_date|is_current)$/);

    if (match && match[3] !== 'end_date') {
        paths.add(`${match[1]}.${match[2]}.end_date`);
    }

    match = path.match(/^projects\.(\d+)\.(start_date|end_date|is_current)$/);

    if (match) {
        paths.add(`projects.${match[1]}.start_date`);
        paths.add(`projects.${match[1]}.end_date`);
    }

    match = path.match(/^certifications\.(\d+)\.(issued_on|expires_on)$/);

    if (match) {
        paths.add(`certifications.${match[1]}.issued_on`);
        paths.add(`certifications.${match[1]}.expires_on`);
    }

    match = path.match(/^links\.(\d+)\.type$/);

    if (match) {
        paths.add(`links.${match[1]}.label`);
    }

    if (path === 'contact_email' || path === 'phone' || path.startsWith('links.')) {
        paths.add('contact_email');
    }

    return [...paths];
};

export const matchingCvEditorErrorPaths = (errors: Partial<Record<string, string>>, path: string, includeDescendants = false): string[] =>
    Object.keys(errors).filter((errorPath) => errorPath === path || (includeDescendants && errorPath.startsWith(`${path}.`)));
