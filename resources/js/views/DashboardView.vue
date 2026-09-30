<script setup lang="ts">
import AppLayout from '../components/layout/AppLayout.vue';
</script>

<template>
    <AppLayout title="Dashboard" subtitle="Ready Mix Concrete Overview & Operations">
        <!-- Clean minimal dashboard container -->
        <div class="h-full min-h-[450px] flex items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-white p-8 text-center shadow-xs">
            <div class="max-w-md space-y-3">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 border border-amber-200">
                    <span class="text-xl">🏗️</span>
                </div>
                <h3 class="text-base font-bold text-slate-900">Dashboard Workspace Ready</h3>
                <p class="text-xs text-slate-500 leading-relaxed font-medium">
                    Sidebar navigation has been set up. The dashboard widgets, KPI charts, and live streams will be designed here next.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
