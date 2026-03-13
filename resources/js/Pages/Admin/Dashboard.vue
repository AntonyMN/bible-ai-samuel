<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  Title,
  Tooltip,
  Legend,
} from 'chart.js';
import { Line, Bar } from 'vue-chartjs';

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  Title,
  Tooltip,
  Legend
);

const props = defineProps({
  stats: Object,
  graphData: Array,
  countries: Object,
});

const chartData = computed(() => ({
  labels: props.graphData.map(d => d.date),
  datasets: [
    {
      label: 'Active Users',
      backgroundColor: '#93c5fd',
      borderColor: '#3b82f6',
      data: props.graphData.map(d => d.active_users),
      fill: false,
      tension: 0.4
    },
    {
      label: 'Auth Requests',
      backgroundColor: '#c4b5fd',
      borderColor: '#8b5cf6',
      data: props.graphData.map(d => d.auth_calls),
      fill: false,
      tension: 0.4
    }
  ]
}));

const requestChartData = computed(() => ({
  labels: props.graphData.map(d => d.date),
  datasets: [
    {
      label: 'Unauth Requests',
      backgroundColor: '#fdba74',
      borderColor: '#f97316',
      data: props.graphData.map(d => d.unauth_calls),
    }
  ]
}));

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom',
    },
  },
  scales: {
    y: {
      beginAtZero: true,
      grid: {
        color: 'rgba(255, 255, 255, 0.05)',
      },
    }
  }
};

const countryList = computed(() => {
  return Object.entries(props.countries || {})
    .sort((a, b) => b[1] - a[1])
    .slice(0, 5);
});
</script>

<template>
  <Head title="Admin Dashboard | Samuel.ai" />

  <div class="min-h-screen bg-slate-950 text-slate-100 p-6 md:p-8">
    <div class="max-w-7xl mx-auto space-y-8">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 class="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-purple-400">
            Admin Dashboard
          </h1>
          <p class="text-slate-400 mt-1">Monitoring usage and performance metrics.</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="px-3 py-1 bg-blue-500/10 border border-blue-500/20 text-blue-400 rounded-full text-sm font-medium">
                Live Status
            </span>
            <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
        </div>
      </div>

      <!-- Stats Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-slate-900/50 border border-white/5 rounded-2xl p-6 hover:border-blue-500/30 transition-colors group">
          <p class="text-slate-500 text-sm font-medium group-hover:text-blue-400 transition-colors">Total Registered Users</p>
          <p class="text-4xl font-bold mt-2">{{ stats.total_users }}</p>
        </div>
        <div class="bg-slate-900/50 border border-white/5 rounded-2xl p-6 hover:border-purple-500/30 transition-colors group">
          <p class="text-slate-500 text-sm font-medium group-hover:text-purple-400 transition-colors">Today's Auth Requests</p>
          <div class="flex items-baseline gap-2">
            <p class="text-4xl font-bold mt-2">{{ stats.today_auth }}</p>
            <span class="text-xs text-slate-500">avg: {{ stats.avg_auth }}</span>
          </div>
        </div>
        <div class="bg-slate-900/50 border border-white/5 rounded-2xl p-6 hover:border-orange-500/30 transition-colors group">
          <p class="text-slate-500 text-sm font-medium group-hover:text-orange-400 transition-colors">Today's Unauth Requests</p>
          <div class="flex items-baseline gap-2">
            <p class="text-4xl font-bold mt-2">{{ stats.today_unauth }}</p>
            <span class="text-xs text-slate-500">avg: {{ stats.avg_unauth }}</span>
          </div>
        </div>
        <div class="bg-slate-900/50 border border-white/5 rounded-2xl p-6 hover:border-green-500/30 transition-colors group">
          <p class="text-slate-500 text-sm font-medium group-hover:text-green-400 transition-colors">Today's Active Users</p>
          <div class="flex items-baseline gap-2">
            <p class="text-4xl font-bold mt-2">{{ stats.today_active }}</p>
            <span class="text-xs text-slate-500">avg: {{ stats.avg_active }}</span>
          </div>
        </div>
      </div>

      <!-- Charts Section -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Usage Chart -->
        <div class="lg:col-span-2 bg-slate-900/50 border border-white/5 rounded-2xl p-6">
          <h2 class="text-lg font-bold flex items-center gap-2 mb-6">
            <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
            </svg>
            Usage Trends (Last 30 Days)
          </h2>
          <div class="h-[400px]">
            <Line :data="chartData" :options="chartOptions" />
          </div>
        </div>

        <!-- Secondary Stats -->
        <div class="space-y-8">
            <!-- Unauth Chart -->
            <div class="bg-slate-900/50 border border-white/5 rounded-2xl p-6">
                <h2 class="text-lg font-bold flex items-center gap-2 mb-6">Unauthenticated Traffic</h2>
                <div class="h-[150px]">
                    <Bar :data="requestChartData" :options="chartOptions" />
                </div>
            </div>

            <!-- Country Breakdown -->
            <div class="bg-slate-900/50 border border-white/5 rounded-2xl p-6">
                <h2 class="text-lg font-bold flex items-center gap-2 mb-4">Top Countries (Today)</h2>
                <div v-if="countryList.length > 0" class="space-y-4">
                    <div v-for="[code, count] in countryList" :key="code" class="flex items-center justify-between p-3 bg-white/5 rounded-xl border border-white/5">
                        <div class="flex items-center gap-3">
                            <span class="text-lg">{{ code }}</span>
                            <span class="font-medium">Country: {{ code }}</span>
                        </div>
                        <span class="px-3 py-1 bg-slate-800 rounded-lg text-sm font-bold">{{ count }}</span>
                    </div>
                </div>
                <div v-else class="text-slate-500 text-center py-8 bg-white/5 rounded-xl border border-dashed border-white/10">
                    No data captured yet today
                </div>
            </div>
        </div>
      </div>

    </div>
  </div>
</template>

<style>
/* Smooth transitions for chart elements */
.chart-container {
  transition: all 0.3s ease;
}
</style>
