// @vitest-environment jsdom

import { indexAfterRemoval, remainingItemsMessage, useAccessibleCollection } from '@/composables/useAccessibleCollection';
import { describe, expect, it } from 'vitest';

describe('useAccessibleCollection', () => {
    it('chooses the next surviving item after a removal', () => {
        expect(indexAfterRemoval(0, 0)).toBeNull();
        expect(indexAfterRemoval(1, 3)).toBe(1);
        expect(indexAfterRemoval(3, 3)).toBe(2);
    });

    it('describes remaining items with the correct singular or plural form', () => {
        expect(remainingItemsMessage(1, 'grupo', 'grupos')).toBe('Queda 1 grupo.');
        expect(remainingItemsMessage(0, 'grupo', 'grupos')).toBe('Quedan 0 grupos.');
        expect(remainingItemsMessage(2, 'grupo', 'grupos')).toBe('Quedan 2 grupos.');
    });

    it('focuses the requested target and publishes a repeatable status', async () => {
        document.body.innerHTML = '<button id="target" type="button">Destino</button>';
        const { announcement, completeAction } = useAccessibleCollection();

        await completeAction('Elemento movido.', 'target');
        expect(document.activeElement).toBe(document.getElementById('target'));
        expect(announcement.value).toBe('Elemento movido.');

        await completeAction('Elemento movido.', 'target');
        expect(announcement.value).toBe('Elemento movido.');
    });
});
