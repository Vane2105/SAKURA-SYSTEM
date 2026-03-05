<template>
  <div>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <h2>Gestión de Eventos</h2>
      <el-button type="primary" @click="openDialog()" style="background-color: var(--sakura-purple); border: none;">
        Crear Evento
      </el-button>
    </div>

    <el-table :data="eventos" v-loading="loading" style="width: 100%" border stripe>
      <el-table-column prop="id_eventos" label="ID" width="80" />
      <el-table-column prop="nombre" label="Nombre" />
      <el-table-column prop="direccion" label="Ubicación" />
      <el-table-column prop="start_date" label="Inicio">
        <template #default="scope">
          {{ new Date(scope.row.start_date).toLocaleDateString() }}
        </template>
      </el-table-column>
      <el-table-column prop="end_date" label="Fin">
        <template #default="scope">
          {{ new Date(scope.row.end_date).toLocaleDateString() }}
        </template>
      </el-table-column>
      <el-table-column label="Acciones" width="150" align="center">
        <template #default="scope">
          <el-button size="small" @click="openDialog(scope.row)">Editar</el-button>
          <el-popconfirm title="¿Eliminar evento?" @confirm="deleteEvento(scope.row.id_eventos)">
            <template #reference>
              <el-button size="small" type="danger">Eliminar</el-button>
            </template>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>

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
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { ElMessage } from 'element-plus'

const eventos = ref([])
const loading = ref(false)
const saving = ref(false)
const dialogVisible = ref(false)
const formRef = ref(null)

const form = ref({
  id_eventos: null,
  nombre: '',
  descripcion: '',
  direccion: '',
  start_date: '',
  end_date: ''
})

const rules = {
  nombre: [{ required: true, message: 'Requerido', trigger: 'blur' }],
  direccion: [{ required: true, message: 'Requerido', trigger: 'blur' }],
  start_date: [{ required: true, message: 'Requerido', trigger: 'change' }],
  end_date: [{ required: true, message: 'Requerido', trigger: 'change' }]
}

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

const openDialog = (row = null) => {
  if (row) {
    form.value = { ...row }
    // Transform datetime to date string for datepicker
    form.value.start_date = form.value.start_date.split('T')[0]
    form.value.end_date = form.value.end_date.split('T')[0]
  } else {
    form.value = { id_eventos: null, nombre: '', descripcion: '', direccion: '', start_date: '', end_date: '' }
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
        fetchEventos()
      } catch (error) {
        ElMessage.error('Error al guardar')
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

onMounted(() => {
  fetchEventos()
})
</script>
