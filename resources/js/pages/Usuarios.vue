<template>
  <div>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <h2>Gestión de Emprendedores</h2>
      <el-button type="primary" @click="openDialog()" style="background-color: var(--sakura-purple); border: none;">
        Registrar Emprendedor
      </el-button>
    </div>

    <el-table :data="usuarios" v-loading="loading" style="width: 100%" border stripe>
      <el-table-column prop="ci" label="Cédula" width="120" />
      <el-table-column prop="nombre" label="Nombre" />
      <el-table-column prop="apellido" label="Apellido" />
      <el-table-column prop="email" label="Correo" />
      <el-table-column label="Teléfonos">
        <template #default="scope">
          <div v-for="t in scope.row.telefonos" :key="t.id_telefonos">
            {{ t.numeros_telefonos }}
          </div>
        </template>
      </el-table-column>
      <el-table-column label="Acciones" width="150" align="center">
        <template #default="scope">
          <!-- TODO: Add proper editing form later -->
          <el-popconfirm title="¿Eliminar emprendedor?" @confirm="deleteUsuario(scope.row.id)">
            <template #reference>
              <el-button size="small" type="danger">Eliminar</el-button>
            </template>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog title="Registrar Emprendedor" v-model="dialogVisible" width="500px">
      <el-form :model="form" ref="formRef" :rules="rules" label-width="120px" label-position="top">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="Nombre" prop="nombre">
              <el-input v-model="form.nombre" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="Apellido" prop="apellido">
              <el-input v-model="form.apellido" />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="Cédula" prop="ci">
              <el-input v-model="form.ci" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="Teléfono" prop="telefonoPrincipal">
              <el-input v-model="form.telefonoPrincipal" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="Correo Electrónico" prop="email">
          <el-input v-model="form.email" />
        </el-form-item>

        <el-form-item label="Dirección" prop="direccion">
          <el-input v-model="form.direccion" />
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="dialogVisible = false">Cancelar</el-button>
          <el-button type="primary" @click="saveUsuario" :loading="saving" style="background-color: var(--sakura-purple); border: none;">
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

const usuarios = ref([])
const loading = ref(false)
const saving = ref(false)
const dialogVisible = ref(false)
const formRef = ref(null)

const form = ref({
  nombre: '',
  apellido: '',
  ci: '',
  email: '',
  telefonoPrincipal: '',
  direccion: '',
  password: 'password123', // Static pass for manual registrations
  role_id: 2 // Emprendedor assumed role ID 2 based on DatabaseSeeder
})

const rules = {
  nombre: [{ required: true, message: 'Requerido', trigger: 'blur' }],
  email: [{ required: true, type: 'email', message: 'Correo válido requerido', trigger: 'blur' }]
}

const fetchUsuarios = async () => {
  loading.value = true
  try {
    const res = await axios.get('/api/usuarios')
    // Filter to show only Emprendedores (role_id 2)
    usuarios.value = res.data.filter(u => u.role_id === 2)
  } catch (error) {
    ElMessage.error('Error al cargar emprendedores')
  } finally {
    loading.value = false
  }
}

const openDialog = () => {
  form.value = { 
    nombre: '', apellido: '', ci: '', email: '', 
    telefonoPrincipal: '', direccion: '', 
    password: 'password123', role_id: 2 
  }
  dialogVisible.value = true
}

const saveUsuario = async () => {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (valid) {
      saving.value = true
      try {
        const payload = { ...form.value }
        if (payload.telefonoPrincipal) {
          payload.telefonos = [payload.telefonoPrincipal]
        }
        await axios.post('/api/usuarios', payload)
        ElMessage.success('Registrado exitosamente')
        dialogVisible.value = false
        fetchUsuarios()
      } catch (error) {
        ElMessage.error(error.response?.data?.message || 'Error al guardar')
      } finally {
        saving.value = false
      }
    }
  })
}

const deleteUsuario = async (id) => {
  try {
    await axios.delete(`/api/usuarios/${id}`)
    ElMessage.success('Eliminado exitosamente')
    fetchUsuarios()
  } catch (error) {
    ElMessage.error('Error al eliminar')
  }
}

onMounted(() => {
  fetchUsuarios()
})
</script>
