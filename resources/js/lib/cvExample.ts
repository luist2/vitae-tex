import type { BasicEditorFormData } from './cvEditorForm';

export type CvEditorContentData = Omit<BasicEditorFormData, 'title' | 'template_key'>;

const textFields = ['full_name', 'professional_headline', 'contact_email', 'phone', 'location', 'professional_summary'] as const;

const collectionFields = ['work_experiences', 'education_entries', 'skill_groups', 'projects', 'certifications', 'links'] as const;

export const createCvExampleContent = (): CvEditorContentData => ({
    full_name: 'Camila Torres Rojas',
    professional_headline: 'Ingeniera de software | Laravel, Vue y PostgreSQL',
    contact_email: 'camila.torres@example.com',
    phone: '+56 9 1234 5678',
    location: 'Santiago, Chile',
    professional_summary:
        'Ingeniera de software con 5 años de experiencia desarrollando aplicaciones web para operaciones y servicios B2B. Especializada en Laravel, Vue y PostgreSQL, con foco en diseño de soluciones mantenibles, pruebas automatizadas y mejora continua. Acostumbrada a colaborar con producto y diseño, traducir necesidades de negocio en entregas incrementales y acompañar funcionalidades desde su definición hasta producción.',
    work_experiences: [
        {
            employer: 'Norte Digital SpA',
            role: 'Ingeniera de Software',
            location: 'Santiago, Chile · Híbrido',
            start_date: '2023-07',
            end_date: '',
            is_current: true,
            highlights: [
                'Desarrollé módulos de facturación y conciliación utilizados diariamente por más de 40 clientes B2B.',
                'Reduje en un 35 % el tiempo de respuesta de los reportes principales mediante optimización de consultas, índices y procesamiento por lotes.',
                'Elevé la cobertura de pruebas del módulo de pagos de 62 % a 86 % e incorporé controles automáticos de calidad en integración continua.',
                'Coordiné entregas con producto, soporte y diseño, dividiendo iniciativas trimestrales en incrementos desplegables y medibles.',
            ],
        },
        {
            employer: 'Mercado Local Labs',
            role: 'Desarrolladora Full Stack',
            location: 'Remoto, Chile',
            start_date: '2021-01',
            end_date: '2023-06',
            is_current: false,
            highlights: [
                'Migré flujos críticos desde una aplicación heredada hacia módulos Laravel y Vue sin interrumpir la operación de los usuarios.',
                'Implementé integraciones de pagos y notificaciones con manejo de reintentos, idempotencia y trazabilidad de errores.',
                'Construí tableros operativos que redujeron de horas a minutos la preparación del reporte semanal del equipo de soporte.',
                'Participé en revisiones de código y documentación técnica para mantener criterios compartidos dentro de un equipo de seis personas.',
            ],
        },
    ],
    education_entries: [
        {
            institution: 'Universidad de Santiago de Chile',
            qualification: 'Ingeniería Civil Informática',
            field_of_study: 'Ingeniería de software',
            location: 'Santiago, Chile',
            start_date: '2016-03',
            end_date: '2020-12',
            is_current: false,
            description:
                'Proyecto de título: plataforma para analizar tiempos de atención y visualizar indicadores operativos, desarrollada con una API web, PostgreSQL y un panel interactivo.',
        },
    ],
    skill_groups: [
        {
            name: 'Backend',
            skills: [{ name: 'PHP' }, { name: 'Laravel' }, { name: 'Eloquent' }, { name: 'APIs REST' }],
        },
        {
            name: 'Frontend',
            skills: [{ name: 'TypeScript' }, { name: 'Vue 3' }, { name: 'Inertia.js' }, { name: 'Tailwind CSS' }],
        },
        {
            name: 'Datos',
            skills: [{ name: 'PostgreSQL' }, { name: 'Modelado relacional' }, { name: 'Optimización de consultas' }],
        },
        {
            name: 'Calidad y entrega',
            skills: [{ name: 'PHPUnit' }, { name: 'Vitest' }, { name: 'Docker' }, { name: 'GitHub Actions' }],
        },
    ],
    projects: [
        {
            name: 'TurnoSimple',
            role: 'Creadora',
            description:
                'Aplicación web para que pequeños centros de atención administren disponibilidad, reservas y recordatorios desde un único panel.',
            url: 'https://example.com/proyectos/turnosimple',
            start_date: '2024-09',
            end_date: '',
            is_current: true,
            highlights: [
                'Diseñé el modelo de reservas con prevención de solapamientos y control de acceso por organización.',
                'Implementé pruebas de integración para los flujos de agenda, cancelación y aislamiento de datos entre cuentas.',
                'Automaticé el build, las migraciones de prueba y los controles de calidad mediante GitHub Actions.',
            ],
            technologies: ['Laravel', 'Vue 3', 'TypeScript', 'PostgreSQL'],
        },
        {
            name: 'Observatorio de precios',
            role: 'Desarrolladora',
            description:
                'Proyecto personal para consolidar series históricas de precios, explorar variaciones y exportar comparaciones reproducibles.',
            url: 'https://example.com/proyectos/observatorio-precios',
            start_date: '2022-04',
            end_date: '2022-10',
            is_current: false,
            highlights: [
                'Construí un proceso de importación validado que informa errores por fila sin interrumpir archivos completos.',
                'Añadí filtros guardables y exportación CSV para facilitar análisis posteriores.',
            ],
            technologies: ['PHP', 'Laravel', 'PostgreSQL', 'Chart.js'],
        },
    ],
    certifications: [
        {
            name: 'AWS Certified Cloud Practitioner',
            issuer: 'Amazon Web Services',
            issued_on: '2023-11',
            expires_on: '2026-11',
            credential_id: 'AWS-CCP-DEMO-001',
            credential_url: 'https://example.com/credenciales/AWS-CCP-DEMO-001',
        },
    ],
    links: [
        {
            type: 'linkedin',
            label: '',
            url: 'https://www.linkedin.com/in/camila-torres-rojas-demo',
        },
        {
            type: 'github',
            label: '',
            url: 'https://github.com/camila-torres-demo',
        },
        {
            type: 'portfolio',
            label: '',
            url: 'https://example.com/camila-torres',
        },
    ],
});

export const hasCvEditorContent = (data: CvEditorContentData): boolean =>
    textFields.some((field) => data[field].trim() !== '') || collectionFields.some((field) => data[field].length > 0);

export const replaceCvContentWithExample = (data: BasicEditorFormData, confirmReplacement: () => boolean): boolean => {
    if (hasCvEditorContent(data) && !confirmReplacement()) {
        return false;
    }

    Object.assign(data, createCvExampleContent());

    return true;
};
