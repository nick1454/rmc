import { onMounted, onUnmounted } from 'vue';

/**
 * Composable to execute a callback when the Escape key is pressed.
 *
 * @param callback Function to call on Escape keypress
 */
export function useEscapeKey(callback: () => void): void {
    const handleKeyDown = (event: KeyboardEvent) => {
        if (event.key === 'Escape' || event.key === 'Esc') {
            callback();
        }
    };

    onMounted(() => {
        window.addEventListener('keydown', handleKeyDown);
    });

    onUnmounted(() => {
        window.removeEventListener('keydown', handleKeyDown);
    });
}
