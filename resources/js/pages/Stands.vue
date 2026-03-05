<template>
  <div class="stands-page">
    <div class="header">
      <h2>🌸 Mapa de Stands</h2>
      <div class="header-actions">
        <el-select v-model="filterEvento" placeholder="Seleccionar Evento" @change="fetchStands" style="width: 250px;">
          <el-option v-for="e in eventos" :key="e.id_eventos" :label="e.nombre" :value="e.id_eventos" />
        </el-select>
        <el-button v-if="filterEvento" type="primary" @click="fetchStands" icon="Refresh">Actualizar</el-button>
      </div>
    </div>

    <!-- Leyenda -->
    <div class="legend" v-if="filterEvento">
      <div class="legend-item"><span class="dot available"></span> Disponible</div>
      <div class="legend-item"><span class="dot reserved"></span> Reservado</div>
      <div class="legend-item"><span class="dot occupied"></span> Ocupado</div>
      <div class="legend-item"><span class="dot maintenance"></span> Mantenimiento</div>
    </div>

    <div v-loading="loading" class="grid-container" v-if="filterEvento">
      <div 
        v-for="s in filteredStands" 
        :key="s.id_stands" 
        class="stand-card" 
        :class="s.status"
        @click="openDialog(s)"
      >
        <div class="stand-name">{{ s.name }}</div>
        <div class="stand-info">
          <div v-if="getOccupant(s)" class="occupant">
            <el-icon><User /></el-icon> {{ getOccupant(s) }}
          </div>
          <div v-else class="status-text">{{ s.status.toUpperCase() }}</div>
        </div>
        <div class="price-tag">${{ Number(s.precio).toFixed(2) }}</div>
      </div>
    </div>

    <div v-else class="empty-state">
      <el-empty description="Por favor seleccione un evento para ver el mapa de stands" />
    </div>

    <!-- Dialogo Edición de Stand -->
    <el-dialog title="Detalles del Stand" v-model="dialogVisible" width="400px">
      <el-form :model="form" ref="formRef" :rules="rules" label-position="top">
        <el-form-item label="Nombre / Código" prop="name">
          <el-input v-model="form.name" />
        </el-form-item>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="Precio ($)">
              <el-input-number v-model="form.precio" :min="0" :precision="2" :step="10" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="Estado">
              <el-select v-model="form.status" style="width: 100%">
                <el-option label="Disponible" value="disponible" />
                <el-option label="Reservado" value="reservado" />
                <el-option label="Ocupado" value="ocupado" />
                <el-option label="Mantenimiento" value="mantenimiento" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        
        <div v-if="getOccupant(form)" class="occupant-detail">
          <el-divider>Ocupante</el-divider>
          <div style="text-align: center; color: var(--sakura-purple);">
            <el-icon size="24"><Shop /></el-icon>
            <div style="font-weight: bold; font-size: 16px;">{{ getOccupant(form) }}</div>
          </div>
        </div>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">Cerrar</el-button>
        <el-button type="primary" @click="saveStand" :loading="saving" style="background-color: var(--sakura-purple); border: none;">
          Guardar Cambios
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { ElMessage } from 'element-plus'
import { User, Shop, Refresh } from '@element-plus/icons-vue'

const stands = ref([])
const eventos = ref([])
const loading = ref(false)
const saving = ref(false)
const dialogVisible = ref(false)
const filterEvento = ref('')

const form = ref({
  id_stands: null,
  name: '',
  precio: 0,
  status: 'disponible',
  detalles: []
})

const formRef = ref(null)

const rules = {
  name: [{ required: true, message: 'El nombre/código es obligatorio', trigger: 'blur' }],
  precio: [{ required: true, message: 'El precio es obligatorio', trigger: 'blur' }],
  status: [{ required: true, message: 'El estado es obligatorio', trigger: 'change' }]
}

const filteredStands = computed(() => stands.value)

const getOccupant = (stand) => {
  if (!stand.detalles || stand.detalles.length === 0) return null
  // Buscar el detalle más reciente o activo
  const activeDetalle = stand.detalles.find(d => d.reservacion && d.reservacion.status !== 'cancelada')
  if (activeDetalle) {
    const res = activeDetalle.reservacion
    const name1 = res.usuario?.nombre_tienda || res.usuario?.nombre || ''
    const name2 = res.usuario2?.nombre_tienda || res.usuario2?.nombre || ''
    return name2 ? `${name1} + ${name2}` : name1
  }
  return null
}

const fetchEventos = async () => {
  try {
    const res = await axios.get('/api/eventos')
    eventos.value = res.data
    if (eventos.value.length > 0 && !filterEvento.value) {
      filterEvento.value = eventos.value[0].id_eventos
      fetchStands()
    }
  } catch (error) {}
}

const fetchStands = async () => {
  if (!filterEvento.value) return
  loading.value = true
  try {
    const res = await axios.get(`/api/stands?evento_id=${filterEvento.value}`)
    // Ordenar por nombre para mantener el grid estable
    stands.value = res.data.sort((a, b) => {
      const numA = parseInt(a.name.replace(/\D/g, ''))
      const numB = parseInt(b.name.replace(/\D/g, ''))
      return numA - numB
    })
  } catch (error) {
    ElMessage.error('Error al cargar stands')
  } finally {
    loading.value = false
  }
}

const openDialog = (row) => {
  form.value = { ...row }
  dialogVisible.value = true
}

const saveStand = async () => {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (valid) {
      saving.value = true
      try {
        await axios.put(`/api/stands/${form.value.id_stands}`, form.value)
        ElMessage.success('Stand actualizado')
        dialogVisible.value = false
        fetchStands()
      } catch (error) {
        if (error.response && error.response.status === 422) {
          const errors = error.response.data.errors
          const firstError = Object.values(errors)[0][0]
          ElMessage.error(firstError)
        } else {
          ElMessage.error('Error al guardar: ' + (error.response?.data?.message || 'Error desconocido'))
        }
      } finally {
        saving.value = false
      }
    }
  })
}

onMounted(() => {
  fetchEventos()
})
</script>

<style scoped>
.stands-page {
  padding: 10px;
}
.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}
.header h2 {
  margin: 0;
  color: var(--sakura-dark);
}
.header-actions {
  display: flex;
  gap: 15px;
}

.legend {
  display: flex;
  gap: 20px;
  margin-bottom: 25px;
  background: white;
  padding: 12px 20px;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.legend-item {
  display: flex;
  align-items: center;
  font-size: 13px;
  color: #666;
  gap: 8px;
}
.dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}
.dot.available { background-color: #67c23a; }
.dot.reserved { background-color: #e6a23c; }
.dot.occupied { background-color: #f56c6c; }
.dot.maintenance { background-color: #909399; }

.grid-container {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 20px;
}

.stand-card {
  height: 110px;
  background: white;
  border-radius: 12px;
  padding: 15px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
  border: 2px solid #f0f0f0;
  box-shadow: 0 4px 12px rgba(0,0,0,0.03);
}

.stand-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 24px rgba(255, 183, 197, 0.2);
  border-color: var(--sakura-pink);
}

.stand-name {
  font-weight: 800;
  font-size: 16px;
  color: var(--sakura-dark);
}

.stand-info {
  font-size: 12px;
  color: #909399;
  flex-grow: 1;
  display: flex;
  align-items: center;
  margin-top: 5px;
}

.status-text {
  font-weight: bold;
  letter-spacing: 0.5px;
}

.occupant {
  color: var(--sakura-purple);
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 4px;
}

.price-tag {
  font-size: 14px;
  font-weight: bold;
  text-align: right;
  color: #333;
}

/* Colores por Estado */
.stand-card.disponible { border-top: 5px solid #67c23a; }
.stand-card.reserved { border-top: 5px solid #e6a23c; }
.stand-card.ocupado { border-top: 5px solid #f56c6c; }
.stand-card.maintenance { border-top: 5px solid #909399; background: #fafafa; }

.empty-state {
  margin-top: 50px;
}

.occupant-detail {
  margin-top: 20px;
  padding: 15px;
  background: #fdf6ec;
  border-radius: 8px;
}
</style>
