<template>
  <div>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <div style="display: flex; gap: 15px; align-items: center;">
        <h2 style="margin: 0;">Gestión de Reservaciones</h2>
        <el-select v-model="filterEvento" placeholder="Selecciona un Evento" @change="fetchReservaciones" style="width: 250px;">
          <el-option v-for="e in eventos" :key="e.id_eventos" :label="e.nombre" :value="e.id_eventos" />
        </el-select>
      </div>
      <el-button type="primary" @click="openDialog()" :disabled="!filterEvento" style="background-color: var(--sakura-purple); border: none;">
        Crear Reservación
      </el-button>
    </div>

    <div v-if="!filterEvento" style="text-align: center; padding: 50px; color: #909399; border: 1px dashed #dcdfe6; border-radius: 8px;">
      <el-icon style="font-size: 48px; margin-bottom: 10px;"><Calendar /></el-icon>
      <p>Selecciona un evento para ver o crear reservaciones.</p>
    </div>

    <el-table v-else :data="reservaciones" v-loading="loading" style="width: 100%" border stripe show-summary :summary-method="getGlobalSummaries">
      <el-table-column prop="id_reservacion" label="ID" width="60" />
      <el-table-column prop="usuario.nombre_tienda" label="Tienda">
        <template #default="scope">
          {{ scope.row.usuario?.nombre_tienda || scope.row.usuario?.nombre }}
        </template>
      </el-table-column>
      <el-table-column label="Stands">
        <template #default="scope">
          <el-tag v-for="d in scope.row.detalles" :key="d.id_detalle_stand" size="small" style="margin-right: 5px;">
            {{ d.stand?.name }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="status" label="Estado" width="120">
        <template #default="scope">
          <el-tag :type="getStatusColor(scope.row.status)">
            {{ scope.row.status.toUpperCase() }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="Mobiliario" width="100" align="center">
        <template #default="scope">
          <span v-if="calcularTotalMobiliario(scope.row) > 0">${{ calcularTotalMobiliario(scope.row).toFixed(2) }}</span>
          <span v-else style="color: #ccc;">—</span>
        </template>
      </el-table-column>
      <el-table-column label="Total Deuda" width="120" align="right">
        <template #default="scope">
          <strong>${{ (calcularTotalStands(scope.row) + calcularTotalMobiliario(scope.row)).toFixed(2) }}</strong>
        </template>
      </el-table-column>
      <el-table-column label="Total Pagado" width="120" align="right">
        <template #default="scope">
          <span style="color: #67C23A">${{ calcularPagado(scope.row).toFixed(2) }}</span>
        </template>
      </el-table-column>
      <el-table-column label="Equiv. Total Bs" width="140" align="right">
        <template #default="scope">
          <span style="color: var(--sakura-purple)">Bs {{ ((calcularTotalStands(scope.row) + calcularTotalMobiliario(scope.row)) * tasaBcv).toFixed(2) }}</span>
        </template>
      </el-table-column>
      <el-table-column label="Acciones" width="180" align="center">
        <template #default="scope">
          <el-button size="small" type="success" @click="openPagoDialog(scope.row)" v-if="scope.row.status !== 'cancelada'">
            Pago
          </el-button>
          <el-button size="small" type="danger" @click="changeStatus(scope.row, 'cancelada')" v-if="scope.row.status !== 'cancelada'">
            Cancelar
          </el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- Dialogo Crear Reservacion -->
    <el-dialog title="Nueva Reservación" v-model="dialogVisible" width="600px">
      <el-form :model="form" ref="formRef" :rules="rules" label-width="120px" label-position="top">
        <el-form-item label="Emprendedor" prop="usuarios_id">
          <el-select v-model="form.usuarios_id" filterable placeholder="Buscar emprendedor..." style="width: 100%">
            <el-option v-for="u in usuarios" :key="u.id" :label="u.nombre_tienda || u.nombre" :value="u.id" />
          </el-select>
        </el-form-item>

        <el-row :gutter="20">
          <el-col :span="24">
            <el-form-item label="Stands" prop="stands">
              <el-select v-model="form.stands" multiple placeholder="Seleccionar stands..." style="width: 100%">
                <el-option v-for="s in standsDisponibles" :key="s.id_stands" :label="`${s.evento?.nombre} - ${s.name} ($${s.precio})`" :value="s.id_stands" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>

        <el-divider>Mobiliario / Extras (Opcional)</el-divider>
        <div v-for="(item, index) in form.mobiliarios" :key="index" style="display: flex; gap: 10px; margin-bottom: 15px; align-items: start;">
          <el-input v-model="item.descripcion" placeholder="Descripción (Ej: Mesa VIP)" style="flex: 2" />
          <el-input-number v-model="item.cantidad" :min="1" placeholder="Cant." style="flex: 1" />
          <el-input-number v-model="item.precio_unitario_usd" :min="0" :precision="2" :step="1" placeholder="Precio ($)" style="flex: 1.2" />
          <el-button type="danger" icon="Delete" circle @click="removeMobiliario(index)" />
        </div>
        <el-button type="dashed" style="width: 100%; margin-bottom: 20px;" @click="addMobiliario">
          <el-icon style="margin-right: 5px;"><Plus /></el-icon> Añadir Ítem de Mobiliario/Extra
        </el-button>

        <!-- Resumen Detallado de Precios -->
        <div v-if="form.stands.length > 0 || form.mobiliarios.length > 0" style="margin-bottom: 20px; border: 1px solid #e4e7ed; border-radius: 8px; overflow: hidden;">
          <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <tr style="background: #f5f7fa;">
              <th style="padding: 8px; text-align: left; border-bottom: 1px solid #e4e7ed;">Concepto</th>
              <th style="padding: 8px; text-align: right; border-bottom: 1px solid #e4e7ed;">Cant</th>
              <th style="padding: 8px; text-align: right; border-bottom: 1px solid #e4e7ed;">USD</th>
              <th style="padding: 8px; text-align: right; border-bottom: 1px solid #e4e7ed;">Bs (BCV)</th>
            </tr>
            <tr v-if="form.stands.length > 0">
              <td style="padding: 8px; border-bottom: 1px solid #ebeef5;">Stands</td>
              <td style="padding: 8px; border-bottom: 1px solid #ebeef5; text-align: right;">{{ form.stands.length }}</td>
              <td style="padding: 8px; border-bottom: 1px solid #ebeef5; text-align: right;">${{ totalStandsUsd.toFixed(2) }}</td>
              <td style="padding: 8px; border-bottom: 1px solid #ebeef5; text-align: right;">Bs {{ (totalStandsUsd * tasaBcv).toFixed(2) }}</td>
            </tr>
            <tr v-for="(mob, i) in form.mobiliarios" :key="`mob-${i}`">
              <td style="padding: 8px; border-bottom: 1px solid #ebeef5; color: #606266; font-size: 13px;">{{ mob.descripcion || 'Ítem' }}</td>
              <td style="padding: 8px; border-bottom: 1px solid #ebeef5; text-align: right; color: #606266; font-size: 13px;">{{ mob.cantidad || 0 }}</td>
              <td style="padding: 8px; border-bottom: 1px solid #ebeef5; text-align: right; color: #606266; font-size: 13px;">${{ ((mob.cantidad || 0) * (mob.precio_unitario_usd || 0)).toFixed(2) }}</td>
              <td style="padding: 8px; border-bottom: 1px solid #ebeef5; text-align: right; color: #606266; font-size: 13px;">Bs {{ (((mob.cantidad || 0) * (mob.precio_unitario_usd || 0)) * tasaBcv).toFixed(2) }}</td>
            </tr>
            <tr style="background: #fdf6ec; font-weight: bold; font-size: 16px;">
              <td colspan="2" style="padding: 8px; color: var(--sakura-purple);">TOTAL A PAGAR</td>
              <td style="padding: 8px; text-align: right; color: var(--sakura-purple);">${{ granTotalUsd.toFixed(2) }}</td>
              <td style="padding: 8px; text-align: right; color: var(--sakura-purple);">Bs {{ (granTotalUsd * tasaBcv).toFixed(2) }}</td>
            </tr>
          </table>
        </div>

        <el-form-item label="Descripción (Opcional)">
           <el-input v-model="form.descripcion" />
        </el-form-item>

        <el-divider>Pago Inicial (Opcional)</el-divider>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="Monto Pagado ($)">
              <el-input-number v-model="form.monto_pago" :min="0" :precision="2" :step="10" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="Referencia">
              <el-input v-model="form.referencia_pago" placeholder="Nro de ref." />
            </el-form-item>
          </el-col>
        </el-row>
        <div v-if="tasaBcv > 0 && form.monto_pago > 0" style="font-size: 13px; color: #67C23A; font-weight: bold; margin-bottom: 15px;">
          Equivalente: Bs {{ (form.monto_pago * tasaBcv).toFixed(2) }}
        </div>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">Cancelar</el-button>
        <el-button type="primary" @click="saveReservacion" :loading="saving" style="background-color: var(--sakura-purple); border: none;">
          Confirmar y Guardar
        </el-button>
      </template>
    </el-dialog>

    <!-- Dialogo Registrar Pago -->
    <el-dialog title="Registrar Pago" v-model="pagoDialogVisible" width="450px">
      <!-- Tabla de deuda total -->
      <div v-if="currentRes" style="margin-bottom: 20px; border: 1px solid #ebeef5; border-radius: 8px; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
          <tr style="background: #fafafa; font-weight: bold;">
            <td style="padding: 8px; border-bottom: 1px solid #ebeef5;">Concepto</td>
            <td style="padding: 8px; border-bottom: 1px solid #ebeef5; text-align: right;">Total</td>
          </tr>
          <tr>
            <td style="padding: 6px 8px;">Stands</td>
            <td style="padding: 6px 8px; text-align: right;">${{ calcularTotalStands(currentRes).toFixed(2) }}</td>
          </tr>
          <tr v-if="calcularTotalMobiliario(currentRes) > 0">
            <td style="padding: 6px 8px;">Mobiliario y Extras</td>
            <td style="padding: 6px 8px; text-align: right;">${{ calcularTotalMobiliario(currentRes).toFixed(2) }}</td>
          </tr>
          <tr style="color: #67C23A;">
            <td style="padding: 6px 8px;">Monto ya Pagado</td>
            <td style="padding: 6px 8px; text-align: right;">-${{ calcularPagado(currentRes).toFixed(2) }}</td>
          </tr>
          <tr style="background: #fdf6ec; font-weight: bold; border-top: 2px solid #e6a23c;">
            <td style="padding: 8px;">DEUDA RESTANTE</td>
            <td style="padding: 8px; text-align: right; color: #E6A23C;">
              ${{ Math.max(0, (calcularTotalStands(currentRes) + calcularTotalMobiliario(currentRes)) - calcularPagado(currentRes)).toFixed(2) }}
              <br>
              <small style="font-weight: normal; color: #909399;">
                Bs {{ (Math.max(0, (calcularTotalStands(currentRes) + calcularTotalMobiliario(currentRes)) - calcularPagado(currentRes)) * tasaBcv).toFixed(2) }}
              </small>
            </td>
          </tr>
        </table>
      </div>

      <el-form :model="pagoForm" ref="pagoFormRef" label-position="top">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="Monto ($)" required>
              <el-input-number v-model="pagoForm.cantidad" :min="0.1" :precision="2" :step="10" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="Fecha de Pago" required>
              <el-date-picker 
                v-model="pagoForm.fecha_pago" 
                type="date" 
                placeholder="Seleccione fecha" 
                style="width: 100%"
                value-format="YYYY-MM-DD"
                @change="handleDateChange"
              />
            </el-form-item>
          </el-col>
        </el-row>
        
        <div v-if="loadingRate" style="margin-bottom: 10px; color: #9d4edd; font-size: 12px;">
          <el-icon class="is-loading"><Loading /></el-icon> Obteniendo tasa del histórico...
        </div>
        
        <!-- Lista de Pagos Realizados (Inmutables) -->
        <div v-if="currentRes?.pagos?.length > 0" style="margin-top: 15px; margin-bottom: 20px;">
          <div style="font-size: 12px; font-weight: bold; color: #909399; margin-bottom: 8px; text-transform: uppercase;">Pagos Registrados:</div>
          <div v-for="p in currentRes.pagos" :key="p.id_pagos" class="pago-item-fixed">
            <div class="pago-info">
              <span class="monto-usd">${{ parseFloat(p.cantidad).toFixed(2) }}</span>
              <span class="ref" v-if="p.numero_referencia"> - Ref: {{ p.numero_referencia }}</span>
            </div>
            <div class="pago-bs" v-if="p.tasa_bcv">
              Bs {{ (p.cantidad * p.tasa_bcv).toFixed(2) }} <small>(Tasa: {{ p.tasa_bcv }})</small>
            </div>
          </div>
        </div>

        <div v-if="pagoForm.cantidad > 0" style="background: linear-gradient(135deg, #f5f0ff, #fff0f5); padding: 12px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #e8d5f5;">
          <div style="font-size: 12px; color: #888; margin-bottom: 4px;">Equivalente en Bolívares (Tasa: {{ pagoForm.tasa_bcv }} Bs/$)</div>
          <div style="font-size: 22px; font-weight: bold; color: var(--sakura-purple);">
            Bs {{ (pagoForm.cantidad * pagoForm.tasa_bcv).toFixed(2) }}
          </div>
          <div style="font-size: 10px; color: #999; margin-top: 4px;" v-if="pagoForm.tasa_fuente">Fuente: {{ pagoForm.tasa_fuente }}</div>
        </div>

        <el-form-item label="Referencia">
          <el-input v-model="pagoForm.numero_referencia" placeholder="Nro de transferencia/pago móvil" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="pagoDialogVisible = false">Cerrar</el-button>
        <el-button type="success" @click="savePago" :loading="saving">Añadir Pago</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Calendar, Delete, Plus, Loading } from '@element-plus/icons-vue'

const reservaciones = ref([])
const usuarios = ref([])
const standsDisponibles = ref([])
const allStands = ref([])
const eventos = ref([])
const filterEvento = ref(null)

const loading = ref(false)
const currentRes = ref(null)
const tasaBcv = ref(75.00)

const dialogVisible = ref(false)
const saving = ref(false)
const formRef = ref(null)

const pagoDialogVisible = ref(false)
const pagoFormRef = ref(null)
const loadingRate = ref(false)

const form = ref({
  usuarios_id: '',
  stands: [],
  mobiliarios: [],
  descripcion: '',
  subido_redes: false,
  monto_pago: 0,
  referencia_pago: ''
})

const pagoForm = ref({
  reservacion_id: null,
  cantidad: 0,
  fecha_pago: new Date().toISOString().split('T')[0],
  tasa_bcv: 0,
  tasa_fuente: '',
  numero_referencia: ''
})

const rules = {
  usuarios_id: [{ required: true, message: 'El emprendedor es obligatorio', trigger: 'blur' }],
  stands: [{ required: true, message: 'Debe seleccionar al menos un stand', type: 'array', min: 1, trigger: 'blur' }]
}

const getStatusColor = (status) => {
  return status === 'confirmada' ? 'success' : (status === 'cancelada' ? 'danger' : 'warning')
}

const calcularPagado = (res) => {
  if (!res.pagos) return 0
  return res.pagos.reduce((total, p) => total + parseFloat(p.cantidad), 0)
}

const calcularTotalStands = (res) => {
  if (!res.detalles) return 0
  return res.detalles.reduce((total, d) => total + parseFloat(d.stand?.precio || 0), 0)
}

const calcularTotalMobiliario = (res) => {
  if (!res.mobiliarios || res.mobiliarios.length === 0) return 0
  return res.mobiliarios.reduce((total, m) => total + (parseFloat(m.cantidad || 0) * parseFloat(m.precio_unitario_usd || 0)), 0)
}

const totalStandsUsd = computed(() => {
  return form.value.stands.reduce((total, standId) => {
    const stand = allStands.value.find(s => s.id_stands === standId)
    return total + (stand ? parseFloat(stand.precio) : 0)
  }, 0)
})

const granTotalUsd = computed(() => {
  const mobs = form.value.mobiliarios.reduce((total, m) => total + ((m.cantidad || 0) * (m.precio_unitario_usd || 0)), 0)
  return totalStandsUsd.value + mobs
})

const fetchInitialData = async () => {
  try {
    const [resUsr, resStands, resEvent] = await Promise.all([
      axios.get('/api/usuarios'),
      axios.get('/api/stands'),
      axios.get('/api/eventos')
    ])
    usuarios.value = resUsr.data.filter(u => u.role_id === 2)
    allStands.value = resStands.data
    eventos.value = resEvent.data
  } catch (error) {
    ElMessage.error('Error al cargar datos maestros')
  }
}

const getGlobalSummaries = (param) => {
  const { columns, data } = param
  const sums = []
  columns.forEach((column, index) => {
    if (index === 0) {
      sums[index] = 'TOTALES'
      return
    }
    
    // Sumar Mobiliario
    if (column.label === 'Mobiliario') {
      const values = data.map(item => calcularTotalMobiliario(item))
      const total = values.reduce((prev, curr) => prev + curr, 0)
      sums[index] = `$${total.toFixed(2)}`
    }
    
    // Sumar Total Deuda
    if (column.label === 'Total Deuda') {
      const values = data.map(item => (calcularTotalStands(item) + calcularTotalMobiliario(item)))
      const total = values.reduce((prev, curr) => prev + curr, 0)
      sums[index] = `$${total.toFixed(2)}`
    }

    // Sumar Total Pagado
    if (column.label === 'Total Pagado') {
      const values = data.map(item => calcularPagado(item))
      const total = values.reduce((prev, curr) => prev + curr, 0)
      sums[index] = `$${total.toFixed(2)}`
    }

    // Sumar Equiv Total Bs
    if (column.label === 'Equiv. Total Bs') {
      const values = data.map(item => ((calcularTotalStands(item) + calcularTotalMobiliario(item)) * tasaBcv.value))
      const total = values.reduce((prev, curr) => prev + curr, 0)
      sums[index] = `Bs ${total.toFixed(2)}`
    }

    if (!sums[index]) sums[index] = ''
  })
  return sums
}

const fetchReservaciones = async () => {
  if (!filterEvento.value) {
    reservaciones.value = []
    standsDisponibles.value = []
    return
  }

  loading.value = true
  try {
    const res = await axios.get(`/api/reservaciones?evento_id=${filterEvento.value}`)
    reservaciones.value = res.data

    // Show only available stands for the selected event
    standsDisponibles.value = allStands.value.filter(s => s.eventos_id == filterEvento.value && s.status === 'disponible')
  } catch (error) {
    ElMessage.error('Error al cargar las reservaciones del evento')
  } finally {
    loading.value = false
  }
}

const fetchTasaBcv = async () => {
  try {
    const res = await axios.get('/api/tasa-bcv')
    if (res.data.tasa > 0) {
      tasaBcv.value = parseFloat(res.data.tasa)
    }
  } catch (e) {
    console.warn('No se pudo obtener la tasa BCV')
  }
}

const addMobiliario = () => {
  form.value.mobiliarios.push({ descripcion: '', cantidad: 1, precio_unitario_usd: 0 })
}

const removeMobiliario = (index) => {
  form.value.mobiliarios.splice(index, 1)
}

const openDialog = () => {
  form.value = { 
    usuarios_id: '', 
    stands: [], 
    mobiliarios: [],
    descripcion: '', 
    subido_redes: false,
    monto_pago: 0,
    referencia_pago: ''
  }
  dialogVisible.value = true
}

const saveReservacion = async () => {
  if (!formRef.value) return
  
  // Validar mobiliarios vacíos
  for (const m of form.value.mobiliarios) {
    if (!m.descripcion || m.descripcion.trim() === '') {
      ElMessage.warning('Todos los ítems de mobiliario deben tener una descripción.')
      return
    }
  }

  await formRef.value.validate(async (valid) => {
    if (valid) {
      saving.value = true
      try {
        const payload = { 
          ...form.value,
          evento_id: filterEvento.value,
          tasa_bcv: tasaBcv.value
        }
        await axios.post('/api/reservaciones', payload)
        ElMessage.success('Reservación creada con éxito')
        dialogVisible.value = false
        fetchReservaciones()
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

const changeStatus = async (res, status) => {
  try {
    await ElMessageBox.confirm(`¿Seguro que deseas marcarla como ${status}?`, 'Confirmación')
    await axios.patch(`/api/reservaciones/${res.id_reservacion}/status`, { status })
    ElMessage.success('Estado actualizado')
    fetchReservaciones()
  } catch (e) { }
}

const toggleRedes = async (res) => {
  try {
    await axios.patch(`/api/reservaciones/${res.id_reservacion}`, { subido_redes: res.subido_redes })
    ElMessage.success(res.subido_redes ? 'Marcado como subido a redes' : 'Desmarcado de redes')
  } catch (e) {
    ElMessage.error('Error al actualizar')
    res.subido_redes = !res.subido_redes
  }
}

const openPagoDialog = (res) => {
  currentRes.value = res
  pagoForm.value = { 
    reservacion_id: res.id_reservacion, 
    cantidad: 0, 
    fecha_pago: new Date().toISOString().split('T')[0],
    tasa_bcv: tasaBcv.value,
    tasa_fuente: 'Actual (BCV)',
    numero_referencia: ''
  }
  pagoDialogVisible.value = true
}

const handleDateChange = async (val) => {
  if (!val) return
  
  // Si es hoy, usar tasaBcv actual
  const hoy = new Date().toISOString().split('T')[0]
  if (val === hoy) {
    pagoForm.value.tasa_bcv = tasaBcv.value
    pagoForm.value.tasa_fuente = 'Actual (BCV)'
    return
  }

  loadingRate.value = true
  try {
    const res = await axios.get(`/api/tasa-bcv/historico?fecha=${val}`)
    pagoForm.value.tasa_bcv = parseFloat(res.data.tasa)
    pagoForm.value.tasa_fuente = res.data.fuente
  } catch (e) {
    ElMessage.warning('No se encontró tasa para esa fecha. Se usará la tasa actual.')
    pagoForm.value.tasa_bcv = tasaBcv.value
    pagoForm.value.tasa_fuente = 'Fallback (Actual)'
  } finally {
    loadingRate.value = false
  }
}

const savePago = async () => {
  saving.value = true
  try {
    if (pagoForm.value.cantidad > 0) {
      await axios.post('/api/pagos', {
        reservacion_id: pagoForm.value.reservacion_id,
        cantidad: pagoForm.value.cantidad,
        numero_referencia: pagoForm.value.numero_referencia,
        tasa_bcv: pagoForm.value.tasa_bcv,
        fecha_pago: pagoForm.value.fecha_pago
      })
    }
    
    ElMessage.success('Información actualizada correctamente')
    pagoDialogVisible.value = false
    fetchReservaciones()
  } catch (error) {
    if (error.response && error.response.status === 422) {
      const errors = error.response.data.errors
      const firstError = Object.values(errors)[0][0]
      ElMessage.error(firstError)
    } else {
      ElMessage.error('Error al procesar el pago: ' + (error.response?.data?.message || 'Error desconocido'))
    }
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  fetchInitialData()
  fetchTasaBcv()
})
</script>

<style scoped>
.pago-item-fixed {
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
.monto-usd {
  font-weight: bold;
  color: #303133;
}
.ref {
  color: #909399;
  font-size: 11px;
}
.pago-bs {
  text-align: right;
  font-size: 12px;
  color: #606266;
  font-weight: 500;
}
.pago-bs small {
  color: #999;
  font-weight: normal;
}
</style>
