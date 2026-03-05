<template>
  <div>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <h2>Reporte de Confirmados</h2>
      <el-button type="success" plain @click="imprimir()">
        <el-icon><Printer /></el-icon> Imprimir Lista
      </el-button>
    </div>

    <el-card shadow="never" class="print-area">
      <el-table :data="confirmados" v-loading="loading" style="width: 100%">
        <el-table-column label="Emprendedor">
          <template #default="scope">
            <b>{{ scope.row.usuario?.nombre }} {{ scope.row.usuario?.apellido }}</b><br>
            <small>{{ scope.row.usuario?.ci }}</small>
          </template>
        </el-table-column>
        <el-table-column label="Contacto">
          <template #default="scope">
            {{ scope.row.usuario?.telefonos?.[0]?.numeros_telefonos || 'N/A' }}
          </template>
        </el-table-column>
        <el-table-column label="Stands Asignados">
          <template #default="scope">
            <span v-for="d in scope.row.detalles" :key="d.id_detalle_stand">
              {{ d.stand?.name }} 
            </span>
          </template>
        </el-table-column>
        <el-table-column label="Pagado">
          <template #default="scope">
            ${{ calcularPagado(scope.row).toFixed(2) }}
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { Printer } from '@element-plus/icons-vue'

const confirmados = ref([])
const loading = ref(false)

const calcularPagado = (res) => {
  if (!res.pagos) return 0
  return res.pagos.reduce((total, p) => total + parseFloat(p.cantidad), 0)
}

const fetchReporte = async () => {
  loading.value = true
  try {
    const res = await axios.get('/api/reportes/confirmados')
    confirmados.value = res.data
  } catch (error) {} finally {
    loading.value = false
  }
}

const imprimir = () => {
  window.print()
}

onMounted(() => {
  fetchReporte()
})
</script>

<style>
@media print {
  body * {
    visibility: hidden;
  }
  .print-area, .print-area * {
    visibility: visible;
  }
  .print-area {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
  }
  .el-aside, .el-header, button {
    display: none !important;
  }
}
</style>
