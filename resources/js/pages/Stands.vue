<template>
  <div>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <h2>Gestión de Stands</h2>
      <el-button type="primary" @click="openDialog()" style="background-color: var(--sakura-purple); border: none;">
        Crear Stand
      </el-button>
    </div>

    <!-- Filtro -->
    <el-card shadow="never" style="margin-bottom: 20px;">
      <el-select v-model="filterEvento" placeholder="Filtrar por Evento" clearable @change="fetchStands" style="width: 300px;">
        <el-option v-for="e in eventos" :key="e.id_eventos" :label="e.nombre" :value="e.id_eventos" />
      </el-select>
    </el-card>

    <el-table :data="stands" v-loading="loading" style="width: 100%" border stripe>
      <el-table-column prop="name" label="Nombre/Código" />
      <el-table-column prop="evento.nombre" label="Evento" />
      <el-table-column prop="precio" label="Precio">
        <template #default="scope">
          ${{ Number(scope.row.precio).toFixed(2) }}
        </template>
      </el-table-column>
      <el-table-column prop="status" label="Estado">
        <template #default="scope">
          <el-tag :type="getStatusColor(scope.row.status)">
            {{ scope.row.status.toUpperCase() }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="Acciones" width="150" align="center">
        <template #default="scope">
          <el-button size="small" @click="openDialog(scope.row)">Editar</el-button>
          <el-popconfirm title="¿Eliminar stand?" @confirm="deleteStand(scope.row.id_stands)">
            <template #reference>
              <el-button size="small" type="danger">Eliminar</el-button>
            </template>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog :title="form.id_stands ? 'Editar Stand' : 'Nuevo Stand'" v-model="dialogVisible" width="400px">
      <el-form :model="form" ref="formRef" :rules="rules" label-width="120px" label-position="top">
        <el-form-item label="Evento Asociado" prop="eventos_id">
          <el-select v-model="form.eventos_id" placeholder="Seleccione un evento" style="width: 100%">
            <el-option v-for="e in eventos" :key="e.id_eventos" :label="e.nombre" :value="e.id_eventos" />
          </el-select>
        </el-form-item>
        <el-form-item label="Nombre / Código" prop="name">
          <el-input v-model="form.name" />
        </el-form-item>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="Precio" prop="precio">
              <el-input-number v-model="form.precio" :min="0" :precision="2" :step="10" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="Estado" prop="status">
              <el-select v-model="form.status" style="width: 100%">
                <el-option label="Disponible" value="disponible" />
                <el-option label="Reservado" value="reservado" />
                <el-option label="Ocupado" value="ocupado" />
                <el-option label="Mantenimiento" value="mantenimiento" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="dialogVisible = false">Cancelar</el-button>
          <el-button type="primary" @click="saveStand" :loading="saving" style="background-color: var(--sakura-purple); border: none;">
            Guardar
          </el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { ElMessage } from 'element-plus'

const stands = ref([])
const eventos = ref([])
const loading = ref(false)
const saving = ref(false)
const dialogVisible = ref(false)
const formRef = ref(null)
const filterEvento = ref('')

const form = ref({
  id_stands: null,
  eventos_id: '',
  name: '',
  precio: 0,
  status: 'disponible'
})

const rules = {
  eventos_id: [{ required: true, message: 'Requerido', trigger: 'change' }],
  name: [{ required: true, message: 'Requerido', trigger: 'blur' }],
  precio: [{ required: true, message: 'Requerido', trigger: 'blur' }],
  status: [{ required: true, message: 'Requerido', trigger: 'change' }]
}

const getStatusColor = (status) => {
  const colors = {
    disponible: 'success',
    reservado: 'warning',
    ocupado: 'danger',
    mantenimiento: 'info'
  }
  return colors[status] || 'info'
}

const fetchEventos = async () => {
  try {
    const res = await axios.get('/api/eventos')
    eventos.value = res.data
  } catch (error) {}
}

const fetchStands = async () => {
  loading.value = true
  try {
    let url = '/api/stands'
    if (filterEvento.value) {
      url += `?evento_id=${filterEvento.value}`
    }
    const res = await axios.get(url)
    stands.value = res.data
  } catch (error) {
    ElMessage.error('Error al cargar stands')
  } finally {
    loading.value = false
  }
}

const openDialog = (row = null) => {
  if (row) {
    form.value = { ...row }
  } else {
    form.value = { id_stands: null, eventos_id: filterEvento.value || '', name: '', precio: 0, status: 'disponible' }
  }
  dialogVisible.value = true
}

const saveStand = async () => {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (valid) {
      saving.value = true
      try {
        if (form.value.id_stands) {
          await axios.put(`/api/stands/${form.value.id_stands}`, form.value)
        } else {
          await axios.post('/api/stands', form.value)
        }
        ElMessage.success('Guardado exitosamente')
        dialogVisible.value = false
        fetchStands()
      } catch (error) {
        ElMessage.error('Error al guardar')
      } finally {
        saving.value = false
      }
    }
  })
}

const deleteStand = async (id) => {
  try {
    await axios.delete(`/api/stands/${id}`)
    ElMessage.success('Eliminado exitosamente')
    fetchStands()
  } catch (error) {
    ElMessage.error('Error al eliminar')
  }
}

onMounted(() => {
  fetchEventos()
  fetchStands()
})
</script>
