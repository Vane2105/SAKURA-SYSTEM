<template>
  <div>
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 25px; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
      <div>
        <h2 style="margin: 0 0 15px 0; color: var(--sakura-dark);">CRM Emprendedores Sakura</h2>
        <div style="display: flex; gap: 10px;">
          <el-input v-model="search" placeholder="Buscar por tienda o nombre..." style="width: 250px;" clearable :prefix-icon="Search" />
          <el-select v-model="filterStatus" placeholder="Estado" clearable style="width: 150px;">
            <el-option label="Por Verificar" value="Por Verificar" />
            <el-option label="Documentos OK" value="Documentos OK" />
            <el-option label="Bloqueado" value="Bloqueado" />
          </el-select>
          <el-select v-model="filterRubro" placeholder="Rubro" clearable style="width: 150px;">
            <el-option v-for="r in rubrosUnicos" :key="r" :label="r" :value="r" />
          </el-select>
        </div>
      </div>
      <el-button type="primary" @click="openDialog()" style="background-color: var(--sakura-purple); border: none; height: 40px; font-weight: bold;">
        <el-icon style="margin-right: 5px;"><Plus /></el-icon> Registrar Emprendedor
      </el-button>
    </div>

    <el-table :data="filteredUsuarios" v-loading="loading" style="width: 100%; border-radius: 12px;" border stripe>
      <el-table-column prop="ci" label="Cédula" width="110" />
      <el-table-column label="Emprendedor / Tienda">
        <template #default="scope">
          <div style="font-weight: bold; color: var(--sakura-purple);">{{ scope.row.nombre_tienda || 'Sin Tienda' }}</div>
          <div style="font-size: 12px; color: #606266;">{{ scope.row.nombre }} {{ scope.row.apellido }}</div>
        </template>
      </el-table-column>
      <el-table-column prop="rubro" label="Rubro" width="120" />
      <el-table-column label="Estado" width="140" align="center">
        <template #default="scope">
          <el-tag :type="getStatusType(scope.row.estado_registro)" effect="dark" size="small">
            {{ scope.row.estado_registro || 'Por Verificar' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="Participaciones" width="160" align="center" sortable prop="participaciones">
        <template #default="scope">
          <el-tooltip content="Reservaciones Confirmadas" placement="top">
            <el-badge :value="scope.row.participaciones || 0" class="item" :type="scope.row.participaciones > 0 ? 'success' : 'info'">
              <el-icon size="20"><Calendar /></el-icon>
            </el-badge>
          </el-tooltip>
        </template>
      </el-table-column>
      <el-table-column label="Contacto">
        <template #default="scope">
          <div v-for="t in scope.row.telefonos" :key="t.id_telefonos" style="font-size: 12px;">
            <el-icon size="10"><Phone /></el-icon> {{ t.numeros_telefonos }}
          </div>
          <div v-if="scope.row.instagram" style="font-size: 11px; color: #E1306C; margin-top: 4px;">
            <b>@</b>{{ scope.row.instagram }}
          </div>
        </template>
      </el-table-column>
      <el-table-column label="Acciones" width="220" align="center">
        <template #default="scope">
          <div style="display: flex; gap: 5px; justify-content: center;">
            <el-button size="small" type="info" plain @click="viewUsuario(scope.row)">Ver</el-button>
            <el-button size="small" type="primary" plain @click="editUsuario(scope.row)">Editar</el-button>
            <el-popconfirm title="¿Eliminar emprendedor?" @confirm="deleteUsuario(scope.row.id)">
              <template #reference>
                <el-button size="small" type="danger" plain>Eliminar</el-button>
              </template>
            </el-popconfirm>
          </div>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog :title="form.id ? 'Editar Emprendedor' : 'Registrar Emprendedor'" v-model="dialogVisible" width="600px" border-radius="15px">
      <el-form :model="form" ref="formRef" :rules="rules" label-width="120px" label-position="top">
        <el-tabs type="border-card" style="border-radius: 8px; overflow: hidden;">
          <el-tab-pane label="Datos Básicos">
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
              <el-col :span="14">
                <el-form-item label="Nombre de la Tienda" prop="nombre_tienda">
                  <el-input v-model="form.nombre_tienda" placeholder="Ej: Sakura Accessories" />
                </el-form-item>
              </el-col>
              <el-col :span="10">
                <el-form-item label="Rubro" prop="rubro">
                  <el-input v-model="form.rubro" placeholder="Ej: Joyería" />
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
                <el-form-item label="Instagram" prop="instagram">
                  <el-input v-model="form.instagram" placeholder="@usuario">
                    <template #prefix><b>@</b></template>
                  </el-input>
                </el-form-item>
              </el-col>
            </el-row>

            <el-form-item label="Teléfono principal" prop="telefonoPrincipal">
              <el-input v-model="form.telefonoPrincipal" />
            </el-form-item>

            <el-form-item label="Dirección" prop="direccion">
              <el-input v-model="form.direccion" type="textarea" :rows="2" />
            </el-form-item>
          </el-tab-pane>

          <el-tab-pane label="Gestión CRM (Admin)">
            <el-form-item label="Estado del Registro">
              <el-radio-group v-model="form.estado_registro">
                <el-radio-button value="Por Verificar">Por Verificar</el-radio-button>
                <el-radio-button value="Documentos OK">Verificado</el-radio-button>
                <el-radio-button value="Bloqueado">Bloqueado</el-radio-button>
              </el-radio-group>
            </el-form-item>

            <el-form-item label="Notas Administrativas / Observaciones">
              <el-input 
                v-model="form.notas_admin" 
                type="textarea" 
                :rows="6" 
                placeholder="Escriba aquí acuerdos comerciales, necesidades técnicas (luz, agua) o comportamiento..."
              />
            </el-form-item>
          </el-tab-pane>
        </el-tabs>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="dialogVisible = false">Cancelar</el-button>
          <el-button type="primary" @click="saveUsuario" :loading="saving" style="background-color: var(--sakura-purple); border: none; font-weight: bold;">
            {{ form.id ? 'Actualizar' : 'Guardar' }}
          </el-button>
        </span>
      </template>
    </el-dialog>

    <!-- Dialogo Ver Detalles Emprendedor (CRM) -->
    <el-dialog title="Detalles del Emprendedor" v-model="detailVisible" width="650px" border-radius="15px">
      <div v-if="selectedUsuario" style="padding: 0 10px;">
        <!-- Cabecera -->
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #ebeef5;">
          <div>
            <div style="font-size: 22px; font-weight: bold; color: var(--sakura-purple); margin-bottom: 5px;">
              {{ selectedUsuario.nombre_tienda || 'Sin Tienda' }}
            </div>
            <div style="color: #606266; font-size: 15px;">
              <el-icon><User /></el-icon> {{ selectedUsuario.nombre }} {{ selectedUsuario.apellido }}
            </div>
            <div style="color: #909399; font-size: 13px; margin-top: 5px;">
              <b>CI:</b> {{ selectedUsuario.ci }} | <b>Rubro:</b> {{ selectedUsuario.rubro || 'N/A' }}
            </div>
          </div>
          <div style="text-align: right;">
            <el-tag :type="getStatusType(selectedUsuario.estado_registro)" effect="dark" size="large">
              {{ selectedUsuario.estado_registro }}
            </el-tag>
            <div v-if="selectedUsuario.instagram" style="margin-top: 10px; font-weight: bold;">
               <a :href="`https://instagram.com/${selectedUsuario.instagram.replace('@','')}`" target="_blank" style="color: #E1306C; text-decoration: none;">
                 <el-icon><InfoFilled /></el-icon> @{{ selectedUsuario.instagram.replace('@','') }}
               </a>
            </div>
          </div>
        </div>

        <!-- Cuerpo de Información -->
        <el-row :gutter="20">
          <el-col :span="12">
            <h4 style="margin: 0 0 10px 0; color: #303133;">Contacto</h4>
            <div v-for="t in selectedUsuario.telefonos" :key="t.id_telefonos" style="margin-bottom: 5px; font-size: 14px;">
              <el-icon><Phone /></el-icon> {{ t.numeros_telefonos }}
            </div>
            <div v-if="!selectedUsuario.telefonos?.length" style="color: #909399; font-size: 13px;">Sin números registrados</div>
            
            <h4 style="margin: 15px 0 10px 0; color: #303133;">Dirección</h4>
            <p style="font-size: 13px; color: #606266; line-height: 1.4; margin: 0;">{{ selectedUsuario.direccion || 'No especificada' }}</p>
          </el-col>
          
          <el-col :span="12">
            <h4 style="margin: 0 0 10px 0; color: #303133;">Notas Administrativas</h4>
            <div style="background: #fdf6ec; border: 1px solid #faecd8; padding: 12px; border-radius: 6px; font-size: 13px; color: #e6a23c; min-height: 80px;">
              {{ selectedUsuario.notas_admin || 'No hay observaciones guardadas para este emprendedor.' }}
            </div>
          </el-col>
        </el-row>

        <!-- Historial CRM -->
        <div style="margin-top: 25px;">
           <h4 style="margin: 0 0 15px 0; color: #303133; border-bottom: 2px solid var(--sakura-purple); display: inline-block; padding-bottom: 5px;">
               Historial de Participaciones ({{ selectedUsuario.reservacions?.length || 0 }})
           </h4>
           
           <el-table :data="selectedUsuario.reservacions" border size="small" v-if="selectedUsuario.reservacions?.length > 0">
              <el-table-column prop="id_reservacion" label="ID Res" width="80" align="center" />
              <el-table-column label="Evento">
                 <template #default="scope">
                    <strong>{{ scope.row.evento?.nombre || 'Desconocido' }}</strong>
                 </template>
              </el-table-column>
              <el-table-column label="Estado" width="120" align="center">
                 <template #default="scope">
                    <el-tag :type="scope.row.status === 'confirmada' ? 'success' : (scope.row.status === 'cancelada' ? 'danger' : 'warning')" size="small">
                      {{ scope.row.status.toUpperCase() }}
                    </el-tag>
                 </template>
              </el-table-column>
              <el-table-column label="Fecha" width="150">
                 <template #default="scope">{{ new Date(scope.row.created_at).toLocaleDateString() }}</template>
              </el-table-column>
           </el-table>
           <el-empty v-else description="No tiene participaciones registradas" :image-size="60" />
        </div>
      </div>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="detailVisible = false">Cerrar Detalle</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { ElMessage } from 'element-plus'
import { Search, Plus, Calendar, Phone, InfoFilled } from '@element-plus/icons-vue'

const usuarios = ref([])
const loading = ref(false)
const saving = ref(false)
const dialogVisible = ref(false)
const detailVisible = ref(false)
const formRef = ref(null)
const selectedUsuario = ref(null)

// Filtros
const search = ref('')
const filterStatus = ref('')
const filterRubro = ref('')

const form = ref({
  id: null,
  nombre: '',
  apellido: '',
  nombre_tienda: '',
  ci: '',
  telefonoPrincipal: '',
  direccion: '',
  rubro: '',
  instagram: '',
  estado_registro: 'Por Verificar',
  notas_admin: '',
  password: 'password123', 
  role_id: 2 
})

const filteredUsuarios = computed(() => {
  return usuarios.value.filter(u => {
    const matchSearch = !search.value || 
      (u.nombre_tienda && u.nombre_tienda.toLowerCase().includes(search.value.toLowerCase())) ||
      (u.nombre && u.nombre.toLowerCase().includes(search.value.toLowerCase())) ||
      (u.ci && u.ci.includes(search.value))
    
    const matchStatus = !filterStatus.value || u.estado_registro === filterStatus.value
    const matchRubro = !filterRubro.value || u.rubro === filterRubro.value
    
    return matchSearch && matchStatus && matchRubro
  })
})

const rubrosUnicos = computed(() => {
  const r = usuarios.value.map(u => u.rubro).filter(Boolean)
  return [...new Set(r)]
})

const getStatusType = (status) => {
  if (status === 'Documentos OK') return 'success'
  if (status === 'Bloqueado') return 'danger'
  return 'warning'
}

const rules = {
  nombre: [{ required: true, message: 'El nombre es obligatorio', trigger: 'blur' }],
  apellido: [{ required: true, message: 'El apellido es obligatorio', trigger: 'blur' }],
  nombre_tienda: [{ required: true, message: 'El nombre de la tienda es obligatorio', trigger: 'blur' }],
  ci: [
    { required: true, message: 'La cédula es obligatoria', trigger: 'blur' },
    { pattern: /^[0-9]+$/, message: 'La cédula debe ser numérica', trigger: 'blur' }
  ],
  telefonoPrincipal: [{ required: true, message: 'El teléfono es obligatorio', trigger: 'blur' }],
  direccion: [{ required: true, message: 'La dirección es obligatoria', trigger: 'blur' }]
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
    id: null,
    nombre: '', apellido: '', nombre_tienda: '', ci: '', 
    telefonoPrincipal: '', direccion: '', 
    rubro: '', instagram: '', estado_registro: 'Por Verificar', notas_admin: '',
    password: 'password123', role_id: 2 
  }
  dialogVisible.value = true
}

const editUsuario = (row) => {
  form.value = { 
    ...row, 
    telefonoPrincipal: row.telefonos?.[0]?.numeros_telefonos || '',
    password: '' // No enviar contraseña a menos que se quiera cambiar explícitamente
  }
  dialogVisible.value = true
}

const viewUsuario = async (row) => {
  try {
    loading.value = true
    const res = await axios.get(`/api/usuarios/${row.id}`) // Trae info completa con reservaciones
    selectedUsuario.value = res.data
    detailVisible.value = true
  } catch (error) {
    ElMessage.error('Error al cargar detalles')
  } finally {
    loading.value = false
  }
}

const saveUsuario = async () => {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (valid) {
      saving.value = true
      try {
        const payload = { ...form.value }
        if (!payload.id) {
            payload.email = (payload.ci || Date.now()) + '@emprendedor.local'
            if (!payload.password) payload.password = 'password123' // Default for new creations if empty
        } else {
            // Fix: Delete empty password to avoid overwriting existing password on update
            if (!payload.password) {
                delete payload.password;
            }
        }
        
        if (payload.telefonoPrincipal) {
          payload.telefonos = [payload.telefonoPrincipal]
        }

        if (payload.id) {
            await axios.put(`/api/usuarios/${payload.id}`, payload)
            ElMessage.success('Actualizado exitosamente')
        } else {
            await axios.post('/api/usuarios', payload)
            ElMessage.success('Registrado exitosamente')
        }
        
        dialogVisible.value = false
        fetchUsuarios()
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
