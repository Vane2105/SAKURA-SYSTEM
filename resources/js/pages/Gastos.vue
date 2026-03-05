<template>
  <div class="gastos-container">
    <div class="header" v-if="!embedded">
      <el-button @click="$emit('back')" icon="ArrowLeft">Volver a Eventos</el-button>
      <h2>Gastos: {{ evento.nombre }}</h2>
      <el-button type="primary" @click="openDialog" icon="Plus">Registrar Gasto</el-button>
    </div>
    <div v-else style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
      <h3 style="margin: 0; color: var(--sakura-purple);">Egresos del Evento</h3>
      <el-button type="primary" @click="openDialog" icon="Plus">Registrar Gasto</el-button>
    </div>

    <!-- Tabla de Gastos -->
    <el-table :data="gastos" v-loading="loading" style="width: 100%; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
      <el-table-column prop="fecha" label="Fecha" width="120">
        <template #default="{ row }">
          {{ formatDate(row.fecha) }}
        </template>
      </el-table-column>
      <el-table-column prop="concepto" label="Concepto" />
      <el-table-column prop="categoria" label="Categoría" width="220">
        <template #default="{ row }">
          <el-tag :type="row.categoria === 'Pago a Proveedor de Mobiliario' ? 'warning' : ''" size="small" effect="dark">
            {{ row.categoria === 'Pago a Proveedor de Mobiliario' ? '💰 Proveedor Mob.' : (row.categoria || 'Otros') }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="Monto USD" width="150" align="right">
        <template #default="{ row }">
          <b>${{ parseFloat(row.monto_usd).toFixed(2) }}</b>
        </template>
      </el-table-column>
      <el-table-column label="Monto Bs" width="200" align="right">
        <template #default="{ row }">
          <span style="color: var(--sakura-purple); font-weight: bold;">
            Bs {{ parseFloat(row.monto_bs).toFixed(2) }}
          </span>
          <br>
          <small style="color: #999; font-size: 10px;">(Tasa: {{ row.tasa_bcv }} Bs/$)</small>
        </template>
      </el-table-column>
      <el-table-column label="Acciones" width="100" align="center">
        <template #default="{ row }">
          <el-button type="danger" icon="Delete" circle @click="deleteGasto(row.id_gastos)" />
        </template>
      </el-table-column>
    </el-table>

    <!-- Resumen TOTAL -->
    <div class="summary-card" v-if="gastos.length > 0">
      <div class="summary-item total">
        <span class="label">GASTO TOTAL:</span>
        <div class="values">
          <div class="usd">${{ totalUsd.toFixed(2) }}</div>
          <div class="bs">Bs {{ totalBs.toFixed(2) }}</div>
        </div>
      </div>
    </div>

    <!-- Dialogo Nuevo Gasto -->
    <el-dialog title="Registrar Nuevo Gasto" v-model="dialogVisible" width="450px">
      <el-form :model="form" ref="formRef" :rules="rules" label-position="top">
        <el-form-item label="Concepto" prop="concepto">
          <el-input v-model="form.concepto" placeholder="Ej: Alquiler del Local" />
        </el-form-item>
        
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="Categoría" prop="categoria">
              <el-select v-model="form.categoria" placeholder="Seleccionar..." style="width: 100%;">
                <el-option-group label="⚠️ Proveedores">
                  <el-option label="Pago a Proveedor de Mobiliario" value="Pago a Proveedor de Mobiliario">
                    <span style="color: #E6A23C; font-weight: bold;">💰 Pago a Proveedor de Mobiliario</span>
                  </el-option>
                </el-option-group>
                <el-option-group label="Gastos Operativos">
                  <el-option label="Alquiler" value="Alquiler" />
                  <el-option label="Publicidad" value="Publicidad" />
                  <el-option label="Personal" value="Personal" />
                  <el-option label="Logística" value="Logística" />
                  <el-option label="Servicios" value="Servicios" />
                  <el-option label="Otros" value="Otros" />
                </el-option-group>
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="Fecha" prop="fecha">
              <el-date-picker 
                v-model="form.fecha" 
                type="date" 
                style="width: 100%" 
                value-format="YYYY-MM-DD"
                @change="handleDateChange"
              />
            </el-form-item>
          </el-col>
        </el-row>
        
        <div v-if="loadingRate" style="margin-bottom: 10px; color: #9d4edd; font-size: 12px;">
          <el-icon class="is-loading"><Loading /></el-icon> Cargando tasa del histórico...
        </div>

        <el-divider>Montos (Conversión automática)</el-divider>
        <div class="conversion-box">
          <div style="font-size: 12px; color: #888; margin-bottom: 10px;">
            Tasa Aplicada: <b>{{ form.tasa_bcv }} Bs/$</b> <small v-if="form.tasa_fuente">({{ form.tasa_fuente }})</small>
          </div>
          
          <el-row :gutter="20">
            <el-col :span="12">
              <el-form-item label="Monto en USD" prop="monto_usd">
                <el-input-number v-model="form.monto_usd" :precision="2" :step="10" @input="updateFromUsd" style="width: 100%" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="Monto en Bs">
                <el-input-number v-model="form.monto_bs" :precision="2" :step="100" @input="updateFromBs" style="width: 100%" />
              </el-form-item>
            </el-col>
          </el-row>
        </div>

        <el-form-item label="Descripción (Opcional)">
          <el-input v-model="form.descripcion" type="textarea" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">Cancelar</el-button>
        <el-button type="primary" @click="saveGasto" :loading="saving">Guardar Gasto</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, ArrowLeft, Delete, Loading } from '@element-plus/icons-vue'

const props = defineProps({
  evento: {
    type: Object,
    required: true
  },
  embedded: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['back'])

const formRef = ref(null)

const gastos = ref([])
const loading = ref(false)
const saving = ref(false)
const dialogVisible = ref(false)
const loadingRate = ref(false)
const tasaBcvGlobal = ref(0)

const form = ref({
  concepto: '',
  categoria: '',
  monto_usd: 0,
  monto_bs: 0,
  tasa_bcv: 0,
  tasa_fuente: '',
  fecha: new Date().toISOString().split('T')[0],
  descripcion: ''
})

const rules = {
  concepto: [{ required: true, message: 'El concepto es obligatorio', trigger: 'blur' }],
  categoria: [{ required: true, message: 'La categoría es obligatoria', trigger: 'change' }],
  fecha: [{ required: true, message: 'La fecha es obligatoria', trigger: 'change' }],
  monto_usd: [{ required: true, message: 'El monto en USD es obligatorio', trigger: 'blur' }]
}

const totalUsd = computed(() => gastos.value.reduce((acc, g) => acc + parseFloat(g.monto_usd), 0))
const totalBs = computed(() => gastos.value.reduce((acc, g) => acc + parseFloat(g.monto_bs), 0))

const fetchTasa = async () => {
  try {
    const res = await axios.get('/api/tasa-bcv')
    tasaBcvGlobal.value = parseFloat(res.data.tasa)
  } catch (error) {
    ElMessage.error('No se pudo obtener la tasa BCV')
  }
}

const fetchGastos = async () => {
  loading.value = true
  try {
    const res = await axios.get(`/api/gastos?id_eventos=${props.evento.id_eventos}`)
    gastos.value = res.data
  } finally {
    loading.value = false
  }
}

const openDialog = () => {
  form.value = {
    concepto: '',
    categoria: 'Logística',
    monto_usd: 0,
    monto_bs: 0,
    tasa_bcv: tasaBcvGlobal.value,
    tasa_fuente: 'Actual (BCV)',
    fecha: new Date().toISOString().split('T')[0],
    descripcion: ''
  }
  dialogVisible.value = true
}

const handleDateChange = async (val) => {
  if (!val) return
  const hoy = new Date().toISOString().split('T')[0]
  if (val === hoy) {
    form.value.tasa_bcv = tasaBcvGlobal.value
    form.value.tasa_fuente = 'Actual (BCV)'
    updateFromUsd(form.value.monto_usd)
    return
  }

  loadingRate.value = true
  try {
    const res = await axios.get(`/api/tasa-bcv/historico?fecha=${val}`)
    form.value.tasa_bcv = parseFloat(res.data.tasa)
    form.value.tasa_fuente = res.data.fuente
    updateFromUsd(form.value.monto_usd)
  } catch (e) {
    ElMessage.warning('No se encontró tasa para esa fecha. Se usará la tasa actual.')
    form.value.tasa_bcv = tasaBcvGlobal.value
    form.value.tasa_fuente = 'Fallback (Actual)'
    updateFromUsd(form.value.monto_usd)
  } finally {
    loadingRate.value = false
  }
}

const updateFromUsd = (val) => {
  if (form.value.tasa_bcv > 0) {
    form.value.monto_bs = parseFloat((val * form.value.tasa_bcv).toFixed(2))
  }
}

const updateFromBs = (val) => {
  if (form.value.tasa_bcv > 0) {
    form.value.monto_usd = parseFloat((val / form.value.tasa_bcv).toFixed(2))
  }
}

const saveGasto = async () => {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (valid) {
      saving.value = true
      try {
        await axios.post('/api/gastos', {
          ...form.value,
          id_eventos: props.evento.id_eventos,
          tasa_bcv: form.value.tasa_bcv,
          fecha: form.value.fecha
        })
        ElMessage.success('Gasto registrado con éxito')
        dialogVisible.value = false
        fetchGastos()
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

const deleteGasto = (id) => {
  ElMessageBox.confirm('¿Estás seguro de eliminar este gasto?', 'Advertencia', {
    type: 'warning'
  }).then(async () => {
    await axios.delete(`/api/gastos/${id}`)
    ElMessage.success('Gasto eliminado')
    fetchGastos()
  })
}

const formatDate = (dateStr) => {
  const d = new Date(dateStr)
  return d.toLocaleDateString()
}

onMounted(() => {
  fetchTasa()
  fetchGastos()
})
</script>

<style scoped>
.gastos-container {
  padding: 20px;
}
.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
}
.header h2 {
  margin: 0;
  color: var(--sakura-purple);
}
.conversion-box {
  background: #f9f9f9;
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 20px;
  border: 1px solid #eee;
}
.summary-card {
  margin-top: 30px;
  background: linear-gradient(135deg, #7b2cbf, #9d4edd);
  color: white;
  padding: 20px;
  border-radius: 12px;
  display: flex;
  justify-content: flex-end;
}
.summary-item.total {
  display: flex;
  align-items: center;
  gap: 20px;
}
.summary-item .label {
  font-size: 18px;
  font-weight: bold;
}
.summary-item .values {
  text-align: right;
}
.summary-item .usd {
  font-size: 28px;
  font-weight: 900;
}
.summary-item .bs {
  font-size: 16px;
  opacity: 0.9;
}
</style>
