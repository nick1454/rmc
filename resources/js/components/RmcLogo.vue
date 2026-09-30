<script setup lang="ts">
interface Props {
    size?: 'sm' | 'md' | 'lg' | 'xl';
    variant?: 'full' | 'compact' | 'icon';
    theme?: 'dark' | 'light';
}

withDefaults(defineProps<Props>(), {
    size: 'md',
    variant: 'full',
    theme: 'light',
});
</script>

<template>
    <div class="inline-flex items-center gap-3 select-none">
        <!-- Logo Icon: Concrete Drum & Hexagonal Batch Symbol -->
        <div
            :class="[
                'relative flex items-center justify-center rounded-xl transition-transform hover:scale-105 shadow-md',
                size === 'sm' ? 'w-8 h-8' : '',
                size === 'md' ? 'w-10 h-10' : '',
                size === 'lg' ? 'w-14 h-14' : '',
                size === 'xl' ? 'w-20 h-20' : '',
                'bg-gradient-to-br from-amber-500 via-orange-500 to-amber-600 shadow-amber-500/20 ring-1 ring-amber-400/40'
            ]"
        >
            <svg
                :class="[
                    'text-white transition-transform duration-700 hover:rotate-45',
                    size === 'sm' ? 'w-5 h-5' : '',
                    size === 'md' ? 'w-6 h-6' : '',
                    size === 'lg' ? 'w-8 h-8' : '',
                    size === 'xl' ? 'w-12 h-12' : '',
                ]"
                viewBox="0 0 48 48"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <!-- Outer Hexagonal Structural Framework -->
                <polygon
                    points="24,4 42,14 42,34 24,44 6,34 6,14"
                    stroke="currentColor"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="opacity-40"
                />
                <!-- Central Rotating Transit Mixer Drum / Concrete Flow Helix -->
                <path
                    d="M16 16C18.5 13 29.5 13 32 16C34.5 19 33 29 30 32C27 35 21 35 18 32C15 29 13.5 19 16 16Z"
                    fill="currentColor"
                    fill-opacity="0.25"
                    stroke="currentColor"
                    stroke-width="2.5"
                    stroke-linecap="round"
                />
                <path
                    d="M20 20L28 28M28 20L20 28"
                    stroke="currentColor"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
                <!-- Dynamic Batch Accent Dots -->
                <circle cx="24" cy="9" r="2" fill="#FEF08A" />
                <circle cx="37" cy="24" r="2" fill="#FEF08A" />
                <circle cx="11" cy="24" r="2" fill="#FEF08A" />
                <circle cx="24" cy="39" r="2" fill="#FEF08A" />
            </svg>
        </div>

        <!-- Typography Branding -->
        <div v-if="variant !== 'icon'" class="flex flex-col text-left">
            <div class="flex items-center gap-1.5 leading-none">
                <span
                    :class="[
                        'font-black tracking-wider uppercase font-sans',
                        size === 'sm' ? 'text-lg' : '',
                        size === 'md' ? 'text-xl' : '',
                        size === 'lg' ? 'text-2xl' : '',
                        size === 'xl' ? 'text-4xl' : '',
                        theme === 'dark'
                            ? 'bg-gradient-to-r from-white via-slate-100 to-amber-200 bg-clip-text text-transparent'
                            : 'text-slate-900'
                    ]"
                >
                    RMC
                </span>
                <span
                    class="px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-widest bg-amber-100 text-amber-800 border border-amber-300 rounded-md"
                >
                    ERP
                </span>
            </div>

            <span
                v-if="variant === 'full'"
                :class="[
                    'font-semibold tracking-widest uppercase',
                    size === 'sm' ? 'text-[8px]' : 'text-[10px]',
                    'text-amber-600 font-bold'
                ]"
            >
                READY MIX CONCRETE
            </span>
        </div>
    </div>
</template>
