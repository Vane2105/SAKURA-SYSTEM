<template>
  <el-config-provider>
    <div v-if="!authStore.isAuthenticated">
      <router-view></router-view>
    </div>
    
    <el-container v-else style="height: 100vh;">
      <!-- Sidebar -->
      <el-aside width="250px" style="background-color: var(--sakura-dark);">
        <div class="logo-container" style="padding: 20px; text-align: center; color: white;">
          <h2>🌸 Sakura Fest</h2>
        </div>
        <el-menu
          active-text-color="#FFB7C5"
          background-color="transparent"
          text-color="#fff"
          :default-active="route.path"
          router
        >
          <el-menu-item index="/">
            <el-icon><DataBoard /></el-icon>
            <span>Dashboard</span>
          </el-menu-item>
          <el-menu-item index="/eventos">
            <el-icon><Calendar /></el-icon>
            <span>Eventos</span>
          </el-menu-item>
          <el-menu-item index="/stands">
            <el-icon><Location /></el-icon>
            <span>Stands</span>
          </el-menu-item>
          <el-menu-item index="/usuarios">
            <el-icon><User /></el-icon>
            <span>Emprendedores</span>
          </el-menu-item>
          <el-menu-item index="/reservaciones">
            <el-icon><Ticket /></el-icon>
            <span>Reservaciones</span>
          </el-menu-item>
          <el-menu-item index="/mobiliario">
            <el-icon><Monitor /></el-icon>
            <span>Mobiliario</span>
          </el-menu-item>
          <el-menu-item index="/gastos">
            <el-icon><Money /></el-icon>
            <span>Gastos</span>
          </el-menu-item>
          <el-menu-item index="/reportes">
            <el-icon><Document /></el-icon>
            <span>Reportes</span>
          </el-menu-item>
        </el-menu>
      </el-aside>

      <!-- Main Content -->
      <el-container>
        <el-header style="background-color: white;">
          <div style="font-weight: bold; font-size: 18px;">
            Bienvenido, {{ authStore.user?.nombre }}
          </div>
          <div>
            <el-button type="danger" plain @click="logout" size="small">Cerrar Sesión</el-button>
          </div>
        </el-header>
        
        <el-main style="background-color: var(--sakura-light);">
          <router-view></router-view>
        </el-main>
      </el-container>
    </el-container>
  </el-config-provider>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { DataBoard, Calendar, Location, User, Ticket, Document, Monitor, Money } from '@element-plus/icons-vue'

const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()

onMounted(async () => {
  if (authStore.token) {
    await authStore.fetchUser()
  }
})

const logout = () => {
  authStore.logout()
  router.push('/login')
}
</script>

<style scoped>
.logo-container h2 {
  margin: 0;
  font-family: 'Outfit', sans-serif;
  letter-spacing: 1px;
}
</style>
