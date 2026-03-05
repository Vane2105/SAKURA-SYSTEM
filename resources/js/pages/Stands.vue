<template>
  <div class="stands-page">
    <div class="header">
      <h2>🌸 Mapa de Stands</h2>
      <div class="header-actions">
        <el-tag
          v-if="filterEvento && totalDeuda > 0"
          type="warning"
          effect="dark"
          size="large"
          style="margin-right: 15px; font-size: 14px;"
        >
          Deuda Total: ${{ totalDeuda.toFixed(2) }}
        </el-tag>

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
        :class="[s.status, { 'has-debt': ['ocupado', 'reservado'].includes(s.status) && Number(s.saldo_pendiente) > 0 }]"
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
                <el-option 
                  label="Disponible" 
                  value="disponible" 
                  :disabled="getOccupant(form) && Number(form.saldo_pendiente) >= 0 && hasPagosAprobados(getReservacionActiva(form))" 
                />
                <el-option label="Reservado" value="reservado" />
                <el-option label="Ocupado" value="ocupado" />
                <el-option label="Mantenimiento" value="mantenimiento" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        
        <div v-if="getOccupantData(form)" class="occupant-detail">
          <el-divider>Ocupante Actual (CRM)</el-divider>
          <div style="text-align: center;">
            <div style="font-weight: bold; font-size: 18px; color: var(--sakura-purple);">
              {{ getOccupant(form) }}
              <el-icon v-if="getOccupantData(form).estado_registro === 'Documentos OK'" color="#67c23a" style="margin-left: 5px;"><SuccessFilled /></el-icon>
            </div>
            
            <div style="margin: 10px 0; display: flex; justify-content: center; gap: 15px; font-size: 14px; color: #606266;">
              <span><b>Rubro:</b> {{ getOccupantData(form).rubro || 'N/A' }}</span>
              <span v-if="getOccupantData(form).instagram">
                <b>IG:</b> <a :href="`https://instagram.com/${getOccupantData(form).instagram.replace('@','')}`" target="_blank" style="color: #E1306C; text-decoration: none; font-weight: bold;">
                  @{{ getOccupantData(form).instagram.replace('@','') }}
                </a>
              </span>
            </div>

            <el-tag :type="getStatusTypeCRM(getOccupantData(form).estado_registro)" effect="plain" size="small" style="margin-bottom: 15px;">
              {{ getOccupantData(form).estado_registro }}
            </el-tag>

            <div style="margin-top: 5px; font-size: 14px; color: #f56c6c;" v-if="Number(form.saldo_pendiente) > 0">
              <strong>Deuda Pendiente:</strong> ${{ Number(form.saldo_pendiente).toFixed(2) }}
            </div>
            <div style="margin-top: 5px; font-size: 14px; color: #67c23a;" v-else>
              <strong>Solvente</strong> (Total Pagado)
            </div>

            <p v-if="getOccupantData(form).notas_admin" style="font-size: 12px; color: #909399; font-style: italic; margin-top: 10px; background: white; padding: 8px; border-radius: 4px; border: 1px solid #eee;">
               "{{ getOccupantData(form).notas_admin }}"
            </p>
          </div>
          
          <div style="margin-top: 20px; text-align: center;">
             <el-button type="danger" plain icon="Remove" @click="confirmWithdrawal" style="width: 100%;">
               Registrar Retiro del Evento
             </el-button>
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

    <!-- Diálogo de Retiro de Emprendedor -->
    <el-dialog title="Confirmar Retiro del Evento" v-model="retireDialogVisible" width="400px">
      <div v-if="getReservacionActiva(form)" style="margin-bottom: 20px;">
        <p>Está a punto de retirar al emprendedor <strong>{{ getOccupant(form) }}</strong> del stand <strong>{{ form.name }}</strong>.</p>
        <p>Esta acción cancelará su reservación y liberará este stand en el mapa inmediatamente.</p>
        
        <div v-if="hasPagosAprobados(getReservacionActiva(form))" style="background: #fdf6ec; padding: 15px; border-radius: 8px; margin-top: 15px;">
           <div style="color: #e6a23c; font-weight: bold; margin-bottom: 10px;">
             <el-icon><Warning /></el-icon> Acción Financiera Requerida
           </div>
           <p style="font-size: 13px;">Hay pagos aprobados en esta reservación. ¿Qué desea hacer con el dinero ya abonado?</p>
           
           <el-radio-group v-model="retireModel.accion_financiera" style="margin-top: 10px; display: flex; flex-direction: column; gap: 10px;">
             <el-radio value="credito">Mantener como saldo a favor (Crédito)</el-radio>
             <el-radio value="reembolso">Registrar Devolución (Reembolso)</el-radio>
           </el-radio-group>
        </div>

        <el-input
          v-model="retireModel.motivo"
          type="textarea"
          :rows="2"
          placeholder="Motivo del retiro u observación extra (Opcional)..."
          style="margin-top: 20px;"
        />
      </div>

      <template #footer>
        <el-button @click="retireDialogVisible = false" :disabled="retiring">Cancelar</el-button>
        <el-button type="danger" @click="executeRetire" :loading="retiring">
          Confirmar Retiro
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { ElMessage, ElMessageBox } from 'element-plus'
import { User, Shop, Refresh, Warning, Remove, SuccessFilled } from '@element-plus/icons-vue'

const stands = ref([])
const eventos = ref([])
const loading = ref(false)
const saving = ref(false)
const dialogVisible = ref(false)
const retireDialogVisible = ref(false)
const filterEvento = ref('')
const retiring = ref(false)

const retireModel = ref({
  accion_financiera: 'credito',
  motivo: ''
})

const totalDeuda = computed(() => {
  return stands.value.reduce((acc, stand) => {
    if (['ocupado', 'reservado'].includes(stand.status)) {
      return acc + Number(stand.saldo_pendiente || 0);
    }
    return acc;
  }, 0);
})

const form = ref({
  id_stands: null,
  name: '',
  precio: 0,
  saldo_pendiente: 0,
  status: 'disponible',
  detalles: []
})

const formRef = ref(null)

const rules = {
  name: [{ required: true, message: 'El nombre/código es obligatorio', trigger: 'blur' }],
  precio: [{ required: true, message: 'El precio es obligatorio', trigger: 'blur' }],
  status: [
    { required: true, message: 'El estado es obligatorio', trigger: 'change' },
    {
      validator: (rule, value, callback) => {
        if (value === 'disponible' && getOccupant(form.value) && Number(form.value.saldo_pendiente) >= 0 && hasPagosAprobados(getReservacionActiva(form.value))) {
          callback(new Error('No puede cambiar manualmente a Disponible un stand con pagos. Use "Registrar Retiro".'))
        } else {
          callback()
        }
      },
      trigger: 'change'
    }
  ]
}

const filteredStands = computed(() => stands.value)

const getReservacionActiva = (stand) => {
  if (!stand.detalles || stand.detalles.length === 0) return null
  const activeDetalle = stand.detalles.find(d => d.reservacion && d.reservacion.status !== 'cancelada')
  return activeDetalle ? activeDetalle.reservacion : null
}

const getOccupant = (stand) => {
  const data = getOccupantData(stand)
  return data ? (data.nombre_tienda || data.nombre) : null
}

const getOccupantData = (stand) => {
  const reservacion = getReservacionActiva(stand)
  return reservacion ? reservacion.usuario : null
}

const getStatusTypeCRM = (status) => {
  if (status === 'Documentos OK') return 'success'
  if (status === 'Bloqueado') return 'danger'
  return 'warning'
}

const hasPagosAprobados = (reservacion) => {
  if (!reservacion || !reservacion.pagos) return false
  return reservacion.pagos.some(p => p.status === 'aprobado')
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

const confirmWithdrawal = () => {
  const reservacion = getReservacionActiva(form.value)
  if (!reservacion) return
  
  retireModel.value = {
    accion_financiera: 'credito',
    motivo: ''
  }
  retireDialogVisible.value = true
}

const executeRetire = async () => {
  const reservacion = getReservacionActiva(form.value)
  if (!reservacion) return

  retiring.value = true
  try {
    await axios.post(`/api/reservaciones/${reservacion.id_reservacion}/retiro`, retireModel.value)
    ElMessage.success('Retiro procesado correctamente. El stand ha sido liberado.')
    
    // Limpieza de estado visual
    retireDialogVisible.value = false
    dialogVisible.value = false
    retireModel.value.motivo = ''
    retireModel.value.accion_financiera = 'credito'
    
    fetchStands()
  } catch (error) {
    ElMessage.error('Error al procesar retiro: ' + (error.response?.data?.message || 'Error desconocido'))
  } finally {
    retiring.value = false
  }
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
.stand-card.reservado { border-top: 5px solid #e6a23c; } /* Corregido "reserved" por "reservado" para hacer match con BD */
.stand-card.ocupado { border-top: 5px solid #f56c6c; }
.stand-card.mantenimiento { border-top: 5px solid #909399; background: #fafafa; } /* Corregido también maintenance a mantenimiento */

.stand-card.has-debt {
  border-top-color: #ff9800; /* Naranja para advertir deuda */
  box-shadow: 0 0 10px rgba(255, 152, 0, 0.4);
}
.stand-card.has-debt::after {
  content: "DEUDA";
  position: absolute;
  top: 5px;
  right: 15px;
  font-size: 10px;
  color: #ff9800;
  font-weight: bold;
}

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
