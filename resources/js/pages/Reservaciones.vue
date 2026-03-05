<template>
  <div>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <h2>Gestión de Reservaciones</h2>
      <el-button type="primary" @click="openDialog()" style="background-color: var(--sakura-purple); border: none;">
        Crear Reservación
      </el-button>
    </div>

    <el-table :data="reservaciones" v-loading="loading" style="width: 100%" border stripe>
      <el-table-column prop="id_reservacion" label="ID" width="60" />
      <el-table-column prop="usuario.nombre" label="Emprendedor">
        <template #default="scope">
          {{ scope.row.usuario?.nombre }} {{ scope.row.usuario?.apellido }}
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
      <el-table-column label="Pagado" width="120">
        <template #default="scope">
          ${{ calcularPagado(scope.row).toFixed(2) }}
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
    <el-dialog title="Nueva Reservación" v-model="dialogVisible" width="500px">
      <el-form :model="form" ref="formRef" :rules="rules" label-width="120px" label-position="top">
        <el-form-item label="Emprendedor" prop="usuarios_id">
          <el-select v-model="form.usuarios_id" filterable placeholder="Buscar emprendedor..." style="width: 100%">
            <el-option v-for="u in usuarios" :key="u.id" :label="`${u.ci} - ${u.nombre} ${u.apellido}`" :value="u.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="Stands" prop="stands">
          <el-select v-model="form.stands" multiple placeholder="Seleccionar stands..." style="width: 100%">
            <el-option v-for="s in standsDisponibles" :key="s.id_stands" :label="`${s.evento?.nombre} - ${s.name} ($${s.precio})`" :value="s.id_stands" />
          </el-select>
        </el-form-item>
        <el-form-item label="Descripción (Opcional)">
          <el-input v-model="form.descripcion" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">Cancelar</el-button>
        <el-button type="primary" @click="saveReservacion" :loading="saving" style="background-color: var(--sakura-purple); border: none;">
          Confirmar y Guardar
        </el-button>
      </template>
    </el-dialog>

    <!-- Dialogo Registrar Pago -->
    <el-dialog title="Registrar Pago" v-model="pagoDialogVisible" width="400px">
      <el-form :model="pagoForm" ref="pagoFormRef" label-position="top">
        <el-form-item label="Monto ($)" required>
          <el-input-number v-model="pagoForm.cantidad" :min="0.1" :precision="2" :step="10" style="width: 100%" />
        </el-form-item>
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
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { ElMessage, ElMessageBox } from 'element-plus'

const reservaciones = ref([])
const usuarios = ref([])
const standsDisponibles = ref([])
const loading = ref(false)

const dialogVisible = ref(false)
const saving = ref(false)
const formRef = ref(null)

const pagoDialogVisible = ref(false)
const pagoFormRef = ref(null)

const form = ref({
  usuarios_id: '',
  stands: [],
  descripcion: ''
})

const pagoForm = ref({
  reservacion_id: null,
  cantidad: 0,
  numero_referencia: ''
})

const rules = {
  usuarios_id: [{ required: true, message: 'Requerido', trigger: 'change' }],
  stands: [{ required: true, message: 'Requerido', type: 'array', min: 1, trigger: 'change' }]
}

const getStatusColor = (status) => {
  return status === 'confirmada' ? 'success' : (status === 'cancelada' ? 'danger' : 'warning')
}

const calcularPagado = (res) => {
  if (!res.pagos) return 0
  return res.pagos.reduce((total, p) => total + parseFloat(p.cantidad), 0)
}

const fetchData = async () => {
  loading.value = true
  try {
    const [resRes, resUsr, resStands] = await Promise.all([
      axios.get('/api/reservaciones'),
      axios.get('/api/usuarios'),
      axios.get('/api/stands')
    ])
    reservaciones.value = resRes.data
    usuarios.value = resUsr.data.filter(u => u.role_id === 2)
    standsDisponibles.value = resStands.data.filter(s => s.status === 'disponible')
  } catch (error) {
    ElMessage.error('Error al cargar datos')
  } finally {
    loading.value = false
  }
}

const openDialog = () => {
  form.value = { usuarios_id: '', stands: [], descripcion: '' }
  dialogVisible.value = true
}

const saveReservacion = async () => {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (valid) {
      saving.value = true
      try {
        await axios.post('/api/reservaciones', form.value)
        ElMessage.success('Reservación creada')
        dialogVisible.value = false
        fetchData()
      } catch (error) {
        ElMessage.error('Error al guardar')
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
    fetchData()
  } catch (e) { }
}

const openPagoDialog = (res) => {
  pagoForm.value = { reservacion_id: res.id_reservacion, cantidad: 0, numero_referencia: '' }
  pagoDialogVisible.value = true
}

const savePago = async () => {
  saving.value = true
  try {
    await axios.post('/api/pagos', pagoForm.value)
    ElMessage.success('Pago registrado')
    pagoDialogVisible.value = false
    fetchData()
  } catch (error) {
    ElMessage.error('Error al procesar pago')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  fetchData()
})
</script>
