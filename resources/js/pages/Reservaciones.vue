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
      <el-table-column label="Tienda(s)" min-width="180">
        <template #default="{ row }">
          <b style="color: var(--sakura-purple);">{{ row.usuario?.nombre_tienda || row.usuario?.nombre }}</b>
          <template v-if="row.usuario2">
            <span style="margin: 0 4px; color: #999;">+</span>
            <b style="color: #7b2cbf;">{{ row.usuario2.nombre_tienda || row.usuario2.nombre }}</b>
          </template>
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
          <div>
            ${{ calcularPagado(scope.row).toFixed(2) }}
            <div v-if="calcularPagado(scope.row) > 0 && calcularPagado(scope.row) < (calcularTotalStands(scope.row) + (scope.row.mobiliario_precio || 0))">
              <el-tag size="small" type="warning" effect="dark" style="font-size: 10px; height: 18px; line-height: 18px; margin-top: 2px;">
                ABONO
              </el-tag>
            </div>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="Equiv. Bs" width="140">
        <template #default="scope">
          Bs {{ calcularTotalBs(scope.row).toFixed(2) }}
        </template>
      </el-table-column>
      <el-table-column label="Mobiliario" width="130">
        <template #default="scope">
          <template v-if="scope.row.mobiliario_precio !== null">
            ${{ parseFloat(scope.row.mobiliario_precio).toFixed(2) }}
            <el-tag :type="scope.row.mobiliario_pagado ? 'success' : 'danger'" size="small" style="margin-left: 4px;">
              {{ scope.row.mobiliario_pagado ? 'Pagado' : 'Pendiente' }}
            </el-tag>
          </template>
          <span v-else style="color: #ccc;">—</span>
        </template>
      </el-table-column>
      <el-table-column label="Redes" width="90" align="center">
        <template #default="scope">
          <el-switch v-model="scope.row.subido_redes" @change="toggleRedes(scope.row)" />
        </template>
      </el-table-column>
      <el-table-column label="Acciones" width="180" align="center">
        <template #default="scope">
          <el-button size="small" :type="calcularTotalStands(scope.row) + (scope.row.mobiliario_precio || 0) - calcularPagado(scope.row) > 0 ? 'success' : 'primary'" @click="openPagoDialog(scope.row)" v-if="scope.row.status !== 'cancelada'">
            {{ calcularTotalStands(scope.row) + (scope.row.mobiliario_precio || 0) - calcularPagado(scope.row) > 0 ? 'Pagar / Abono' : 'Ver / Mobiliario' }}
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
        <el-form-item label="Emprendedor Principal" prop="usuarios_id">
          <el-select v-model="form.usuarios_id" filterable placeholder="Buscar emprendedor..." style="width: 100%">
            <el-option v-for="u in usuarios" :key="u.id" :label="u.nombre_tienda || u.nombre" :value="u.id" />
          </el-select>
        </el-form-item>

        <el-form-item label="Tipo de Stand">
          <el-radio-group v-model="form.es_compartido">
            <el-radio :label="false">Individual</el-radio>
            <el-radio :label="true">Compartido (2 Tiendas)</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item v-if="form.es_compartido" label="Segundo Emprendedor" prop="usuario_2_id">
          <el-select v-model="form.usuario_2_id" filterable placeholder="Buscar segundo emprendedor..." style="width: 100%">
            <el-option v-for="u in usuarios" :key="u.id" :label="u.nombre_tienda || u.nombre" :value="u.id" :disabled="u.id === form.usuarios_id" />
          </el-select>
        </el-form-item>
        <el-form-item label="Stands" prop="stands">
          <el-select v-model="form.stands" multiple placeholder="Seleccionar stands..." style="width: 100%">
            <el-option v-for="s in standsDisponibles" :key="s.id_stands" :label="`${s.evento?.nombre} - ${s.name} ($${s.precio})`" :value="s.id_stands" />
          </el-select>
        </el-form-item>

        <!-- Resumen Detallado de Precios -->
        <div v-if="form.stands.length > 0 || form.mobiliario_precio > 0" style="margin-bottom: 20px; border: 1px solid #e4e7ed; border-radius: 8px; overflow: hidden;">
          <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <tr style="background: #f5f7fa;">
              <th style="padding: 8px; text-align: left; border-bottom: 1px solid #e4e7ed;">Concepto</th>
              <th style="padding: 8px; text-align: right; border-bottom: 1px solid #e4e7ed;">USD</th>
              <th style="padding: 8px; text-align: right; border-bottom: 1px solid #e4e7ed;">Bs (BCV)</th>
            </tr>
            <tr>
              <td style="padding: 8px; border-bottom: 1px solid #ebeef5;">Stands ({{ form.stands.length }})</td>
              <td style="padding: 8px; border-bottom: 1px solid #ebeef5; text-align: right;">${{ totalStandsUsd.toFixed(2) }}</td>
              <td style="padding: 8px; border-bottom: 1px solid #ebeef5; text-align: right;">Bs {{ (totalStandsUsd * form.tasa_bcv).toFixed(2) }}</td>
            </tr>
            <tr v-if="form.mobiliario_precio > 0">
              <td style="padding: 8px; border-bottom: 1px solid #ebeef5;">Alquiler Mobiliario</td>
              <td style="padding: 8px; border-bottom: 1px solid #ebeef5; text-align: right;">${{ parseFloat(form.mobiliario_precio || 0).toFixed(2) }}</td>
              <td style="padding: 8px; border-bottom: 1px solid #ebeef5; text-align: right;">Bs {{ ((form.mobiliario_precio || 0) * form.tasa_bcv).toFixed(2) }}</td>
            </tr>
            <tr style="background: #fdf6ec; font-weight: bold; font-size: 16px;">
              <td style="padding: 8px; color: var(--sakura-purple);">TOTAL A PAGAR</td>
              <td style="padding: 8px; text-align: right; color: var(--sakura-purple);">${{ (totalStandsUsd + (form.mobiliario_precio || 0)).toFixed(2) }}</td>
              <td style="padding: 8px; text-align: right; color: var(--sakura-purple);">Bs {{ ((totalStandsUsd + (form.mobiliario_precio || 0)) * form.tasa_bcv).toFixed(2) }}</td>
            </tr>
          </table>
        </div>

        <el-form-item label="Descripción (Opcional)">
          <el-input v-model="form.descripcion" />
        </el-form-item>


        <el-form-item label="¿Es un Abono? (Pago parcial)">
          <el-switch v-model="form.pago_cuotas" active-text="Sí (Abono)" inactive-text="No (Pago Completo)" />
          <div style="font-size: 11px; color: #909399; margin-top: 4px;">
            Si el monto pagado es menor al total, marque "Sí" para dejar la reserva pendiente.
          </div>
        </el-form-item>

        <el-divider>Registro del Pago Realizado</el-divider>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="Monto en USD">
              <el-input-number v-model="form.monto_pago" :min="0" :precision="2" :step="10" @input="updateInitialFromUsd" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="Monto en Bs">
              <el-input-number v-model="form.monto_bs" :min="0" :precision="2" :step="100" @input="updateInitialFromBs" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="Fecha del Pago">
              <el-date-picker 
                v-model="form.fecha_pago" 
                type="date" 
                style="width: 100%" 
                value-format="YYYY-MM-DD"
                @change="handleInitialDateChange"
              />
            </el-form-item>
          </el-col>
        </el-row>

        <div v-if="loadingInitialRate" style="margin-bottom: 10px; color: #9d4edd; font-size: 11px;">
          <el-icon class="is-loading"><Loading /></el-icon> Consultando tasa histórica...
        </div>

        <div style="margin-bottom: 15px; background: #f5f7fa; padding: 12px; border-radius: 8px;">
          <el-row :gutter="20" align="middle">
            <el-col :span="12">
              <span style="font-size: 13px; color: #606266; font-weight: bold;">Tasa de Cambio (Bs/$)</span>
              <div style="font-size: 11px; color: #909399; margin-bottom: 5px;">{{ form.tasa_fuente || 'Manual' }}</div>
            </el-col>
            <el-col :span="12">
              <el-input-number v-model="form.tasa_bcv" :precision="4" :step="0.1" @change="updateInitialFromUsd(form.monto_pago)" style="width: 100%" />
            </el-col>
          </el-row>
        </div>

        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="Referencia">
              <el-input v-model="form.referencia_pago" placeholder="Nro de ref." />
            </el-form-item>
          </el-col>

        </el-row>

        <el-divider>Mobiliario (Opcional)</el-divider>
        <el-form-item label="Costo del Mobiliario ($)">
          <el-input-number v-model="form.mobiliario_precio" :min="0" :precision="2" :step="5" style="width: 100%" placeholder="Precio si aplica" />
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
    <el-dialog 
      :title="(calcularTotalStands(currentRes) + (pagoForm.mobiliario_precio || 0) - calcularPagado(currentRes)) > 0 ? 'Registrar Pago / Abono' : 'Detalle de Reservación'" 
      v-model="pagoDialogVisible" 
      width="500px"
    >
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
          <tr v-if="pagoForm.mobiliario_precio">
            <td style="padding: 6px 8px;">Mobiliario</td>
            <td style="padding: 6px 8px; text-align: right;">${{ parseFloat(pagoForm.mobiliario_precio).toFixed(2) }}</td>
          </tr>
          <tr style="color: #67C23A;">
            <td style="padding: 6px 8px;">Monto ya Pagado</td>
            <td style="padding: 6px 8px; text-align: right;">-${{ calcularPagado(currentRes).toFixed(2) }}</td>
          </tr>
          <tr v-if="(calcularTotalStands(currentRes) + (pagoForm.mobiliario_precio || 0) - calcularPagado(currentRes)) > 0" style="background: #fdf6ec; font-weight: bold; border-top: 2px solid #e6a23c;">
            <td style="padding: 8px;">SALDO PENDIENTE</td>
            <td style="padding: 8px; text-align: right; color: #E6A23C;">
              ${{ (calcularTotalStands(currentRes) + (pagoForm.mobiliario_precio || 0) - calcularPagado(currentRes)).toFixed(2) }}
              <br>
              <small style="font-weight: normal; color: #909399;">
                Bs {{ ((calcularTotalStands(currentRes) + (pagoForm.mobiliario_precio || 0) - calcularPagado(currentRes)) * (pagoForm.tasa_bcv || tasaBcv)).toFixed(2) }}
              </small>
            </td>
          </tr>
          <tr v-else style="background: #f0f9eb; font-weight: bold; border-top: 2px solid #67c23a;">
            <td style="padding: 8px; color: #67c23a;">PAGO COMPLETADO</td>
            <td style="padding: 8px; text-align: right; color: #67C23A;">
              $0.00
              <br>
              <small style="font-weight: normal; color: #909399;">Bs 0.00</small>
            </td>
          </tr>
        </table>
      </div>

      <el-form :model="pagoForm" ref="pagoFormRef" label-position="top">
        <!-- 1. Mobiliario Section -->
        <div style="margin-top: 10px; margin-bottom: 20px; border: 1px solid #ebeef5; border-radius: 8px; overflow: hidden;">
          <div 
            @click="pagoForm.show_mobiliario = !pagoForm.show_mobiliario" 
            style="background: #fdfdfd; padding: 10px 15px; cursor: pointer; display: flex; justify-content: space-between; align-items: center;"
          >
            <span style="font-weight: bold; color: #606266;">
              <el-icon><Monitor /></el-icon> Alquiler de Mobiliario (Opcional)
            </span>
            <el-icon><ArrowDown v-if="!pagoForm.show_mobiliario"/><ArrowUp v-else/></el-icon>
          </div>
          
          <div v-if="pagoForm.show_mobiliario" style="padding: 15px; border-top: 1px solid #ebeef5; background: #fffaf0;">
            <el-row :gutter="10" align="bottom">
              <el-col :span="16">
                <el-form-item label="Costo del Mobiliario ($)">
                  <el-input-number v-model="pagoForm.mobiliario_precio" :min="0" :precision="2" :step="5" style="width: 100%;" />
                </el-form-item>
              </el-col>
              <el-col :span="8">
                <el-button 
                  v-if="!currentRes.mobiliario_pagado && (calcularTotalStands(currentRes) + (pagoForm.mobiliario_precio || 0) - calcularPagado(currentRes)) > 0"
                  type="warning" 
                  plain 
                  size="small" 
                  style="margin-bottom: 22px; width: 100%;"
                  @click="pagoForm.cantidad = (calcularTotalStands(currentRes) + (pagoForm.mobiliario_precio || 0) - calcularPagado(currentRes)).toFixed(2); updatePagoFromUsd(pagoForm.cantidad)"
                >
                  Pagar Deuda
                </el-button>
              </el-col>
            </el-row>
            <div v-if="pagoForm.mobiliario_precio > 0" style="font-size: 13px; color: #E6A23C; font-weight: bold; margin-top: -10px; margin-bottom: 10px;">
              Equivale a: Bs {{ (pagoForm.mobiliario_precio * (pagoForm.tasa_bcv || tasaBcv)).toFixed(2) }}
            </div>
            <el-alert v-if="currentRes.mobiliario_pagado" type="success" :closable="false" title="Mobiliario ya está pagado" />
          </div>
        </div>

        <!-- 2. Payment Section -->
        <template v-if="(calcularTotalStands(currentRes) + (pagoForm.mobiliario_precio || 0) - calcularPagado(currentRes)) > 0">
          <el-row :gutter="20">
            <el-col :span="12">
              <el-form-item label="Monto en USD" required>
                <el-input-number v-model="pagoForm.cantidad" :min="0" :precision="2" :step="10" @input="updatePagoFromUsd" style="width: 100%" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="Monto en Bs">
                <el-input-number v-model="pagoForm.monto_bs" :min="0" :precision="2" :step="100" @input="updatePagoFromBs" style="width: 100%" />
              </el-form-item>
            </el-col>
          </el-row>

          <div v-if="pagoForm.cantidad > 0" style="background: linear-gradient(135deg, #f5f0ff, #fff0f5); padding: 12px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #e8d5f5; border-left: 5px solid var(--sakura-purple);">
            <div style="font-size: 12px; color: #888; margin-bottom: 4px;">Equivalente en Bolívares (Tasa: {{ pagoForm.tasa_bcv }} Bs/$)</div>
            <div style="font-size: 22px; font-weight: bold; color: var(--sakura-purple);">
              Bs {{ (pagoForm.cantidad * (pagoForm.tasa_bcv || tasaBcv)).toFixed(2) }}
            </div>
            <div style="font-size: 10px; color: #999; margin-top: 4px;" v-if="pagoForm.tasa_fuente">Fuente: {{ pagoForm.tasa_fuente }}</div>
          </div>

          <el-row :gutter="20">
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
            <el-col :span="12">
              <el-form-item label="Tasa Aplicada (Bs/$)">
                <el-input-number v-model="pagoForm.tasa_bcv" :precision="4" :step="0.1" @change="updatePagoFromUsd(pagoForm.cantidad)" style="width: 100%" />
                <div style="font-size: 10px; color: #909399; margin-top: 4px;">{{ pagoForm.tasa_fuente || 'Manual' }}</div>
              </el-form-item>
            </el-col>
          </el-row>
          
          <div v-if="loadingRate" style="margin-bottom: 10px; color: #9d4edd; font-size: 12px;">
            <el-icon class="is-loading"><Loading /></el-icon> Obteniendo tasa del histórico...
          </div>

          <el-form-item label="Número de Referencia">
            <el-input v-model="pagoForm.numero_referencia" placeholder="Nro de transferencia/pago móvil" />
          </el-form-item>
        </template>

        <!-- 3. History Section -->
        <div v-if="currentRes?.pagos?.length > 0" style="margin-top: 25px; border-top: 2px dashed #eee; padding-top: 15px;">
          <div style="font-size: 12px; font-weight: bold; color: #909399; margin-bottom: 12px; text-transform: uppercase; display: flex; align-items: center;">
            <el-icon style="margin-right: 5px; color: #67c23a;"><Checked /></el-icon> Historial de Pagos Registrados:
          </div>
          <div v-for="p in currentRes.pagos" :key="p.id_pagos" class="pago-item-fixed">
            <div class="pago-info">
              <span class="monto-usd">${{ parseFloat(p.cantidad).toFixed(2) }}</span>
              <span class="ref" v-if="p.numero_referencia"> - Ref: {{ p.numero_referencia }}</span>
              <div style="font-size: 10px; color: #bbb; margin-top: 2px;">{{ p.fecha }}</div>
            </div>
            <div class="pago-bs" v-if="p.tasa_bcv">
              Bs {{ (p.cantidad * p.tasa_bcv).toFixed(2) }} <br>
              <small>(Tasa: {{ p.tasa_bcv }})</small>
            </div>
          </div>
        </div>
      </el-form>
      <template #footer>
        <el-button @click="pagoDialogVisible = false">Cerrar</el-button>
        <el-button type="success" :loading="saving" @click="savePago">
          {{ (calcularTotalStands(currentRes) + (pagoForm.mobiliario_precio || 0) - calcularPagado(currentRes)) > 0 ? 'Añadir Pago' : 'Actualizar Información' }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Monitor, ArrowDown, ArrowUp, Loading } from '@element-plus/icons-vue'

const reservaciones = ref([])
const usuarios = ref([])
const standsDisponibles = ref([])
const allStands = ref([])
const loading = ref(false)
const currentRes = ref(null)
const tasaBcv = ref(75.00)

const dialogVisible = ref(false)
const saving = ref(false)
const formRef = ref(null)

const pagoDialogVisible = ref(false)
const pagoFormRef = ref(null)
const loadingRate = ref(false)
const loadingInitialRate = ref(false)

const form = ref({
  usuarios_id: '',
  usuario_2_id: null,
  es_compartido: false,
  stands: [], 
  descripcion: '', 
  subido_redes: false,
  monto_pago: 0,
  monto_bs: 0, // Nuevo campo para Bs
  referencia_pago: '',
  fecha_pago: new Date().toISOString().split('T')[0],
  tasa_bcv: 0,
  tasa_fuente: '',
  mobiliario_precio: null,
  mobiliario_pagado: false,
  pago_cuotas: false
})

const pagoForm = ref({
  reservacion_id: null,
  cantidad: 0,
  monto_bs: 0, 
  fecha_pago: new Date().toISOString().split('T')[0],
  tasa_bcv: 0,
  tasa_fuente: '',
  numero_referencia: '',
  mobiliario_pagado: false,
  mobiliario_precio: 0,
  show_mobiliario: false
})

const rules = {
  usuarios_id: [{ required: true, message: 'El emprendedor es obligatorio', trigger: ['blur', 'change'] }],
  stands: [{ required: true, message: 'Debe seleccionar al menos un stand', type: 'array', min: 1, trigger: ['blur', 'change'] }]
}

const getStatusColor = (status) => {
  return status === 'confirmada' ? 'success' : (status === 'cancelada' ? 'danger' : 'warning')
}

const calcularPagado = (res) => {
  if (!res || !res.pagos) return 0
  return res.pagos.reduce((total, p) => total + parseFloat(p.cantidad), 0)
}

const calcularTotalStands = (res) => {
  if (!res || !res.detalles) return 0
  return res.detalles.reduce((total, d) => total + parseFloat(d.stand?.precio || 0), 0)
}

const calcularTotalBs = (res) => {
  if (!res) return 0
  const totalUsd = calcularTotalStands(res) + (parseFloat(res.mobiliario_precio) || 0)
  if (!res.pagos || res.pagos.length === 0) return totalUsd * tasaBcv.value
  
  let totalBs = 0
  let paidUsd = 0
  
  res.pagos.forEach(p => {
    if (p.status === 'aprobado' || p.status === 'pendiente') {
      const rate = parseFloat(p.tasa_bcv) || tasaBcv.value
      totalBs += parseFloat(p.cantidad) * rate
      paidUsd += parseFloat(p.cantidad)
    }
  })
  
  const pendingUsd = totalUsd - paidUsd
  if (pendingUsd > 0) {
    totalBs += pendingUsd * tasaBcv.value
  }
  
  return totalBs
}

const totalStandsUsd = computed(() => {
  return form.value.stands.reduce((total, standId) => {
    const stand = allStands.value.find(s => s.id_stands === standId)
    return total + (stand ? parseFloat(stand.precio) : 0)
  }, 0)
})

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
    allStands.value = resStands.data
    standsDisponibles.value = resStands.data.filter(s => s.status === 'disponible')
  } catch (error) {
    ElMessage.error('Error al cargar datos')
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

const openDialog = () => {
  form.value = { 
    usuarios_id: '', 
    usuario_2_id: null,
    es_compartido: false,
    stands: [], 
    descripcion: '', 
    subido_redes: false,
    monto_pago: 0,
    monto_bs: 0,
    referencia_pago: '',
    fecha_pago: new Date().toISOString().split('T')[0],
    tasa_bcv: tasaBcv.value,
    tasa_fuente: 'Actual (BCV)',
    mobiliario_precio: null,
    mobiliario_pagado: false,
    pago_cuotas: false
  }
  dialogVisible.value = true
}

const saveReservacion = async () => {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (valid) {
      saving.value = true
      try {
        const totalDeuda = totalStandsUsd.value + (form.value.mobiliario_precio || 0)
        
        // Si no es por cuotas, el monto debe ser el total
        if (!form.value.pago_cuotas && form.value.monto_pago < totalDeuda) {
          saving.value = false
          return ElMessageBox.confirm(
            `El monto ingresado ($${form.value.monto_pago}) es menor al total ($${totalDeuda}). ¿Deseas marcar la reservación como "Pago por Cuotas" automáticamente?`,
            'Confirmación de Monto',
            { confirmButtonText: 'Sí, Abono', cancelButtonText: 'No, Corregir' }
          ).then(() => {
            form.value.pago_cuotas = true
            saveReservacion()
          }).catch(() => {})
        }

        const payload = { 
          ...form.value,
          tasa_bcv: form.value.tasa_bcv
        }
        await axios.post('/api/reservaciones', payload)
        ElMessage.success('Reservación creada')
        dialogVisible.value = false
        fetchData()
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
    fetchData()
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
    numero_referencia: '',
    mobiliario_pagado: !!res.mobiliario_pagado,
    mobiliario_precio: res.mobiliario_precio !== null ? parseFloat(res.mobiliario_precio) : 0,
    show_mobiliario: res.mobiliario_precio !== null && parseFloat(res.mobiliario_precio) > 0
  }
  pagoDialogVisible.value = true
}

const updateInitialFromUsd = (val) => {
  if (form.value.tasa_bcv > 0) {
    form.value.monto_bs = parseFloat((val * form.value.tasa_bcv).toFixed(2))
  }
}

const updateInitialFromBs = (val) => {
  if (form.value.tasa_bcv > 0) {
    form.value.monto_pago = parseFloat((val / form.value.tasa_bcv).toFixed(2))
  }
}

const updatePagoFromUsd = (val) => {
  if (pagoForm.value.tasa_bcv > 0) {
    pagoForm.value.monto_bs = parseFloat((val * pagoForm.value.tasa_bcv).toFixed(2))
  }
}

const updatePagoFromBs = (val) => {
  if (pagoForm.value.tasa_bcv > 0) {
    pagoForm.value.cantidad = parseFloat((val / pagoForm.value.tasa_bcv).toFixed(2))
  }
}

const handleInitialDateChange = async (val) => {
  if (!val) return
  const hoy = new Date().toISOString().split('T')[0]
  if (val === hoy) {
    form.value.tasa_bcv = tasaBcv.value
    form.value.tasa_fuente = 'Actual (BCV)'
    updateInitialFromUsd(form.value.monto_pago)
    return
  }

  loadingInitialRate.value = true
  try {
    const res = await axios.get(`/api/tasa-bcv/historico?fecha=${val}`)
    form.value.tasa_bcv = parseFloat(res.data.tasa)
    form.value.tasa_fuente = res.data.fuente
    updateInitialFromUsd(form.value.monto_pago)
  } catch (e) {
    ElMessage.warning('No se encontró tasa para esa fecha. Se usará la tasa actual.')
    form.value.tasa_bcv = tasaBcv.value
    form.value.tasa_fuente = 'Fallback (Actual)'
    updateInitialFromUsd(form.value.monto_pago)
  } finally {
    loadingInitialRate.value = false
  }
}

const handleDateChange = async (val) => {
  if (!val) return
  
  // Si es hoy, usar tasaBcv actual
  const hoy = new Date().toISOString().split('T')[0]
  if (val === hoy) {
    pagoForm.value.tasa_bcv = tasaBcv.value
    pagoForm.value.tasa_fuente = 'Actual (BCV)'
    updatePagoFromUsd(pagoForm.value.cantidad)
    return
  }

  loadingRate.value = true
  try {
    const res = await axios.get(`/api/tasa-bcv/historico?fecha=${val}`)
    pagoForm.value.tasa_bcv = parseFloat(res.data.tasa)
    pagoForm.value.tasa_fuente = res.data.fuente
    updatePagoFromUsd(pagoForm.value.cantidad)
  } catch (e) {
    ElMessage.warning('No se encontró tasa para esa fecha. Se usará la tasa actual.')
    pagoForm.value.tasa_bcv = tasaBcv.value
    pagoForm.value.tasa_fuente = 'Fallback (Actual)'
    updatePagoFromUsd(pagoForm.value.cantidad)
  } finally {
    loadingRate.value = false
  }
}

const savePago = async () => {
  saving.value = true
  try {
    // 1. Registrar el pago monetario si hay monto
    if (pagoForm.value.cantidad > 0) {
      await axios.post('/api/pagos', {
        reservacion_id: pagoForm.value.reservacion_id,
        cantidad: pagoForm.value.cantidad,
        numero_referencia: pagoForm.value.numero_referencia,
        tasa_bcv: pagoForm.value.tasa_bcv,
        fecha: pagoForm.value.fecha_pago
      })
    }

    // 2. Siempre actualizar datos del mobiliario si se modificaron
    await axios.patch(`/api/reservaciones/${pagoForm.value.reservacion_id}`, {
      mobiliario_precio: pagoForm.value.mobiliario_precio > 0 ? pagoForm.value.mobiliario_precio : null,
      mobiliario_pagado: currentRes.value.mobiliario_pagado || (pagoForm.value.mobiliario_precio > 0 && (calcularTotalStands(currentRes.value) + pagoForm.value.mobiliario_precio - calcularPagado(currentRes.value)) <= 0)
    })
    
    ElMessage.success('Información actualizada correctamente')
    pagoDialogVisible.value = false
    fetchData()
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
  fetchData()
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
