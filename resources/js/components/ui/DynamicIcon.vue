<script setup lang="ts">
import { computed } from 'vue';
import {
    LayoutDashboard,
    Truck,
    Factory,
    CalendarCheck,
    ClipboardCheck,
    Clock,
    Layers,
    FlaskConical,
    Building2,
    ShieldCheck,
    ReceiptText,
    BarChart3,
    Settings,
    User,
    FileText,
    HelpCircle,
    CircleDot,
    IndianRupee,
    CreditCard,
    Receipt,
    FileSpreadsheet
} from 'lucide-vue-next';

interface Props {
    name: string;
    class?: any;
}

const props = withDefaults(defineProps<Props>(), {
    name: 'LayoutDashboard',
    class: 'w-4 h-4',
});

const iconMap: Record<string, any> = {
    LayoutDashboard,
    Truck,
    Factory,
    CalendarCheck,
    ClipboardCheck,
    Clock,
    Layers,
    FlaskConical,
    Building2,
    ShieldCheck,
    ReceiptText,
    BarChart3,
    Settings,
    User,
    FileText,
    HelpCircle,
    CircleDot,
    IndianRupee,
    CreditCard,
    Receipt,
    FileSpreadsheet,
};

const resolvedIcon = computed(() => {
    return iconMap[props.name] || LayoutDashboard;
});
</script>

<template>
    <component :is="resolvedIcon" :class="props.class" />
</template>
