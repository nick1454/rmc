import { createRouter, createWebHistory, RouteRecordRaw } from 'vue-router';
import { setDocumentTitle } from '../utils/title';
import { useAuthStore } from '../stores/auth';
import LoginView from '../views/LoginView.vue';
import DashboardView from '../views/DashboardView.vue';
import ItemsView from '../views/items/ItemsView.vue';
import CategoriesView from '../views/categories/CategoriesView.vue';
import PartiesView from '../views/parties/PartiesView.vue';
import InwardView from '../views/inward/InwardView.vue';
import PurchasePaymentsView from '../views/payments/PurchasePaymentsView.vue';
import OutwardPaymentsView from '../views/payments/OutwardPaymentsView.vue';
import ProductionView from '../views/production/ProductionView.vue';
import OutwardView from '../views/outward/OutwardView.vue';
import StockReportView from '../views/reports/StockReportView.vue';
import DispatchPaymentReportView from '../views/reports/DispatchPaymentReportView.vue';

const routes: Array<RouteRecordRaw> = [
    {
        path: '/login',
        name: 'login',
        component: LoginView,
        meta: { guestOnly: true, title: 'Login' },
    },
    {
        path: '/dashboard',
        name: 'dashboard',
        component: DashboardView,
        meta: { requiresAuth: true, title: 'Dashboard' },
    },
    {
        path: '/items',
        name: 'items',
        component: ItemsView,
        meta: { requiresAuth: true, title: 'Item Master' },
    },
    {
        path: '/categories',
        name: 'categories',
        component: CategoriesView,
        meta: { requiresAuth: true, title: 'Category Master' },
    },
    {
        path: '/parties',
        name: 'parties',
        component: PartiesView,
        meta: { requiresAuth: true, title: 'Customer / Vendor Master' },
    },
    {
        path: '/customers',
        redirect: '/parties',
    },
    {
        path: '/vendors',
        redirect: '/parties',
    },
    {
        path: '/inward',
        name: 'inward',
        component: InwardView,
        meta: { requiresAuth: true, title: 'Stock Inward' },
    },
    {
        path: '/inwards',
        redirect: '/inward',
    },
    {
        path: '/outward',
        name: 'outward',
        component: OutwardView,
        meta: { requiresAuth: true, title: 'Stock Outward' },
    },
    {
        path: '/outwards',
        redirect: '/outward',
    },
    {
        path: '/purchase-payments',
        name: 'purchase-payments',
        component: PurchasePaymentsView,
        meta: { requiresAuth: true, title: 'Purchase Payments' },
    },
    {
        path: '/payments',
        redirect: '/purchase-payments',
    },
    {
        path: '/outward-payments',
        name: 'outward-payments',
        component: OutwardPaymentsView,
        meta: { requiresAuth: true, title: 'Customer Payments' },
    },
    {
        path: '/customer-payments',
        redirect: '/outward-payments',
    },
    {
        path: '/production',
        name: 'production',
        component: ProductionView,
        meta: { requiresAuth: true, title: 'Production Entry' },
    },
    {
        path: '/productions',
        redirect: '/production',
    },
    {
        path: '/stock-report',
        name: 'stock-report',
        component: StockReportView,
        meta: { requiresAuth: true, title: 'Stock Report' },
    },
    {
        path: '/reports/stock',
        redirect: '/stock-report',
    },
    {
        path: '/reports/dispatch-payment',
        name: 'dispatch-payment-report',
        component: DispatchPaymentReportView,
        meta: { requiresAuth: true, title: 'Dispatch & Customer Billing Register' },
    },
    {
        path: '/dispatch-payment-report',
        redirect: '/reports/dispatch-payment',
    },
    {
        path: '/dispatch-report',
        redirect: '/reports/dispatch-payment',
    },
    {
        path: '/reports/sales',
        redirect: '/reports/dispatch-payment',
    },
    {
        path: '/',
        redirect: '/dashboard',
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/dashboard',
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

// Navigation Guard
router.beforeEach((to, _from, next) => {
    const authStore = useAuthStore();
    const isAuthenticated = authStore.isAuthenticated;

    if (to.meta.requiresAuth && !isAuthenticated) {
        next({ name: 'login' });
    } else if (to.meta.guestOnly && isAuthenticated) {
        next({ name: 'dashboard' });
    } else {
        next();
    }
});

// Set document title dynamically: [Section Name] | [App Name]
router.afterEach((to) => {
    const sectionTitle = (to.meta.title as string) || '';
    setDocumentTitle(sectionTitle);
});

export default router;
