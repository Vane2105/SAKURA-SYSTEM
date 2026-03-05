<template>
  <div>
    <h2>Dashboard</h2>
    <el-row :gutter="20">
      <el-col :span="6">
        <el-card class="dashboard-card" shadow="hover">
          <div class="stat-header">Eventos Activos</div>
          <div class="stat-value">1</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="dashboard-card" shadow="hover">
          <div class="stat-header">Stands Reservados</div>
          <div class="stat-value">0</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="dashboard-card" shadow="hover">
          <div class="stat-header">Total Ingresos</div>
          <div class="stat-value">$0.00</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="dashboard-card" shadow="hover">
          <div class="stat-header">Emprendedores</div>
          <div class="stat-value">0</div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
</script>

<style scoped>
.stat-header {
  font-size: 14px;
  color: #7f8c8d;
}
.stat-value {
  font-size: 28px;
  font-weight: bold;
  color: var(--sakura-purple);
  margin-top: 10px;
}
</style>
