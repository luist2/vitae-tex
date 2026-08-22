import { nextTick, ref } from 'vue';

export const indexAfterRemoval = (removedIndex: number, remainingItems: number): number | null => {
    if (remainingItems === 0) {
        return null;
    }

    return Math.min(removedIndex, remainingItems - 1);
};

export const remainingItemsMessage = (count: number, singular: string, plural: string) =>
    `${count === 1 ? 'Queda' : 'Quedan'} ${count} ${count === 1 ? singular : plural}.`;

export const useAccessibleCollection = () => {
    const announcement = ref('');

    const completeAction = async (message: string, focusId?: string) => {
        await nextTick();

        if (focusId) {
            document.getElementById(focusId)?.focus();
        }

        announcement.value = '';
        await nextTick();
        announcement.value = message;
    };

    return {
        announcement,
        completeAction,
    };
};
