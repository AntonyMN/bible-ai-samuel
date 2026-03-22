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
  topPosts: Object,
});

const chartData = computed(() => ({
  labels: props.graphData.map(d => d.date),
  datasets: [
    {
      label: 'Page Views',
      backgroundColor: '#93c5fd',
      borderColor: '#3b82f6',
      data: props.graphData.map(d => d.page_views),
      fill: true,
      backgroundColor: 'rgba(59, 130, 246, 0.1)',
      tension: 0.4
    },
    {
      label: 'Queries',
      backgroundColor: '#c4b5fd',
      borderColor: '#8b5cf6',
      data: props.graphData.map(d => d.queries),
      fill: false,
      tension: 0.4
    }
  ]
}));

const userChartData = computed(() => ({
  labels: props.graphData.map(d => d.date),
  datasets: [
    {
      label: 'Active Users',
      backgroundColor: '#10b981',
      borderColor: '#10b981',
      data: props.graphData.map(d => d.active_users),
    }
  ]
}));

const chartOptions = {
// ... existing options ...
};

const countryList = computed(() => {
  return Object.entries(props.countries || {})
    .sort((a, b) => b[1] - a[1])
    .slice(0, 5);
});

const topPostsList = computed(() => {
  return Object.entries(props.topPosts || {})
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
          <p class="text-slate-500 text-sm font-medium group-hover:text-blue-400 transition-colors">Today's Page Views</p>
          <div class="flex items-baseline gap-2">
            <p class="text-4xl font-bold mt-2">{{ stats.today_page_views }}</p>
            <span class="text-xs text-slate-500">avg: {{ stats.avg_page_views }}</span>
          </div>
        </div>
        <div class="bg-slate-900/50 border border-white/5 rounded-2xl p-6 hover:border-purple-500/30 transition-colors group">
          <p class="text-slate-500 text-sm font-medium group-hover:text-purple-400 transition-colors">Today's Queries</p>
          <div class="flex items-baseline gap-2">
            <p class="text-4xl font-bold mt-2">{{ stats.today_queries }}</p>
            <span class="text-xs text-slate-500">avg: {{ stats.avg_queries }}</span>
          </div>
        </div>
        <div class="bg-slate-900/50 border border-white/5 rounded-2xl p-6 hover:border-green-500/30 transition-colors group">
          <p class="text-slate-500 text-sm font-medium group-hover:text-green-400 transition-colors">Today's Active Users</p>
          <div class="flex items-baseline gap-2">
            <p class="text-4xl font-bold mt-2">{{ stats.today_active }}</p>
            <span class="text-xs text-slate-500">avg: {{ stats.avg_active }}</span>
          </div>
        </div>
        <!-- Blog Management Card -->
        <Link :href="route('admin.blog.index')" class="bg-purple-900/20 border border-purple-500/20 rounded-2xl p-6 hover:border-purple-500/50 transition-all group flex flex-col justify-center">
          <div class="flex items-center justify-between">
            <p class="text-purple-400 text-sm font-bold uppercase tracking-widest">Blog Management</p>
            <i class="fas fa-newspaper text-purple-400 group-hover:scale-110 transition-transform"></i>
          </div>
          <p class="text-slate-400 text-xs mt-2 group-hover:text-slate-200 transition-colors">Manage AI posts & view counts.</p>
        </Link>
      </div>

      <!-- Charts Section -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Usage Chart -->
        <div class="lg:col-span-2 bg-slate-900/50 border border-white/5 rounded-2xl p-6">
          <h2 class="text-lg font-bold flex items-center gap-2 mb-6 text-blue-400">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
            </svg>
            Traffic & engagement (Last 30 Days)
          </h2>
          <div class="h-[400px]">
            <Line :data="chartData" :options="chartOptions" />
          </div>
        </div>

        <!-- Secondary Stats -->
        <div class="space-y-8">
            <!-- Top Blog Posts -->
            <div class="bg-slate-900/50 border border-white/5 rounded-2xl p-6">
                <h2 class="text-lg font-bold flex items-center gap-2 mb-4 text-purple-400">Popular Content</h2>
                <div v-if="topPostsList.length > 0" class="space-y-3">
                    <div v-for="[slug, count] in topPostsList" :key="slug" class="p-3 bg-white/5 rounded-xl border border-white/5 hover:bg-white/10 transition-colors">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium truncate max-w-[150px]">{{ slug }}</span>
                            <span class="px-2 py-1 bg-purple-500/20 text-purple-300 rounded-lg text-xs font-bold">{{ count }} views</span>
                        </div>
                    </div>
                </div>
                <div v-else class="text-slate-500 text-center py-8 bg-white/5 rounded-xl border border-dashed border-white/10">
                    No post views tracked
                </div>
            </div>

            <!-- Country Breakdown -->
            <div class="bg-slate-900/50 border border-white/5 rounded-2xl p-6">
                <h2 class="text-lg font-bold flex items-center gap-2 mb-4 text-orange-400">Top Countries (Today)</h2>
                <div v-if="countryList.length > 0" class="space-y-3">
                    <div v-for="[code, count] in countryList" :key="code" class="flex items-center justify-between p-3 bg-white/5 rounded-xl border border-white/5">
                        <span class="font-medium text-sm">{{ code }}</span>
                        <span class="px-3 py-1 bg-slate-800 rounded-lg text-xs font-bold">{{ count }}</span>
                    </div>
                </div>
                <div v-else class="text-slate-500 text-center py-8 bg-white/5 rounded-xl border border-dashed border-white/10">
                    No country data today
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
