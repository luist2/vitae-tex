import { cvEditorErrorPathForControlId, cvEditorErrorPathsForFieldChange, matchingCvEditorErrorPaths } from '@/lib/cvEditorErrorLifecycle';
import { describe, expect, it } from 'vitest';

describe('CV editor error lifecycle', () => {
    it.each([
        ['cv-full-name', 'full_name'],
        ['work-experience-2-start-date-year', 'work_experiences.2.start_date'],
        ['work-experience-1-highlight-3', 'work_experiences.1.highlights.3'],
        ['education-4-field-of-study', 'education_entries.4.field_of_study'],
        ['skill-group-2-skill-5', 'skill_groups.2.skills.5.name'],
        ['project-3-technology-7', 'projects.3.technologies.7'],
        ['certification-1-credential-url', 'certifications.1.credential_url'],
        ['link-6-type', 'links.6.type'],
    ])('maps control %s to validation path %s', (controlId, path) => {
        expect(cvEditorErrorPathForControlId(controlId)).toBe(path);
    });

    it('clears the edited field and only its validation dependencies', () => {
        expect(cvEditorErrorPathsForFieldChange('work_experiences.1.is_current')).toEqual([
            'work_experiences.1.is_current',
            'work_experiences.1.end_date',
        ]);
        expect(cvEditorErrorPathsForFieldChange('links.2.type')).toEqual(['links.2.type', 'links.2.label', 'contact_email']);
        expect(cvEditorErrorPathsForFieldChange('projects.0.name')).toEqual(['projects.0.name']);
    });

    it('can invalidate an indexed collection without touching unrelated collections', () => {
        const errors = {
            'work_experiences.0.employer': 'Falta empleador.',
            'work_experiences.1.role': 'Falta cargo.',
            'education_entries.0.institution': 'Falta institución.',
        };

        expect(matchingCvEditorErrorPaths(errors, 'work_experiences', true)).toEqual(['work_experiences.0.employer', 'work_experiences.1.role']);
        expect(matchingCvEditorErrorPaths(errors, 'work_experiences.0.employer')).toEqual(['work_experiences.0.employer']);
    });
});
