import AppLayout from '@/layouts/AppLayout.vue'
import BudgetView from '@/views/BudgetView.vue'
import CaterersView from '@/views/CaterersView.vue'
import DashboardView from '@/views/DashboardView.vue'
import FloristsView from '@/views/FloristsView.vue'
import GuestsView from '@/views/GuestsView.vue'
import LoginView from '@/views/LoginView.vue'
import SeatingView from '@/views/SeatingView.vue'
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
        },
        {
          path: 'guests',
          name: 'guests',
          component: GuestsView,
        },
        {
          path: 'budget',
          name: 'budget',
          component: BudgetView,
        },
        {
          path: 'venues',
          name: 'venues',
          component: VenuesView,
        },
        {
          path: 'caterers',
          name: 'caterers',
          component: CaterersView,
        },
        {
          path: 'florists',
          name: 'florists',
          component: FloristsView,
        },
        {
          path: 'tasks',
          name: 'tasks',
          component: TasksView,
        },
        {
          path: 'seating',
          name: 'seating',
          component: SeatingView,
        },
        {
          path: 'timeline',
          name: 'timeline',
          component: TimelineView,
        },
      ]
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: '/'
    }
  ],
})

export default router
