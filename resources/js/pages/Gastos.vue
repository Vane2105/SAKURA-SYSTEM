<template>
  <div class="gastos-container">
    <div class="header">
      <el-button @click="$emit('back')" icon="ArrowLeft">Volver a Eventos</el-button>
      <h2>Gastos: {{ evento.nombre }}</h2>
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
      <el-table-column prop="categoria" label="Categoría" width="150">
        <template #default="{ row }">
          <el-tag size="small">{{ row.categoria || 'Otros' }}</el-tag>
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
      <el-table-column label="Acciones" width="120" align="center">
        <template #default="{ row }">
          <el-button type="primary" icon="Edit" circle @click="openDialog(row)" />
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

    <!-- Dialogo Gasto -->
    <el-dialog :title="isEdit ? 'Editar Gasto' : 'Registrar Nuevo Gasto'" v-model="dialogVisible" width="450px">
      <el-form :model="form" ref="formRef" :rules="rules" label-position="top">
        <el-form-item label="Concepto" prop="concepto" :error="formErrors.concepto">
          <el-input v-model="form.concepto" @blur="validateField('concepto')" placeholder="Ej: Alquiler del Local" />
        </el-form-item>
        
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="Categoría">
              <el-select v-model="form.categoria" placeholder="Seleccionar...">
                <el-option label="Alquiler" value="Alquiler" />
                <el-option label="Publicidad" value="Publicidad" />
                <el-option label="Personal" value="Personal" />
                <el-option label="Logística" value="Logística" />
                <el-option label="Servicios" value="Servicios" />
                <el-option label="Otros" value="Otros" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="Fecha" prop="fecha" :error="formErrors.fecha">
              <el-date-picker 
                v-model="form.fecha" 
                type="date" 
                style="width: 100%" 
                value-format="YYYY-MM-DD"
                @change="handleDateChange"
                @blur="validateField('fecha')"
              />
            </el-form-item>
          </el-col>
        </el-row>
        
        <div v-if="loadingRate" style="margin-bottom: 10px; color: #9d4edd; font-size: 12px;">
          <el-icon class="is-loading"><Loading /></el-icon> Cargando tasa del histórico...
        </div>

        <el-divider>Montos (Conversión automática)</el-divider>
        <div class="conversion-box" style="background: #fdfdfd; padding: 15px; border-radius: 8px; border: 1px solid #efefef; margin-bottom: 20px;">
          <el-row :gutter="20" align="middle" style="margin-bottom: 15px;">
            <el-col :span="12">
              <span style="font-size: 13px; color: #606266; font-weight: bold;">Tasa de Cambio (Bs/$)</span>
              <div style="font-size: 11px; color: #909399;">{{ form.tasa_fuente || 'Manual' }}</div>
            </el-col>
            <el-col :span="12" style="text-align: right;">
              <el-input-number v-model="form.tasa_bcv" :precision="4" :step="0.1" @change="updateFromUsd(form.monto_usd)" style="width: 100%" />
            </el-col>
          </el-row>
          
          <el-row :gutter="20">
            <el-col :span="12">
              <el-form-item label="Monto en USD" prop="monto_usd" :error="formErrors.monto_usd">
                <el-input-number v-model="form.monto_usd" :precision="2" :step="10" @input="updateFromUsd" @blur="validateField('monto_usd')" style="width: 100%" />
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
  }
})

const emit = defineEmits(['back'])

const gastos = ref([])
const loading = ref(false)
const saving = ref(false)
const dialogVisible = ref(false)
const isEdit = ref(false)
const loadingRate = ref(false)
const tasaBcvGlobal = ref(0)
const formRef = ref(null)

const formErrors = ref({
  concepto: '',
  fecha: '',
  monto_usd: ''
})

const validateField = (field) => {
  if (field === 'concepto') {
    formErrors.value.concepto = form.value.concepto ? '' : 'Falta el concepto de gasto'
  }
  if (field === 'fecha') {
    formErrors.value.fecha = form.value.fecha ? '' : 'Seleccione la fecha del registro'
  }
  if (field === 'monto_usd') {
    formErrors.value.monto_usd = form.value.monto_usd > 0 ? '' : 'El monto debe ser mayor a 0'
  }
}

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
  concepto: [{ required: true, message: 'El concepto es obligatorio', trigger: ['blur', 'change'] }],
  fecha: [{ required: true, message: 'La fecha es obligatoria', trigger: 'change' }],
  monto_usd: [{ required: true, message: 'El monto en USD es obligatorio', trigger: ['blur', 'change'] }]
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

const openDialog = (gasto = null) => {
  if (gasto && gasto.id_gastos) {
    isEdit.value = true
    form.value = { ...gasto }
    // Asegurarse de que fecha sea string YYYY-MM-DD
    if (form.value.fecha instanceof Date) {
      form.value.fecha = form.value.fecha.toISOString().split('T')[0]
    } else if (typeof form.value.fecha === 'string' && form.value.fecha.includes('T')) {
      form.value.fecha = form.value.fecha.split('T')[0]
    }
  } else {
    isEdit.value = false
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
        const payload = {
          ...form.value,
          id_eventos: props.evento.id_eventos
        }
        
        if (isEdit.value) {
          await axios.put(`/api/gastos/${form.value.id_gastos}`, payload)
          ElMessage.success('Gasto actualizado correctamente')
        } else {
          await axios.post('/api/gastos', payload)
          ElMessage.success('Gasto registrado correctamente')
        }
        
        dialogVisible.value = false
        fetchGastos()
      } catch (error) {
        ElMessage.error('Error al guardar el gasto')
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
