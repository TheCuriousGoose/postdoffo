import { reactive } from 'vue';

/**
 * A single, app-wide "variable inspector" popover. Any highlighted
 * `{{variable}}` can call `open(key, x, y)` when clicked, and one
 * `<VariableInspector />` mounted near the editor renders the panel at that
 * point. Keeping it a module singleton avoids threading refs through every
 * input, header row and body editor.
 */
type InspectorState = {
    visible: boolean;
    key: string | null;
    x: number;
    y: number;
};

const state = reactive<InspectorState>({
    visible: false,
    key: null,
    x: 0,
    y: 0,
});

export function useVariableInspector() {
    function open(key: string, x: number, y: number): void {
        state.key = key;
        state.x = x;
        state.y = y;
        state.visible = true;
    }

    function inspect(key: string): void {
        state.key = key;
    }

    function close(): void {
        state.visible = false;
        state.key = null;
    }

    return { state, open, inspect, close };
}
