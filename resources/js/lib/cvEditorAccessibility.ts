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

const targetForErrorPath = (path: string): HTMLElement | undefined => {
    const exactId = simpleFieldIds[path] ?? nestedFieldId(path);
    const fallbackIds = collectionFallbackIds[path.split('.')[0] ?? ''] ?? [];
    const candidates = exactId ? [exactId, ...fallbackIds] : fallbackIds;

    for (const id of candidates) {
        const element = focusableElement(id);

        if (element) {
            return element;
        }
    }
};

const compareDomOrder = (left: HTMLElement, right: HTMLElement): number => {
    const position = left.compareDocumentPosition(right);

    if (position & Node.DOCUMENT_POSITION_FOLLOWING) {
        return -1;
    }

    if (position & Node.DOCUMENT_POSITION_PRECEDING) {
        return 1;
    }

    return 0;
};

const scrollToErrorTarget = (element: HTMLElement): void => {
    const editorPanel = document.getElementById('editor-panel');
    const usesDesktopEditorScroll =
        editorPanel instanceof HTMLElement && editorPanel.contains(element) && (window.matchMedia?.('(min-width: 1024px)').matches ?? false);

    if (!usesDesktopEditorScroll) {
        element.scrollIntoView({ block: 'center' });

        return;
    }

    const panelRect = editorPanel.getBoundingClientRect();
    const targetRect = element.getBoundingClientRect();
    const centeredTop = editorPanel.scrollTop + targetRect.top - panelRect.top - (editorPanel.clientHeight - targetRect.height) / 2;

    editorPanel.scrollTo({ top: Math.max(0, centeredTop) });
};

export interface CvEditorErrorSummaryItem {
    path: string;
    message: string;
    targetId?: string;
}

export const cvEditorErrorSummaryItems = (errors: Partial<Record<string, string>>): CvEditorErrorSummaryItem[] =>
    Object.entries(errors)
        .flatMap(([path, message], index) => {
            if (!message) {
                return [];
            }

            return [{ path, message, target: targetForErrorPath(path), index }];
        })
        .sort((left, right) => {
            if (left.target && right.target) {
                return compareDomOrder(left.target, right.target);
            }

            if (left.target) {
                return -1;
            }

            if (right.target) {
                return 1;
            }

            return left.index - right.index;
        })
        .map(({ path, message, target }) => ({ path, message, targetId: target?.id }));

export const focusCvEditorError = (path: string): boolean => {
    const target = targetForErrorPath(path);

    if (!target) {
        return false;
    }

    target.focus({ preventScroll: true });
    scrollToErrorTarget(target);

    return true;
};

export const focusFirstCvEditorError = (errors: Partial<Record<string, string>>): boolean => {
    const firstTarget = cvEditorErrorSummaryItems(errors).find((item) => item.targetId);

    return firstTarget ? focusCvEditorError(firstTarget.path) : false;
};
