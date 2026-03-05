<template>
  <div>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <h2>🛋️ Alquiler de Mobiliario</h2>
      <el-button type="primary" @click="openDialog()" style="background-color: var(--sakura-purple); border: none;">
        Registrar Alquiler
      </el-button>
    </div>

    <!-- Filtro por reservacion -->
    <div style="margin-bottom: 20px; display: flex; gap: 12px; align-items: center;">
      <el-select v-model="filterReservacion" clearable placeholder="Filtrar por Reservación" style="width: 340px;" @change="fetchData">
        <el-option
          v-for="r in reservaciones"
          :key="r.id_reservacion"
          :label="`#${r.id_reservacion} - ${r.usuario?.nombre_tienda || r.usuario?.nombre}${r.usuario2 ? ' + ' + (r.usuario2?.nombre_tienda || r.usuario2?.nombre) : ''}`"
          :value="r.id_reservacion"
        />
      </el-select>
      <el-button @click="filterReservacion = null; fetchData()">Ver Todos</el-button>
    </div>

    <!-- Tabla -->
    <el-table :data="alquileres" v-loading="loading" style="width: 100%; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05);" border stripe>
      <el-table-column label="Reservación" min-width="200">
        <template #default="{ row }">
          <b style="color: var(--sakura-purple);">#{{ row.reservacion?.id_reservacion }} - {{ row.reservacion?.usuario?.nombre_tienda || row.reservacion?.usuario?.nombre }}</b>
          <span v-if="row.reservacion?.usuario2" style="color: #999; font-size: 11px;"> + {{ row.reservacion?.usuario2?.nombre_tienda || row.reservacion?.usuario2?.nombre }}</span>
        </template>
      </el-table-column>
      <el-table-column label="Descripción" prop="descripcion" min-width="160">
        <template #default="{ row }">
          <span v-if="row.descripcion">{{ row.descripcion }}</span>
          <span v-else style="color: #ccc;">—</span>
        </template>
      </el-table-column>
      <el-table-column label="Fecha" width="110">
        <template #default="{ row }">
          {{ row.fecha ? formatDate(row.fecha) : '—' }}
        </template>
      </el-table-column>
      <el-table-column label="Monto USD" width="120" align="right">
        <template #default="{ row }">
          <b>${{ Number(row.precio_usd).toFixed(2) }}</b>
        </template>
      </el-table-column>
      <el-table-column label="Monto Bs" width="180" align="right">
        <template #default="{ row }">
          <span style="color: var(--sakura-purple); font-weight: bold;">
            Bs {{ Number(row.monto_bs || 0).toFixed(2) }}
          </span>
          <br>
          <small style="color: #999; font-size: 10px;">(Tasa: {{ row.tasa_bcv || '—' }} Bs/$)</small>
        </template>
      </el-table-column>
      <el-table-column label="Pagado" width="110" align="right">
        <template #default="{ row }">
          <span style="color: #67c23a; font-weight: bold;">${{ calcularPagado(row).toFixed(2) }}</span>
        </template>
      </el-table-column>
      <el-table-column label="Pendiente" width="110" align="right">
        <template #default="{ row }">
          <span :style="{ fontWeight: 'bold', color: (row.precio_usd - calcularPagado(row)) > 0 ? '#E6A23C' : '#67c23a' }">
            ${{ Math.max(0, row.precio_usd - calcularPagado(row)).toFixed(2) }}
          </span>
        </template>
      </el-table-column>
      <el-table-column label="Estado" width="110">
        <template #default="{ row }">
          <el-tag :type="row.status === 'pagado' ? 'success' : row.status === 'cancelado' ? 'danger' : 'warning'">
            {{ row.status.toUpperCase() }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="Acciones" width="200" align="center">
        <template #default="{ row }">
          <el-button size="small" icon="Edit" type="primary" plain @click="openEditDialog(row)" />
          <el-button size="small" type="success" @click="openPagoDialog(row)" v-if="row.status !== 'pagado' && row.status !== 'cancelado'">
            Pagar
          </el-button>
          <el-button size="small" type="primary" plain @click="openPagoDialog(row)" v-else>
            Detalle
          </el-button>
          <el-button size="small" type="danger" icon="Delete" circle @click="cancelarAlquiler(row)" v-if="row.status === 'pendiente'" />
        </template>
      </el-table-column>
    </el-table>

    <!-- Resumen totales -->
    <div class="summary-card" v-if="alquileres.length > 0">
      <div class="summary-item total">
        <span class="label">TOTAL MOBILIARIO:</span>
        <div class="values">
          <div class="usd">${{ totalUsd.toFixed(2) }}</div>
          <div class="bs">Bs {{ totalBs.toFixed(2) }}</div>
        </div>
      </div>
    </div>

    <!-- DIÁLOGO: Crear/Editar Alquiler -->
    <el-dialog :title="editMode ? 'Editar Alquiler' : 'Registrar Alquiler de Mobiliario'" v-model="dialogVisible" width="520px">
      <el-form :model="form" ref="formRef" :rules="rules" label-position="top">
        <el-form-item label="Reservación" prop="reservacion_id">
          <el-select v-model="form.reservacion_id" filterable placeholder="Buscar reservación..." style="width: 100%" :disabled="editMode">
            <el-option
              v-for="r in reservaciones"
              :key="r.id_reservacion"
              :label="`#${r.id_reservacion} - ${r.usuario?.nombre_tienda || r.usuario?.nombre}`"
              :value="r.id_reservacion"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="Descripción del Mobiliario">
          <el-input v-model="form.descripcion" placeholder="Ej: 2 mesas, 4 sillas, stand doble..." />
        </el-form-item>

        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="Fecha del Alquiler" prop="fecha">
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

        <div v-if="loadingRate" style="margin-bottom: 8px; color: #9d4edd; font-size: 12px;">
          <el-icon class="is-loading"><Loading /></el-icon> Cargando tasa del histórico...
        </div>

        <el-divider>Monto (Conversión automática)</el-divider>
        <div class="conversion-box">
          <el-row :gutter="16" align="middle" style="margin-bottom: 10px;">
            <el-col :span="12">
              <span style="font-size: 12px; color: #888;">Tasa Aplicada (Bs/$):</span>
              <span v-if="form.tasa_fuente" style="font-size: 10px; color: #aaa; margin-left: 6px;">{{ form.tasa_fuente }}</span>
            </el-col>
            <el-col :span="12">
              <el-input-number v-model="form.tasa_bcv" :precision="4" :step="0.1" @change="updateFromUsd(form.precio_usd)" style="width: 100%" />
            </el-col>
          </el-row>
          <el-row :gutter="20">
            <el-col :span="12">
              <el-form-item label="Monto en USD" prop="precio_usd">
                <el-input-number v-model="form.precio_usd" :precision="2" :step="5" @input="updateFromUsd" style="width: 100%" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="Monto en Bs">
                <el-input-number v-model="form.monto_bs" :precision="2" :step="100" @input="updateFromBs" style="width: 100%" />
              </el-form-item>
            </el-col>
          </el-row>
          <div v-if="form.precio_usd > 0" style="background: linear-gradient(135deg, #f5f0ff, #fff0f5); padding: 10px 14px; border-radius: 8px; border-left: 4px solid var(--sakura-purple); font-size: 13px;">
            Total: <b style="color: var(--sakura-purple); font-size: 16px;">Bs {{ (form.monto_bs || 0).toFixed(2) }}</b>
            &nbsp;=&nbsp; <b>${{ (form.precio_usd || 0).toFixed(2) }}</b>
          </div>
        </div>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">Cancelar</el-button>
        <el-button type="primary" @click="saveAlquiler" :loading="saving" style="background-color: var(--sakura-purple); border: none;">
          {{ editMode ? 'Actualizar' : 'Guardar' }}
        </el-button>
      </template>
    </el-dialog>

    <!-- DIÁLOGO: Registrar Pago -->
    <el-dialog
      :title="currentAlquiler?.status !== 'pagado' ? 'Registrar Pago de Alquiler' : 'Detalle de Alquiler'"
      v-model="pagoDialogVisible"
      width="540px"
    >
      <div v-if="currentAlquiler">
        <!-- Resumen de deuda -->
        <div style="margin-bottom: 20px; border: 1px solid #ebeef5; border-radius: 8px; overflow: hidden;">
          <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <tr style="background: #fafafa; font-weight: bold;">
              <td style="padding: 8px; border-bottom: 1px solid #ebeef5;">Concepto</td>
              <td style="padding: 8px; border-bottom: 1px solid #ebeef5; text-align: right;">USD</td>
              <td style="padding: 8px; border-bottom: 1px solid #ebeef5; text-align: right;">Bs</td>
            </tr>
            <tr>
              <td style="padding: 6px 8px;">Precio Total Mobiliario</td>
              <td style="padding: 6px 8px; text-align: right;">${{ Number(currentAlquiler.precio_usd).toFixed(2) }}</td>
              <td style="padding: 6px 8px; text-align: right; color: #909399;">Bs {{ Number(currentAlquiler.monto_bs || 0).toFixed(2) }}</td>
            </tr>
            <tr style="color: #67C23A;">
              <td style="padding: 6px 8px;">Ya Pagado</td>
              <td style="padding: 6px 8px; text-align: right;">-${{ calcularPagado(currentAlquiler).toFixed(2) }}</td>
              <td style="padding: 6px 8px; text-align: right;">-Bs {{ calcularPagadoBs(currentAlquiler).toFixed(2) }}</td>
            </tr>
            <tr v-if="(currentAlquiler.precio_usd - calcularPagado(currentAlquiler)) > 0"
                style="background: #fdf6ec; font-weight: bold; border-top: 2px solid #e6a23c;">
              <td style="padding: 8px;">SALDO PENDIENTE</td>
              <td style="padding: 8px; text-align: right; color: #E6A23C;">
                ${{ Math.max(0, currentAlquiler.precio_usd - calcularPagado(currentAlquiler)).toFixed(2) }}
              </td>
              <td style="padding: 8px; text-align: right; color: #E6A23C;">
                Bs {{ (Math.max(0, currentAlquiler.precio_usd - calcularPagado(currentAlquiler)) * (pagoForm.tasa_bcv || tasaBcv)).toFixed(2) }}
              </td>
            </tr>
            <tr v-else style="background: #f0f9eb; font-weight: bold; border-top: 2px solid #67c23a;">
              <td style="padding: 8px; color: #67c23a;" colspan="3">✅ PAGO COMPLETADO</td>
            </tr>
          </table>
        </div>

        <!-- Formulario de pago -->
        <el-form :model="pagoForm" ref="pagoFormRef" label-position="top" v-if="currentAlquiler.status !== 'pagado' && currentAlquiler.status !== 'cancelado'">
          <el-row :gutter="20">
            <el-col :span="12">
              <el-form-item label="Fecha del Pago" required>
                <el-date-picker
                  v-model="pagoForm.fecha"
                  type="date"
                  style="width: 100%"
                  value-format="YYYY-MM-DD"
                  @change="handlePagoDateChange"
                />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="Tasa Aplicada (Bs/$)">
                <el-input-number v-model="pagoForm.tasa_bcv" :precision="4" :step="0.1" @change="updatePagoFromUsd(pagoForm.cantidad)" style="width: 100%" />
                <div style="font-size: 10px; color: #909399; margin-top: 4px;">{{ pagoForm.tasa_fuente || 'Manual' }}</div>
              </el-form-item>
            </el-col>
          </el-row>

          <div v-if="loadingRate" style="color: #9d4edd; font-size: 12px; margin-bottom: 10px;">
            <el-icon class="is-loading"><Loading /></el-icon> Obteniendo tasa histórica...
          </div>

          <div class="conversion-box" style="margin-top: 0;">
            <el-row :gutter="20">
              <el-col :span="12">
                <el-form-item label="Monto en USD" required>
                  <el-input-number v-model="pagoForm.cantidad" :min="0" :precision="2" :step="5" @input="updatePagoFromUsd" style="width: 100%" />
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="Monto en Bs">
                  <el-input-number v-model="pagoForm.monto_bs" :min="0" :precision="2" :step="100" @input="updatePagoFromBs" style="width: 100%" />
                </el-form-item>
              </el-col>
            </el-row>
            <div v-if="pagoForm.cantidad > 0" style="background: linear-gradient(135deg, #f5f0ff, #fff0f5); padding: 10px 14px; border-radius: 8px; border-left: 4px solid var(--sakura-purple); font-size: 13px;">
              Pago de: <b style="color: var(--sakura-purple); font-size: 18px;">Bs {{ (pagoForm.cantidad * (pagoForm.tasa_bcv || tasaBcv)).toFixed(2) }}</b>
              &nbsp;=&nbsp; <b>${{ pagoForm.cantidad.toFixed(2) }}</b>
            </div>
          </div>

          <el-form-item label="Número de Referencia" style="margin-top: 10px;">
            <el-input v-model="pagoForm.numero_referencia" placeholder="Nro de transferencia/pago móvil" />
          </el-form-item>
        </el-form>

        <!-- Historial de pagos -->
        <div v-if="currentAlquiler?.pagos?.length > 0" style="margin-top: 20px; border-top: 2px dashed #eee; padding-top: 15px;">
          <div style="font-size: 12px; font-weight: bold; color: #909399; margin-bottom: 10px; text-transform: uppercase;">
            Historial de Pagos:
          </div>
          <div v-for="p in currentAlquiler.pagos" :key="p.id_pago_mobiliario" class="pago-item">
            <div>
              <span class="monto-usd">${{ parseFloat(p.cantidad).toFixed(2) }}</span>
              <span class="ref" v-if="p.numero_referencia"> — Ref: {{ p.numero_referencia }}</span>
              <div style="font-size: 10px; color: #bbb; margin-top: 2px;">{{ p.fecha }}</div>
            </div>
            <div class="pago-bs" v-if="p.tasa_bcv">
              <b>Bs {{ (p.cantidad * p.tasa_bcv).toFixed(2) }}</b><br>
              <small>{{ p.tasa_bcv }} Bs/$</small>
            </div>
          </div>
        </div>
      </div>
      <template #footer>
        <el-button @click="pagoDialogVisible = false">Cerrar</el-button>
        <el-button
          type="success"
          :loading="saving"
          @click="savePago"
          v-if="currentAlquiler?.status !== 'pagado' && currentAlquiler?.status !== 'cancelado'"
        >
          Registrar Pago
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Loading } from '@element-plus/icons-vue'

const alquileres = ref([])
const reservaciones = ref([])
const loading = ref(false)
const saving = ref(false)
const dialogVisible = ref(false)
const pagoDialogVisible = ref(false)
const loadingRate = ref(false)
const tasaBcv = ref(75.00)
const currentAlquiler = ref(null)
const filterReservacion = ref(null)
const editMode = ref(false)
const editId = ref(null)
const formRef = ref(null)
const pagoFormRef = ref(null)

const emptyForm = () => ({
  reservacion_id: null,
  descripcion: '',
  precio_usd: 0,
  monto_bs: 0,
  tasa_bcv: tasaBcv.value,
  tasa_fuente: 'Actual (BCV)',
  fecha: new Date().toISOString().split('T')[0],
})

const form = ref(emptyForm())

const pagoForm = ref({
  cantidad: 0,
  monto_bs: 0,
  tasa_bcv: 75.00,
  tasa_fuente: '',
  fecha: new Date().toISOString().split('T')[0],
  numero_referencia: '',
})

const rules = {
  reservacion_id: [{ required: true, message: 'La reservación es obligatoria', trigger: 'change' }],
  precio_usd: [{ required: true, message: 'El precio es obligatorio', trigger: 'blur' }],
  fecha: [{ required: true, message: 'La fecha es obligatoria', trigger: 'change' }],
}

// ─── Computed totals ─────────────────────────────────────────────────────────
const totalUsd = computed(() => alquileres.value.reduce((s, a) => s + parseFloat(a.precio_usd || 0), 0))
const totalBs  = computed(() => alquileres.value.reduce((s, a) => s + parseFloat(a.monto_bs  || 0), 0))

const calcularPagado = (a) => {
  if (!a?.pagos) return 0
  return a.pagos.reduce((s, p) => s + parseFloat(p.cantidad), 0)
}

const calcularPagadoBs = (a) => {
  if (!a?.pagos) return 0
  return a.pagos.reduce((s, p) => s + parseFloat(p.cantidad) * parseFloat(p.tasa_bcv || 0), 0)
}

// ─── Data fetching ────────────────────────────────────────────────────────────
const fetchTasaBcv = async () => {
  try {
    const res = await axios.get('/api/tasa-bcv')
    if (res.data.tasa > 0) {
      tasaBcv.value = parseFloat(res.data.tasa)
    }
  } catch (e) {}
}

const fetchData = async () => {
  loading.value = true
  try {
    const params = filterReservacion.value ? { reservacion_id: filterReservacion.value } : {}
    const [resAlq, resRes] = await Promise.all([
      axios.get('/api/alquileres-mobiliario', { params }),
      axios.get('/api/reservaciones'),
    ])
    alquileres.value = resAlq.data
    reservaciones.value = resRes.data
  } catch (e) {
    ElMessage.error('Error al cargar datos')
  } finally {
    loading.value = false
  }
}

// ─── Form helpers ─────────────────────────────────────────────────────────────
const updateFromUsd = (val) => {
  if (form.value.tasa_bcv > 0) {
    form.value.monto_bs = parseFloat(((val ?? form.value.precio_usd) * form.value.tasa_bcv).toFixed(2))
  }
}

const updateFromBs = (val) => {
  if (form.value.tasa_bcv > 0) {
    form.value.precio_usd = parseFloat(((val ?? form.value.monto_bs) / form.value.tasa_bcv).toFixed(2))
  }
}

const updatePagoFromUsd = (val) => {
  if (pagoForm.value.tasa_bcv > 0) {
    pagoForm.value.monto_bs = parseFloat(((val ?? pagoForm.value.cantidad) * pagoForm.value.tasa_bcv).toFixed(2))
  }
}

const updatePagoFromBs = (val) => {
  if (pagoForm.value.tasa_bcv > 0) {
    pagoForm.value.cantidad = parseFloat(((val ?? pagoForm.value.monto_bs) / pagoForm.value.tasa_bcv).toFixed(2))
  }
}

const fetchHistoricalRate = async (fecha, targetForm) => {
  const hoy = new Date().toISOString().split('T')[0]
  if (fecha === hoy) {
    targetForm.tasa_bcv = tasaBcv.value
    targetForm.tasa_fuente = 'Actual (BCV)'
    return
  }
  loadingRate.value = true
  try {
    const res = await axios.get(`/api/tasa-bcv/historico?fecha=${fecha}`)
    targetForm.tasa_bcv = parseFloat(res.data.tasa)
    targetForm.tasa_fuente = res.data.fuente
  } catch (e) {
    ElMessage.warning('No se encontró tasa para esa fecha, se usará la actual.')
    targetForm.tasa_bcv = tasaBcv.value
    targetForm.tasa_fuente = 'Fallback (Actual)'
  } finally {
    loadingRate.value = false
  }
}

const handleDateChange = async (val) => {
  if (!val) return
  await fetchHistoricalRate(val, form.value)
  updateFromUsd(form.value.precio_usd)
}

const handlePagoDateChange = async (val) => {
  if (!val) return
  await fetchHistoricalRate(val, pagoForm.value)
  updatePagoFromUsd(pagoForm.value.cantidad)
}

// ─── CRUD ─────────────────────────────────────────────────────────────────────
const openDialog = () => {
  editMode.value = false
  editId.value = null
  form.value = { ...emptyForm(), tasa_bcv: tasaBcv.value }
  dialogVisible.value = true
}

const openEditDialog = (alquiler) => {
  editMode.value = true
  editId.value = alquiler.id_alquiler
  form.value = {
    reservacion_id: alquiler.reservacion_id,
    descripcion:    alquiler.descripcion || '',
    precio_usd:     parseFloat(alquiler.precio_usd),
    monto_bs:       parseFloat(alquiler.monto_bs || 0),
    tasa_bcv:       parseFloat(alquiler.tasa_bcv || tasaBcv.value),
    tasa_fuente:    alquiler.tasa_fuente || '',
    fecha:          alquiler.fecha ? alquiler.fecha.split('T')[0] : new Date().toISOString().split('T')[0],
  }
  dialogVisible.value = true
}

const saveAlquiler = async () => {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    saving.value = true
    try {
      if (editMode.value) {
        await axios.put(`/api/alquileres-mobiliario/${editId.value}`, form.value)
        ElMessage.success('Alquiler actualizado')
      } else {
        await axios.post('/api/alquileres-mobiliario', form.value)
        ElMessage.success('Alquiler registrado')
      }
      dialogVisible.value = false
      fetchData()
    } catch (e) {
      ElMessage.error('Error al guardar: ' + (e.response?.data?.message || 'Desconocido'))
    } finally {
      saving.value = false
    }
  })
}

const openPagoDialog = (alquiler) => {
  currentAlquiler.value = alquiler
  pagoForm.value = {
    cantidad: 0,
    monto_bs: 0,
    tasa_bcv: parseFloat(alquiler.tasa_bcv || tasaBcv.value),
    tasa_fuente: 'Actual (BCV)',
    fecha: new Date().toISOString().split('T')[0],
    numero_referencia: '',
  }
  pagoDialogVisible.value = true
}

const savePago = async () => {
  if (!pagoForm.value.cantidad || pagoForm.value.cantidad <= 0) {
    return ElMessage.warning('El monto debe ser mayor a 0')
  }
  saving.value = true
  try {
    await axios.post(`/api/alquileres-mobiliario/${currentAlquiler.value.id_alquiler}/pagos`, {
      cantidad:          pagoForm.value.cantidad,
      tasa_bcv:          pagoForm.value.tasa_bcv,
      fecha:             pagoForm.value.fecha,
      numero_referencia: pagoForm.value.numero_referencia,
    })
    ElMessage.success('Pago registrado')
    pagoDialogVisible.value = false
    fetchData()
  } catch (e) {
    ElMessage.error('Error al registrar pago: ' + (e.response?.data?.message || 'Desconocido'))
  } finally {
    saving.value = false
  }
}

const cancelarAlquiler = async (alquiler) => {
  try {
    await ElMessageBox.confirm('¿Seguro que deseas cancelar este alquiler?', 'Confirmación')
    await axios.put(`/api/alquileres-mobiliario/${alquiler.id_alquiler}`, {
      ...alquiler,
      precio_usd: alquiler.precio_usd,
      status: 'cancelado',
    })
    ElMessage.success('Alquiler cancelado')
    fetchData()
  } catch (e) {
    if (e !== 'cancel') ElMessage.error('Error al cancelar')
  }
}

const formatDate = (dateStr) => {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  return d.toLocaleDateString('es-VE')
}

onMounted(() => {
  fetchTasaBcv()
  fetchData()
})
</script>

<style scoped>
.conversion-box {
  background: #f9f9f9;
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 16px;
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
.pago-item {
  background: #fdfdfd;
  border-left: 4px solid #909399;
  padding: 8px 12px;
  margin-bottom: 8px;
  border-radius: 4px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border: 1px solid #f0f0f0;
}
.monto-usd { font-weight: bold; color: #303133; }
.ref { color: #909399; font-size: 11px; }
.pago-bs { text-align: right; font-size: 12px; color: #606266; font-weight: 500; }
.pago-bs small { color: #999; font-weight: normal; }
</style>
