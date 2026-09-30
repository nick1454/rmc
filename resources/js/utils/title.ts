export const APP_NAME = 'RMC - Ready Mix Concrete ERP';

/**
 * Dynamically set document title: [Section Name] | [App Name]
 */
export function setDocumentTitle(sectionTitle?: string): void {
    if (typeof document === 'undefined') return;

    if (sectionTitle && sectionTitle.trim()) {
        document.title = `${sectionTitle.trim()} | ${APP_NAME}`;
    } else {
        document.title = APP_NAME;
    }
}
