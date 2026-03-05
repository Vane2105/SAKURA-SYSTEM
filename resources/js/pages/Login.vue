<template>
  <div class="auth-container">
    <el-card class="login-card" shadow="always">
      <div class="login-header">
        <h1>🌸 Sakura Fest</h1>
        <p>Panel de Administración</p>
      </div>

      <el-form :model="form" :rules="rules" ref="loginForm" @submit.prevent="handleLogin">
        <el-form-item prop="email">
          <el-input 
            v-model="form.email" 
            placeholder="Correo electrónico" 
            :prefix-icon="'Message'"
            size="large"
          />
        </el-form-item>
        
        <el-form-item prop="password">
          <el-input 
            v-model="form.password" 
            type="password" 
            placeholder="Contraseña" 
            :prefix-icon="'Lock'"
            show-password
            size="large"
          />
        </el-form-item>

        <el-button 
          type="primary" 
          native-type="submit" 
          :loading="loading" 
          class="login-btn"
          size="large"
        >
          Iniciar Sesión
        </el-button>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { ElMessage } from 'element-plus'

const router = useRouter()
const authStore = useAuthStore()
const loginForm = ref(null)

const loading = ref(false)
const form = ref({
  email: '',
  password: ''
})

const rules = {
  email: [
    { required: true, message: 'Ingrese su correo electrónico', trigger: 'blur' },
    { type: 'email', message: 'Ingrese un correo válido', trigger: 'blur' }
  ],
  password: [
    { required: true, message: 'Ingrese su contraseña', trigger: 'blur' }
  ]
}

const handleLogin = async () => {
  if (!loginForm.value) return
  
  await loginForm.value.validate(async (valid) => {
    if (valid) {
      loading.value = true
      try {
        await authStore.login(form.value.email, form.value.password)
        ElMessage.success('¡Bienvenido al sistema!')
        router.push('/')
      } catch (error) {
        const msg = error.response?.data?.message || error.response?.data?.errors?.email?.[0] || 'Error de conexión con el servidor.';
        ElMessage.error(msg)
      } finally {
        loading.value = false
      }
    }
  })
}
</script>

<style scoped>
.login-card {
  width: 400px;
  border-radius: 12px;
  border: none;
}
.login-header {
  text-align: center;
  margin-bottom: 30px;
}
.login-header h1 {
  color: var(--sakura-purple);
  margin: 0;
  font-size: 28px;
}
.login-header p {
  color: #7f8c8d;
  margin-top: 5px;
}
.login-btn {
  width: 100%;
  background-color: var(--sakura-purple);
  border-color: var(--sakura-purple);
}
.login-btn:hover {
  background-color: #8E44AD;
  border-color: #8E44AD;
}
</style>
