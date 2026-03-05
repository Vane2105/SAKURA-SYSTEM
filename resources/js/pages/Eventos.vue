<template>
  <div>
    <!-- ===== VISTA LISTA DE EVENTOS ===== -->
    <div v-if="currentView === 'list'">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>Gestión de Eventos</h2>
        <el-button type="primary" @click="openDialog()" style="background-color: var(--sakura-purple); border: none;">
          Crear Evento
        </el-button>
      </div>

      <el-table :data="eventos" v-loading="loading" style="width: 100%" border stripe>
        <el-table-column prop="id_eventos" label="ID" width="60" />
        <el-table-column prop="nombre" label="Nombre" min-width="120" />
        <el-table-column prop="direccion" label="Ubicación" min-width="100" />
        <el-table-column prop="start_date" label="Inicio" width="100">
          <template #default="scope">
            {{ new Date(scope.row.start_date).toLocaleDateString() }}
          </template>
        </el-table-column>
        <el-table-column prop="end_date" label="Fin" width="100">
          <template #default="scope">
            {{ new Date(scope.row.end_date).toLocaleDateString() }}
          </template>
        </el-table-column>
        <el-table-column label="Flujo de Caja (USD)" width="280">
          <template #default="{ row }">
            <div style="font-size: 12px; line-height: 1.5;">
              <div style="display: flex; justify-content: space-between;">
                <span>Ingresos Stands:</span> <b style="color: #67C23A;">+${{ parseFloat(row.ingresos_stand || 0).toFixed(2) }}</b>
              </div>
              <div style="display: flex; justify-content: space-between;">
                <span>Ingresos Mob.:</span> <b style="color: #E6A23C;">+${{ parseFloat(row.ingresos_mob || 0).toFixed(2) }}</b>
              </div>
              <div style="display: flex; justify-content: space-between;">
                <span>Egresos:</span> <b style="color: #F56C6C;">-${{ parseFloat(row.total_gastos || 0).toFixed(2) }}</b>
              </div>
              <div style="display: flex; justify-content: space-between; margin-top: 4px; border-top: 1px solid #eee; padding-top: 2px;">
                <span>NETO:</span> <b :style="{ color: row.rentabilidad >= 0 ? '#67C23A' : '#F56C6C' }">
                  {{ row.rentabilidad >= 0 ? '+' : '' }}${{ parseFloat(row.rentabilidad || 0).toFixed(2) }}
                </b>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="Acciones" width="200" align="center">
          <template #default="scope">
            <el-button size="small" type="primary" @click="openDashboard(scope.row)" icon="DataAnalysis">
              Panel
            </el-button>
            <el-button size="small" @click="openDialog(scope.row)" icon="Edit" circle />
            <el-popconfirm title="¿Eliminar evento?" @confirm="deleteEvento(scope.row.id_eventos)">
              <template #reference>
                <el-button size="small" type="danger" icon="Delete" circle />
              </template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <!-- ===== PANEL DE CONTROL DEL EVENTO (DASHBOARD) ===== -->
    <div v-if="currentView === 'dashboard'" class="dashboard-view">
      <div class="dashboard-nav">
        <el-button @click="backToList" icon="ArrowLeft">Volver a Eventos</el-button>
        <h2 style="margin: 0; color: var(--sakura-purple);">📊 Panel de Control: {{ dashboardData?.evento?.nombre }}</h2>
        <el-button @click="openDialog(dashboardData?.evento)" icon="Edit">Editar Evento</el-button>
      </div>

      <!-- RESUMEN FINANCIERO -->
      <el-row :gutter="15" style="margin-bottom: 15px;" v-loading="dashboardLoading">
        <el-col :span="5">
          <div class="finance-card income-stand">
            <div class="finance-label">Ingresos Stand</div>
            <div class="finance-value">+${{ resumen.ingresos_stand.toFixed(2) }}</div>
            <div class="finance-bs">+Bs {{ (resumen.ingresos_stand_bs || 0).toFixed(2) }}</div>
          </div>
        </el-col>
        <el-col :span="5">
          <div class="finance-card income-mob">
            <div class="finance-label">Ingresos Mob.</div>
            <div class="finance-value">+${{ resumen.ingresos_mob.toFixed(2) }}</div>
            <div class="finance-bs">+Bs {{ (resumen.ingresos_mob_bs || 0).toFixed(2) }}</div>
          </div>
        </el-col>
        <el-col :span="5">
          <div class="finance-card expense-ops">
            <div class="finance-label">Gastos Operativos</div>
            <div class="finance-value">-${{ resumen.egresos_operativos.toFixed(2) }}</div>
            <div class="finance-bs">-Bs {{ (resumen.egresos_operativos_bs || 0).toFixed(2) }}</div>
          </div>
        </el-col>
        <el-col :span="5">
          <div class="finance-card expense-prov">
            <div class="finance-label">Pago Proveedores</div>
            <div class="finance-value">-${{ resumen.egresos_proveedor.toFixed(2) }}</div>
            <div class="finance-bs">-Bs {{ (resumen.egresos_proveedor_bs || 0).toFixed(2) }}</div>
          </div>
        </el-col>
        <el-col :span="4">
          <div class="finance-card balance" :class="{ negative: resumen.balance_neto < 0 }">
            <div class="finance-label">Balance Neto</div>
            <div class="finance-value">{{ resumen.balance_neto >= 0 ? '+' : '' }}${{ resumen.balance_neto.toFixed(2) }}</div>
            <div class="finance-bs">{{ resumen.balance_neto_bs >= 0 ? '+' : '' }}Bs {{ (resumen.balance_neto_bs || 0).toFixed(2) }}</div>
          </div>
        </el-col>
      </el-row>

      <!-- ALERTA DE FLUJO MOBILIARIO -->
      <!-- ALERTA DE COMPROMISO MOBILIARIO -->
      <el-alert
        v-if="resumen.ingresos_mob > 0 && resumen.egresos_proveedor === 0"
        type="warning"
        show-icon
        :closable="false"
        style="margin-bottom: 20px; font-weight: bold; background-color: #fdf6ec; color: #e6a23c; border: 1px solid #faecd8;"
      >
        <template #title>
          ⚠️ Fondos de mobiliario por liquidar: Has recibido pagos por mobiliario pero no has registrado ningún egreso al proveedor.
        </template>
      </el-alert>

      <!-- ALERTA DE FLUJO MOBILIARIO (PÉRDIDA) -->
      <el-alert
        v-if="resumen.egresos_proveedor > resumen.ingresos_mob"
        type="error"
        show-icon
        :closable="false"
        style="margin-bottom: 20px; font-weight: bold;"
      >
        <template #title>
          ⚠️ ALERTA DE FLUJO: Los pagos a proveedores de mobiliario (${{ resumen.egresos_proveedor.toFixed(2) }}) SUPERAN los ingresos de mobiliario (${{ resumen.ingresos_mob.toFixed(2) }}). Estás cubriendo ${{ (resumen.egresos_proveedor - resumen.ingresos_mob).toFixed(2) }} de tu cuenta bancaria Sakura.
        </template>
      </el-alert>

      <!-- CONCILIACION BANCARIA STATS -->
      <el-card shadow="never" style="margin-bottom: 20px; border: 1px solid #e4e7ed; background-color: #fafbfc;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
          <div>
            <div style="font-weight: 900; color: var(--sakura-purple); font-size: 18px; margin-bottom: 8px;">
              🏦 Saldo Total en Banco (Bs): Bs {{ (resumen.total_ingresos_conciliado_bs || 0).toFixed(2) }}
            </div>
            <div style="display: flex; gap: 10px;">
              <el-tag type="success" effect="dark" size="small">✅ {{ resumen.conciliados }} pagos validados en banco</el-tag>
              <el-tag type="warning" effect="dark" size="small">⏳ {{ resumen.pendientes }} pagos sin conciliar</el-tag>
            </div>
            <div v-if="resumen.pendientes > 0" style="margin-top: 8px; font-size: 11px; color: #909399;">
              💡 Para conciliar, ve a <b>Reservaciones</b> → Historial de Pagos.
            </div>
          </div>
          <div style="text-align: right; font-size: 14px; line-height: 1.6; background: white; padding: 10px 15px; border-radius: 8px; border: 1px solid #ebeef5;">
            <div style="color: #606266; font-size: 12px;">Ingresos Teóricos: <b>Bs {{ (resumen.total_ingresos_bs || 0).toFixed(2) }}</b> <small>(${{ resumen.total_ingresos.toFixed(2) }})</small></div>
            <div style="color: #67C23A; margin-bottom: 5px;">Recibido en Banco: <b>Bs {{ (resumen.total_ingresos_conciliado_bs || 0).toFixed(2) }}</b> <small>(${{ resumen.total_ingresos_conciliado.toFixed(2) }})</small></div>
            <div style="border-top: 1px dashed #dcdfe6; padding-top: 5px; font-weight: 900; font-size: 15px;" 
                 :style="{ color: resumen.balance_neto_conciliado_bs >= 0 ? '#4facfe' : '#F56C6C' }">
              Balance Real Banco: Bs {{ (resumen.balance_neto_conciliado_bs || 0).toFixed(2) }}
              <div style="font-size: 11px; opacity: 0.8; font-weight: normal;">
                (Equivalente: ${{ (resumen.balance_neto_conciliado || 0).toFixed(2) }})
              </div>
            </div>
          </div>
        </div>
      </el-card>

      <!-- TABS: INGRESOS Y EGRESOS -->
      <el-tabs type="border-card" v-model="activeTab">
        <!-- TAB INGRESOS -->
        <el-tab-pane name="ingresos">
          <template #label>
            <span style="font-weight: bold;">💰 Ingresos <el-badge :value="ingresos.length" type="success" class="tab-badge" /></span>
          </template>
          <el-table :data="filteredIngresos" border stripe style="width: 100%;" max-height="500">
            <el-table-column prop="fecha" label="Fecha" width="100">
              <template #default="{ row }">{{ formatDate(row.fecha) }}</template>
            </el-table-column>
            <el-table-column prop="concepto" label="Concepto" min-width="200" show-overflow-tooltip />
            <el-table-column prop="tipo" label="Tipo" width="110" align="center">
              <template #default="{ row }">
                <el-tag :type="row.tipo === 'stand' ? 'primary' : 'warning'" size="small" effect="dark">
                  {{ row.tipo === 'stand' ? '🏪 Stand' : '🪑 Mob.' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="Monto USD" width="120" align="right">
              <template #default="{ row }"><b style="color: #67C23A;">${{ row.monto_usd.toFixed(2) }}</b></template>
            </el-table-column>
            <el-table-column label="Tasa" width="90" align="center">
              <template #default="{ row }">{{ row.tasa_bcv }}</template>
            </el-table-column>
            <el-table-column label="Monto Bs" width="130" align="right">
              <template #default="{ row }"><span style="color: var(--sakura-purple);">Bs {{ row.monto_bs.toFixed(2) }}</span></template>
            </el-table-column>
            <el-table-column prop="referencia" label="Ref." width="110" show-overflow-tooltip />
            <el-table-column label="Banco" width="110" align="center">
              <template #default="{ row }">
                <el-tag :type="row.conciliado ? 'success' : 'warning'" size="small" effect="dark">
                  {{ row.conciliado ? '✅ Validado' : '⏳ Pendiente' }}
                </el-tag>
              </template>
            </el-table-column>
          </el-table>

          <div style="margin-top: 15px; display: flex; gap: 10px;">
            <el-radio-group v-model="ingresoFilter" size="small">
              <el-radio-button value="todos">Todos</el-radio-button>
              <el-radio-button value="stand">Solo Stand</el-radio-button>
              <el-radio-button value="mobiliario">Solo Mob.</el-radio-button>
              <el-radio-button value="pendiente">⏳ Pendientes</el-radio-button>
              <el-radio-button value="conciliado">✅ Conciliados</el-radio-button>
            </el-radio-group>
          </div>
        </el-tab-pane>

        <!-- TAB EGRESOS -->
        <el-tab-pane name="egresos">
          <template #label>
            <span style="font-weight: bold;">📤 Egresos <el-badge :value="dashboardData?.evento?.gastos?.length || 0" type="danger" class="tab-badge" /></span>
          </template>
          <Gastos v-if="dashboardData?.evento" :evento="dashboardData.evento" @back="backToList" :embedded="true" />
          
          <!-- REPORTE DE CATEGORIAS -->
          <div v-if="gastosCategorias.length > 0" style="margin-top: 25px; border-top: 1px solid #ebeef5; padding-top: 15px;">
            <h4 style="margin: 0 0 15px 0; color: #606266; font-size: 14px;">📊 Desglose de Gastos por Categoría</h4>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
              <el-card v-for="cat in gastosCategorias" :key="cat.categoria" shadow="hover" style="min-width: 200px; padding: 5px; flex: 1;">
                <div style="font-size: 13px; font-weight: bold; color: #303133; margin-bottom: 8px;">
                  {{ cat.categoria }}
                  <el-tag size="small" type="info" style="float: right;">{{ cat.cantidad }} reg.</el-tag>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 14px;">
                  <span style="color: #F56C6C; font-weight: bold;">${{ parseFloat(cat.total_usd).toFixed(2) }}</span>
                  <span style="color: var(--sakura-purple); font-weight: bold;">Bs {{ parseFloat(cat.total_bs).toFixed(2) }}</span>
                </div>
              </el-card>
            </div>
          </div>
        </el-tab-pane>
      </el-tabs>
    </div>

    <!-- DIALOGO CREAR/EDITAR EVENTO -->
    <el-dialog :title="form.id_eventos ? 'Editar Evento' : 'Nuevo Evento'" v-model="dialogVisible" width="500px">
      <el-form :model="form" ref="formRef" :rules="rules" label-width="120px" label-position="top">
        <el-form-item label="Nombre" prop="nombre">
          <el-input v-model="form.nombre" />
        </el-form-item>
        <el-form-item label="Descripción">
          <el-input v-model="form.descripcion" type="textarea" />
        </el-form-item>
        <el-form-item label="Ubicación" prop="direccion">
          <el-input v-model="form.direccion" />
        </el-form-item>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="Fecha Inicio" prop="start_date">
              <el-date-picker v-model="form.start_date" type="date" value-format="YYYY-MM-DD" style="width: 100%;" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="Fecha Fin" prop="end_date">
              <el-date-picker v-model="form.end_date" type="date" value-format="YYYY-MM-DD" style="width: 100%;" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="Precio Base de Stand ($)" prop="base_precio_stand">
          <template #label>
            <span>Precio Base de Stand ($) </span>
            <el-tooltip content="Este precio se aplicará a los 20 stands del evento. Si se cambia después, actualizará todos los stands de este evento." placement="top">
              <el-icon><InfoFilled /></el-icon>
            </el-tooltip>
          </template>
          <el-input-number v-model="form.base_precio_stand" :min="0" :precision="2" :step="5" style="width: 100%;" />
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="dialogVisible = false">Cancelar</el-button>
          <el-button type="primary" @click="saveEvento" :loading="saving" style="background-color: var(--sakura-purple); border: none;">
            Guardar
          </el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { ElMessage } from 'element-plus'
import { InfoFilled, DataAnalysis, Edit, Delete, ArrowLeft } from '@element-plus/icons-vue'
import Gastos from './Gastos.vue'

const eventos = ref([])
const loading = ref(false)
const saving = ref(false)
const dialogVisible = ref(false)
const formRef = ref(null)

// Dashboard state
const currentView = ref('list') // 'list' or 'dashboard'
const dashboardData = ref(null)
const dashboardLoading = ref(false)
const activeTab = ref('ingresos')
const ingresoFilter = ref('todos')
const gastosCategorias = ref([])

const form = ref({
  id_eventos: null, nombre: '', descripcion: '', direccion: '',
  start_date: '', end_date: '', base_precio_stand: 26.00
})

const rules = {
  nombre: [{ required: true, message: 'El nombre es obligatorio', trigger: 'blur' }],
  direccion: [{ required: true, message: 'La ubicación es obligatoria', trigger: 'blur' }],
  start_date: [{ required: true, message: 'La fecha de inicio es obligatoria', trigger: 'change' }],
  end_date: [{ required: true, message: 'La fecha de fin es obligatoria', trigger: 'change' }]
}

// Computed: resumen financiero
const resumen = computed(() => {
  return dashboardData.value?.resumen || {
    ingresos_stand: 0, ingresos_mob: 0, total_ingresos: 0,
    ingresos_stand_bs: 0, ingresos_mob_bs: 0, total_ingresos_bs: 0,
    ingresos_stand_conciliado: 0, ingresos_mob_conciliado: 0, total_ingresos_conciliado: 0, total_ingresos_conciliado_bs: 0,
    egresos_operativos: 0, egresos_proveedor: 0, total_egresos: 0,
    egresos_operativos_bs: 0, egresos_proveedor_bs: 0, total_egresos_bs: 0,
    balance_neto: 0, balance_neto_bs: 0,
    balance_neto_conciliado: 0, balance_neto_conciliado_bs: 0,
    conciliados: 0, pendientes: 0
  }
})

const ingresos = computed(() => dashboardData.value?.ingresos || [])

const filteredIngresos = computed(() => {
  const list = ingresos.value
  switch (ingresoFilter.value) {
    case 'stand': return list.filter(i => i.tipo === 'stand')
    case 'mobiliario': return list.filter(i => i.tipo === 'mobiliario')
    case 'pendiente': return list.filter(i => !i.conciliado)
    case 'conciliado': return list.filter(i => i.conciliado)
    default: return list
  }
})

// === FETCH DATA ===
const fetchEventos = async () => {
  loading.value = true
  try {
    const res = await axios.get('/api/eventos')
    eventos.value = res.data
  } catch (error) {
    ElMessage.error('Error al cargar eventos')
  } finally {
    loading.value = false
  }
}

const fetchDashboard = async (eventoId) => {
  dashboardLoading.value = true
  try {
    const [dashRes, catRes] = await Promise.all([
      axios.get(`/api/eventos/${eventoId}`),
      axios.get(`/api/eventos/${eventoId}/gastos-categorias`)
    ])
    dashboardData.value = dashRes.data
    gastosCategorias.value = catRes.data
  } catch (error) {
    ElMessage.error('Error al cargar el panel financiero')
  } finally {
    dashboardLoading.value = false
  }
}

// === NAVIGATION ===
const openDashboard = (evento) => {
  currentView.value = 'dashboard'
  activeTab.value = 'ingresos'
  ingresoFilter.value = 'todos'
  fetchDashboard(evento.id_eventos)
}

const backToList = () => {
  currentView.value = 'list'
  dashboardData.value = null
  fetchEventos()
}

// CRUD functions
const openDialog = (row = null) => {
  if (row) {
    form.value = { ...row }
    if (form.value.start_date && form.value.start_date.includes('T')) {
      form.value.start_date = form.value.start_date.split('T')[0]
    }
    if (form.value.end_date && form.value.end_date.includes('T')) {
      form.value.end_date = form.value.end_date.split('T')[0]
    }
  } else {
    form.value = { id_eventos: null, nombre: '', descripcion: '', direccion: '', start_date: '', end_date: '', base_precio_stand: 26.00 }
  }
  dialogVisible.value = true
}

const saveEvento = async () => {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (valid) {
      saving.value = true
      try {
        if (form.value.id_eventos) {
          await axios.put(`/api/eventos/${form.value.id_eventos}`, form.value)
        } else {
          await axios.post('/api/eventos', form.value)
        }
        ElMessage.success('Guardado exitosamente')
        dialogVisible.value = false
        if (currentView.value === 'dashboard' && dashboardData.value?.evento?.id_eventos) {
          fetchDashboard(dashboardData.value.evento.id_eventos)
        }
        fetchEventos()
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

const deleteEvento = async (id) => {
  try {
    await axios.delete(`/api/eventos/${id}`)
    ElMessage.success('Eliminado exitosamente')
    fetchEventos()
  } catch (error) {
    ElMessage.error('Error al eliminar')
  }
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString()
}

onMounted(() => { fetchEventos() })
</script>

<style scoped>
.dashboard-view {
  padding: 5px;
}
.dashboard-nav {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid #f0f2f5;
}
.finance-card {
  padding: 15px;
  border-radius: 12px;
  color: white;
  text-align: center;
  min-height: 95px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
  transition: transform 0.2s;
}
.finance-card:hover {
  transform: translateY(-2px);
}
.finance-label {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  opacity: 0.9;
  margin-bottom: 4px;
}
.finance-value {
  font-size: 22px;
  font-weight: 900;
  margin-bottom: 2px;
}
.finance-bs {
  font-size: 13px;
  opacity: 0.85;
  font-weight: 500;
}
.finance-card.income-stand {
  background: linear-gradient(135deg, #11998e, #38ef7d);
}
.finance-card.income-mob {
  background: linear-gradient(135deg, #f2994a, #f2c94c);
}
.finance-card.expense-ops {
  background: linear-gradient(135deg, #ff416c, #ff4b2b);
}
.finance-card.expense-prov {
  background: linear-gradient(135deg, #e65c00, #f9d423);
}
.finance-card.balance {
  background: linear-gradient(135deg, #4facfe, #00f2fe);
}
.finance-card.balance.negative {
  background: linear-gradient(135deg, #232526, #414345);
}
.tab-badge {
  margin-left: 5px;
}
</style>
