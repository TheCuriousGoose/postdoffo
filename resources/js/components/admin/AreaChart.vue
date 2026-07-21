<script setup lang="ts">
import { useElementSize } from '@vueuse/core';
import { computed, ref } from 'vue';
import type { ChartPoint } from '@/types';

const props = withDefaults(
    defineProps<{
        points: ChartPoint[];
        height?: number;
        formatValue?: (value: number) => string;
    }>(),
    {
        height: 200,
    },
);

const container = ref<HTMLElement | null>(null);
const { width } = useElementSize(container);

// Fall back to a sensible width before the element is measured so the very
// first paint (and SSR) still renders a usable curve instead of collapsing.
const chartWidth = computed(() => Math.max(width.value || 640, 1));

const padding = { top: 16, right: 4, bottom: 4, left: 4 };

const maxValue = computed(() =>
    Math.max(1, ...props.points.map((point) => point.value)),
);

const geometry = computed(() => {
    const innerW = chartWidth.value - padding.left - padding.right;
    const innerH = props.height - padding.top - padding.bottom;
    const count = props.points.length;

    return props.points.map((point, index) => {
        const x =
            count <= 1
                ? padding.left + innerW / 2
                : padding.left + (index / (count - 1)) * innerW;
        const y =
            padding.top + innerH - (point.value / maxValue.value) * innerH;

        return { x, y, point };
    });
});

// Catmull-Rom → cubic Bézier: gives the line a soft, deliberate curve instead
// of the jagged look of raw segments, without over-smoothing the data.
const linePath = computed(() => {
    const pts = geometry.value;

    if (pts.length === 0) {
        return '';
    }

    if (pts.length === 1) {
        return `M ${pts[0].x} ${pts[0].y}`;
    }

    let d = `M ${pts[0].x} ${pts[0].y}`;

    for (let i = 0; i < pts.length - 1; i++) {
        const p0 = pts[i - 1] ?? pts[i];
        const p1 = pts[i];
        const p2 = pts[i + 1];
        const p3 = pts[i + 2] ?? p2;

        const cp1x = p1.x + (p2.x - p0.x) / 6;
        const cp1y = p1.y + (p2.y - p0.y) / 6;
        const cp2x = p2.x - (p3.x - p1.x) / 6;
        const cp2y = p2.y - (p3.y - p1.y) / 6;

        d += ` C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${p2.x} ${p2.y}`;
    }

    return d;
});

const areaPath = computed(() => {
    const pts = geometry.value;

    if (pts.length === 0) {
        return '';
    }

    const baseline = props.height - padding.bottom;

    return `${linePath.value} L ${pts[pts.length - 1].x} ${baseline} L ${pts[0].x} ${baseline} Z`;
});

const format = (value: number) =>
    props.formatValue ? props.formatValue(value) : value.toLocaleString();

const gradientId = `area-gradient-${Math.random().toString(36).slice(2, 9)}`;

const activeIndex = ref<number | null>(null);
const active = computed(() =>
    activeIndex.value === null ? null : geometry.value[activeIndex.value],
);

function onMove(event: PointerEvent) {
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    const x = event.clientX - rect.left;
    let nearest = 0;
    let best = Infinity;
    geometry.value.forEach((g, i) => {
        const dist = Math.abs(g.x - x);

        if (dist < best) {
            best = dist;
            nearest = i;
        }
    });
    activeIndex.value = nearest;
}

const tooltipLeft = computed(() => {
    if (!active.value) {
        return 0;
    }

    // Keep the tooltip inside the chart bounds near the edges.
    const half = 48;

    return Math.min(Math.max(active.value.x, half), chartWidth.value - half);
});
</script>

<template>
    <div ref="container" class="relative w-full text-primary">
        <svg
            :width="chartWidth"
            :height="height"
            :viewBox="`0 0 ${chartWidth} ${height}`"
            class="block w-full overflow-visible"
            @pointermove="onMove"
            @pointerleave="activeIndex = null"
        >
            <defs>
                <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
                    <stop
                        offset="0%"
                        stop-color="currentColor"
                        stop-opacity="0.22"
                    />
                    <stop
                        offset="100%"
                        stop-color="currentColor"
                        stop-opacity="0"
                    />
                </linearGradient>
            </defs>

            <path :d="areaPath" :fill="`url(#${gradientId})`" />
            <path
                :d="linePath"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            />

            <template v-if="active">
                <line
                    :x1="active.x"
                    :x2="active.x"
                    :y1="padding.top - 8"
                    :y2="height - padding.bottom"
                    stroke="currentColor"
                    stroke-opacity="0.25"
                    stroke-width="1"
                    stroke-dasharray="4 4"
                />
                <circle
                    :cx="active.x"
                    :cy="active.y"
                    r="4"
                    fill="var(--background)"
                    stroke="currentColor"
                    stroke-width="2"
                />
            </template>
        </svg>

        <div
            v-if="active"
            class="pointer-events-none absolute top-0 -translate-x-1/2 rounded-md border bg-popover px-2.5 py-1.5 text-center shadow-md"
            :style="{ left: `${tooltipLeft}px` }"
        >
            <div class="text-sm font-semibold text-foreground tabular-nums">
                {{ format(active.point.value) }}
            </div>
            <div class="text-xs text-muted-foreground">
                {{ active.point.label }}
            </div>
        </div>
    </div>
</template>
