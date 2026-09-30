<script setup lang="ts">
import { onMounted } from 'vue';
import { useAuthStore } from './stores/auth';

const authStore = useAuthStore();

onMounted(() => {
    if (authStore.token) {
        authStore.fetchUser();
    }
});
</script>

<template>
    <div id="rmc-app" class="min-h-screen bg-slate-100 text-slate-800 antialiased font-sans">
        <router-view />
    </div>
</template>
