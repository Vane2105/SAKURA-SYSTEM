<template>
  <div class="dashboard-container">
    <div class="dashboard-header">
      <h2>🌸 Balance General</h2>
      <el-tag type="info" size="large">Filtro: Todo el tiempo</el-tag>
    </div>

    <!-- Metricas Principales -->
    <el-row :gutter="20" v-loading="loading">
      <el-col :span="6">
        <el-card class="stat-card income" shadow="hover">
          <div class="stat-content">
            <el-icon class="icon"><Ticket /></el-icon>
            <div class="text">
              <span class="label">Ingresos Stands</span>
              <span class="value">${{ metrics.total_ingresos.toFixed(2) }}</span>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card furniture" shadow="hover">
          <div class="stat-content">
            <el-icon class="icon"><Box /></el-icon>
            <div class="text">
              <span class="label">Fondo Mobiliario</span>
              <span class="value">${{ metrics.total_mobiliario?.toFixed(2) || '0.00' }}</span>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card expense" shadow="hover">
          <div class="stat-content">
            <el-icon class="icon"><Money /></el-icon>
            <div class="text">
              <span class="label">Total Gastos</span>
              <span class="value">${{ metrics.total_gastos.toFixed(2) }}</span>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card balance" shadow="hover" :class="{ 'negative': metrics.saldo < 0 }">
          <div class="stat-content">
            <el-icon class="icon"><Wallet /></el-icon>
            <div class="text">
              <span class="label">Utilidad Neta</span>
              <span class="value">${{ metrics.saldo.toFixed(2) }}</span>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- ROI por Evento -->
    <el-card style="margin-top: 30px;" shadow="never">
      <template #header>
        <div style="font-weight: bold; font-size: 16px;">Rentabilidad por Evento</div>
      </template>
      <el-table :data="metrics.eventos_roi" border stripe style="width: 100%">
        <el-table-column prop="nombre" label="Evento" />
        <el-table-column label="Ingresos Stands" width="160" align="right">
          <template #default="{ row }">
            <span style="color: #67C23A; font-weight: bold;">+${{ row.ingresos_stands?.toFixed(2) || '0.00' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="Fondo Mob." width="130" align="right">
          <template #default="{ row }">
            <span style="color: #E6A23C;">${{ row.ingresos_mobiliario?.toFixed(2) || '0.00' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="Gastos" width="130" align="right">
          <template #default="{ row }">
            <span style="color: #F56C6C;">-${{ row.gastos.toFixed(2) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="Neto (Stands)" width="150" align="right">
          <template #default="{ row }">
            <b :style="{ color: row.neto >= 0 ? '#67C23A' : '#F56C6C' }">
              {{ row.neto >= 0 ? '+' : '' }}${{ row.neto.toFixed(2) }}
            </b>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { Ticket, Money, Wallet } from '@element-plus/icons-vue'

const loading = ref(false)
const metrics = ref({
  total_ingresos: 0,
  total_mobiliario: 0,
  total_gastos: 0,
  saldo: 0,
  eventos_roi: []
})

const fetchMetrics = async () => {
  loading.value = true
  try {
    const res = await axios.get('/api/finanzas/global')
    metrics.value = res.data
  } finally {
    loading.value = false
  }
}

onMounted(fetchMetrics)
</script>

<style scoped>
.dashboard-container {
  padding: 10px;
}
.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
}
.stat-card {
  border-radius: 12px;
  border: none;
  color: white;
}
.stat-card.income {
  background: linear-gradient(135deg, #11998e, #38ef7d);
}
.stat-card.furniture {
  background: linear-gradient(135deg, #f2994a, #f2c94c);
}
.stat-card.expense {
  background: linear-gradient(135deg, #ff416c, #ff4b2b);
}
.stat-card.balance {
  background: linear-gradient(135deg, #4facfe, #00f2fe);
}
.stat-card.negative {
  background: linear-gradient(135deg, #232526, #414345);
}
.stat-content {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 10px 5px;
}
.icon {
  font-size: 40px;
  opacity: 0.8;
}
.text {
  display: flex;
  flex-direction: column;
}
.label {
  font-size: 14px;
  opacity: 0.9;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.value {
  font-size: 28px;
  font-weight: 900;
}
</style>
