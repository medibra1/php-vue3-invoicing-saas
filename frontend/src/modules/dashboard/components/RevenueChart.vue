<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Chart, registerables } from 'chart.js'

Chart.register(...registerables)

const props = defineProps<{
  series: Array<{ month: string; revenue: number }>
}>()

const canvas = ref<HTMLCanvasElement | null>(null)
let chart: Chart | null = null

function render(): void {
  if (!canvas.value) {
    return
  }

  const labels = props.series.map((point) => point.month)
  const data = props.series.map((point) => point.revenue)

  if (chart) {
    chart.data.labels = labels
    chart.data.datasets[0]!.data = data
    chart.update()

    return
  }

  chart = new Chart(canvas.value, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'Revenue',
          data,
          backgroundColor: '#185FA5',
          borderRadius: 4,
        },
      ],
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } },
    },
  })
}

onMounted(render)
watch(() => props.series, render)

onBeforeUnmount(() => {
  chart?.destroy()
})
</script>

<template>
  <canvas ref="canvas" role="img" aria-label="Revenue by month" />
</template>
