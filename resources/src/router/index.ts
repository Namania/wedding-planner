import AppLayout from '@/layouts/AppLayout.vue'
import { useAuthStore } from '@/stores/auth'
import AnimationsView from '@/views/AnimationsView.vue'
import BudgetView from '@/views/BudgetView.vue'
import CaterersView from '@/views/CaterersView.vue'
import DashboardView from '@/views/DashboardView.vue'
import FloristsView from '@/views/FloristsView.vue'
import GuestsView from '@/views/GuestsView.vue'
import LoginView from '@/views/LoginView.vue'
import OutfitsView from '@/views/OutfitsView.vue'
import SeatingView from '@/views/SeatingView.vue'
import SettingsView from '@/views/SettingsView.vue'
import TasksView from '@/views/TasksView.vue'
import TimelineView from '@/views/TimelineView.vue'
import VenuesView from '@/views/VenuesView.vue'
import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView,
    },
    {
      path: '/',
      component: AppLayout,
      children: [
        {
          path: '',
          name: 'dashboard',
          component: DashboardView,
          meta: { requiresAuth: true },
        },
        {
          path: 'guests',
          name: 'guests',
          component: GuestsView,
          meta: { requiresAuth: true },
        },
        {
          path: 'budget',
          name: 'budget',
          component: BudgetView,
          meta: { requiresAuth: true },
        },
        {
          path: 'venues',
          name: 'venues',
          component: VenuesView,
          meta: { requiresAuth: true },
        },
        {
          path: 'caterers',
          name: 'caterers',
          component: CaterersView,
          meta: { requiresAuth: true },
        },
        {
          path: 'florists',
          name: 'florists',
          component: FloristsView,
          meta: { requiresAuth: true },
        },
        {
          path: 'animations',
          name: 'animations',
          component: AnimationsView,
          meta: { requiresAuth: true },
        },
        {
          path: 'outfits',
          name: 'outfits',
          component: OutfitsView,
          meta: { requiresAuth: true },
        },
        {
          path: 'tasks',
          name: 'tasks',
          component: TasksView,
          meta: { requiresAuth: true },
        },
        {
          path: 'seating',
          name: 'seating',
          component: SeatingView,
          meta: { requiresAuth: true },
        },
        {
          path: 'timeline',
          name: 'timeline',
          component: TimelineView,
          meta: { requiresAuth: true },
        },
        {
          path: 'settings',
          name: 'settings',
          component: SettingsView,
          meta: { requiresAuth: true },
        },
      ]
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: '/'
    }
  ],
})

router.beforeEach(async (to) => {
  const authStore = useAuthStore()

  if (to.meta.requiresAuth && authStore.user === null) {
    await authStore.checkAuth()
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return '/login'
  } else if (to.path === '/login' && authStore.isAuthenticated) {
    return '/'
  }
});

export default router
