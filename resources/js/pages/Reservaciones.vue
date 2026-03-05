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
      <el-table-column label="Tiendas">
        <template #default="scope">
          <div style="font-weight: bold;">{{ scope.row.usuario?.nombre_tienda || scope.row.usuario?.nombre }}</div>
          <div v-if="scope.row.usuario2" style="font-size: 11px; color: #909399;">
            Compartido con: {{ scope.row.usuario2?.nombre_tienda || scope.row.usuario2?.nombre }}
          </div>
        </template>
      </el-table-column>
      <el-table-column label="Estado de Pago" width="180">
        <template #default="scope">
          <el-tag :type="getStatusColor(scope.row.status)" effect="dark" size="small">
            Stand: {{ formatStatus(scope.row.status) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="Resumen Financiero" width="280">
        <template #default="scope">
          <div style="display: flex; flex-direction: column; gap: 4px; font-size: 12px;">
            <div style="display: flex; justify-content: space-between;">
              <span>Stand:</span>
              <b>${{ calcularTotalStands(scope.row).toFixed(2) }}</b>
            </div>
            <div style="display: flex; justify-content: space-between; color: #F56C6C;">
              <span>Deuda Stand:</span>
              <b>${{ Math.max(0, calcularTotalStands(scope.row) - calcularPagado(scope.row, 'stand')).toFixed(2) }}</b>
            </div>
            <el-divider style="margin: 4px 0;" />
            <div style="display: flex; justify-content: space-between; color: #67C23A;">
              <span>Mobiliario Pagado:</span>
              <b>${{ (scope.row.monto_mobiliario || 0).toFixed(2) }}</b>
            </div>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="Acciones" width="300" align="center">
        <template #default="scope">
          <div style="display: flex; gap: 5px; justify-content: center; flex-wrap: wrap;">
            <el-button size="small" circle type="info" @click="openVerDialog(scope.row)" :icon="View" />
            <el-button size="small" type="primary" @click="openPagoDialog(scope.row)" :disabled="isSolventeStand(scope.row)">
              Stand
            </el-button>
            <el-button size="small" type="warning" @click="openMobiliarioDialog(scope.row)">
              Mob.
            </el-button>
            <el-tooltip :content="hasConciliado(scope.row) ? 'Desconcilie los pagos antes de eliminar' : 'Eliminar reservación'" placement="top">
              <el-button size="small" circle type="danger" @click="confirmDelete(scope.row)" :icon="Delete" :disabled="hasConciliado(scope.row)" />
            </el-tooltip>
          </div>
        </template>
      </el-table-column>
    </el-table>

    <!-- Dialogo Crear Reservacion -->
    <el-dialog title="Nueva Reservación" v-model="dialogVisible" width="600px">
      <el-form :model="form" ref="formRef" :rules="rules" label-width="120px" label-position="top">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="Emprendedor Principal (Selector Inteligente)" prop="usuarios_id">
              <el-select 
                v-model="form.usuarios_id" 
                filterable 
                placeholder="Buscar por Nombre o Tienda..." 
                style="width: 100%"
                @change="handleUsuarioChange"
              >
                <el-option 
                  v-for="u in usuarios" 
                  :key="u.id" 
                  :label="`${u.nombre_tienda || 'Sin Tienda'} - ${u.nombre}`" 
                  :value="u.id"
                >
                    <div style="display: flex; justify-content: space-between;">
                      <span>{{ u.nombre_tienda || u.nombre }}</span>
                      <el-tag v-if="u.estado_registro === 'Bloqueado'" type="danger" size="small">BLOQUEADO</el-tag>
                    </div>
                </el-option>
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="Segundo Emprendedor (Socio Opcional)">
              <el-select v-model="form.usuario_2_id" filterable clearable placeholder="Buscar socio..." style="width: 100%">
                <el-option v-for="u in usuarios" :key="u.id" :label="u.nombre_tienda || u.nombre" :value="u.id" :disabled="u.id === form.usuarios_id" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>

        <!-- AREA DE AUTO-LLENADO / INFO CRM -->
        <div v-if="selectedUser" style="margin-bottom: 20px; padding: 15px; background: #fdf6ec; border-radius: 8px; border: 1px dashed #e6a23c;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
              <div style="font-weight: bold; color: var(--sakura-purple); font-size: 16px;">{{ selectedUser.nombre_tienda || 'Emprendedor Individual' }}</div>
              <div style="font-size: 13px; color: #606266;">
                <el-icon><User /></el-icon> {{ selectedUser.nombre }} {{ selectedUser.apellido }} | 
                <el-icon><Phone /></el-icon> {{ selectedUser.telefonos?.[0]?.numeros_telefonos || 'Sin Tlf' }}
              </div>
              <div v-if="selectedUser.instagram" style="font-size: 12px; color: #E1306C; margin-top: 5px;">
                <b>Instagram:</b> @{{ selectedUser.instagram }} | <b>Rubro:</b> {{ selectedUser.rubro || 'N/A' }}
              </div>
            </div>
            <div style="text-align: right;">
              <el-tag :type="getStatusColorCRM(selectedUser.estado_registro)" effect="dark">
                {{ selectedUser.estado_registro }}
              </el-tag>
              <div v-if="selectedUser.estado_registro === 'Bloqueado'" style="color: #F56C6C; font-weight: bold; font-size: 11px; margin-top: 5px;">
                ⚠️ ACCESO RESTRINGIDO
              </div>
            </div>
          </div>
        </div>

        <el-row :gutter="20">
          <el-col :span="24">
            <el-form-item label="Stands" prop="stands">
              <el-select v-model="form.stands" multiple placeholder="Seleccionar stands..." style="width: 100%" @change="validateFormPayment">
                <el-option v-for="s in standsDisponibles" :key="s.id_stands" :label="`${s.evento?.nombre} - ${s.name} ($${s.precio})`" :value="s.id_stands" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>

        <el-divider>Registro de Pago Inicial (STAND)</el-divider>
        <div v-if="form.stands.length > 0" style="margin-bottom: 20px; border: 1px solid #e4e7ed; border-radius: 8px; overflow: hidden;">
          <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <tr style="background: #f5f7fa; font-weight: bold; font-size: 15px;">
              <td style="padding: 10px 8px; color: #606266;">TOTAL STAND</td>
              <td style="padding: 10px 8px; text-align: right; color: #606266;">${{ totalStandsUsd.toFixed(2) }}</td>
              <td style="padding: 10px 8px; text-align: right; color: #606266;">Bs {{ (totalStandsUsd * form.tasa_bcv).toFixed(2) }}</td>
            </tr>
            <tr v-if="form.monto_pago > 0" style="background: #f0f9eb; font-weight: bold;">
              <td style="padding: 8px; color: #67c23a;">ABONO INICIAL STAND</td>
              <td style="padding: 8px; text-align: right; color: #67c23a;">-${{ form.monto_pago.toFixed(2) }}</td>
              <td style="padding: 8px; text-align: right; color: #67c23a;">-Bs {{ (form.monto_pago * form.tasa_bcv).toFixed(2) }}</td>
            </tr>
            <tr style="background: #fef0f0; font-weight: bold; font-size: 17px; border-top: 2px solid var(--sakura-purple);">
              <td style="padding: 10px 8px; color: var(--sakura-purple);">DEUDA RESTANTE STAND</td>
              <td style="padding: 10px 8px; text-align: right; color: var(--sakura-purple);">${{ Math.max(0, totalStandsUsd - form.monto_pago).toFixed(2) }}</td>
              <td style="padding: 10px 8px; text-align: right; color: var(--sakura-purple);">Bs {{ (Math.max(0, totalStandsUsd - form.monto_pago) * form.tasa_bcv).toFixed(2) }}</td>
            </tr>
          </table>
        </div>

        <el-form-item label="Fecha de Pago Inicial">
          <el-date-picker v-model="form.fecha_pago" type="date" placeholder="Seleccione fecha" style="width: 100%" value-format="YYYY-MM-DD" @change="handleFormDateChange" />
        </el-form-item>

        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="Monto inicial Bs">
              <el-input-number v-model="form.monto_bs" :min="0" :precision="2" style="width: 100%" @change="recalculateFormUsd" @blur="validateFormPayment" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="Monto inicial $">
              <el-input-number v-model="form.monto_pago" :min="0" :precision="2" style="width: 100%" @change="recalculateFormBs" @blur="validateFormPayment" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="Tasa Aplicada (Bs/$)">
          <el-input-number v-model="form.tasa_bcv" :min="0.01" :precision="2" style="width: 100%" @change="recalculateFormUsd" />
        </el-form-item>

        <el-form-item label="Referencia">
          <el-input v-model="form.referencia_pago" placeholder="Nro de referencia" />
        </el-form-item>

      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">Cancelar</el-button>
        <el-button type="primary" @click="saveReservacion" :loading="saving" style="background-color: var(--sakura-purple); border: none;">
          Guardar Reservación
        </el-button>
      </template>
    </el-dialog>

    <!-- Dialogo Registrar Pago STAND -->
    <el-dialog title="Registrar Pago de STAND" v-model="pagoDialogVisible" width="450px">
      <div v-if="currentRes" style="margin-bottom: 20px; padding: 15px; background: #fdf6ec; border-radius: 8px; border: 1px solid #e6a23c;">
        <div style="font-weight: bold; color: #E6A23C; margin-bottom: 8px; text-transform: uppercase; font-size: 12px;">Caja Stand:</div>
        <div style="display: flex; justify-content: space-between; font-size: 14px;">
          <span>Precio Stand:</span>
          <b>${{ calcularTotalStands(currentRes).toFixed(2) }}</b>
        </div>
        <div style="display: flex; justify-content: space-between; font-size: 14px; color: #67C23A;">
          <span>Pagado Stand:</span>
          <b>-${{ calcularPagado(currentRes, 'stand').toFixed(2) }}</b>
        </div>
        <el-divider style="margin: 8px 0;" />
        <div style="display: flex; justify-content: space-between; font-size: 16px; font-weight: bold; color: var(--sakura-purple);">
          <span>PENDIENTE STAND:</span>
          <span>${{ currentStandRemainingDebt.toFixed(2) }}</span>
        </div>
      </div>

      <el-form :model="pagoForm" ref="pagoFormRef" label-position="top">
        <el-form-item label="Fecha de Pago" required>
          <el-date-picker v-model="pagoForm.fecha_pago" type="date" style="width: 100%" value-format="YYYY-MM-DD" @change="handleDateChange" />
        </el-form-item>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="Monto Bs">
              <el-input-number v-model="pagoForm.monto_bs" :min="0" :precision="2" style="width: 100%" @change="recalculateUsd" @blur="validatePaymentAmount" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="Monto USD ($)">
              <el-input-number v-model="pagoForm.cantidad" :min="0" :precision="2" style="width: 100%" @change="recalculateBs" @blur="validatePaymentAmount" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="Tasa BCV (Bs/$)">
          <el-input-number v-model="pagoForm.tasa_bcv" :min="0.01" :precision="2" style="width: 100%" @change="recalculateUsd" />
        </el-form-item>
        <el-form-item label="Referencia">
          <el-input v-model="pagoForm.numero_referencia" placeholder="Ref. transferencia" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="pagoDialogVisible = false">Cerrar</el-button>
        <el-button type="primary" @click="savePago('stand')" :loading="saving">Añadir Pago Stand</el-button>
      </template>
    </el-dialog>

    <!-- Dialogo Mobiliario Pago al Contado -->
    <el-dialog title="Añadir Mobiliario (Pago al Contado)" v-model="mobDialogVisible" width="500px">
      <div v-if="currentRes">
        <div style="background: #f0f9eb; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c2e7b0;">
           <div style="font-weight: bold; color: #67C23A; margin-bottom: 8px; text-transform: uppercase; font-size: 12px;">Mobiliario Asignado:</div>
           <div style="display: flex; justify-content: space-between; font-size: 18px; font-weight: bold; color: #67C23A;">
             <span>TOTAL PAGADO:</span>
             <span>${{ (currentRes.monto_mobiliario || 0).toFixed(2) }}</span>
           </div>
        </div>

        <p style="font-size: 13px; color: #606266; margin-bottom: 15px;">
          Solo puedes añadir mobiliario registrando su pago inmediato. No se permite deuda de mobiliario.
        </p>

        <el-divider>Registrar Nuevo Pago de Mobiliario</el-divider>
        <el-form :model="mobPagoForm" label-position="top">
          <el-row :gutter="20">
             <el-col :span="12">
               <el-form-item label="Fecha">
                 <el-date-picker v-model="mobPagoForm.fecha_pago" type="date" style="width: 100%" value-format="YYYY-MM-DD" @change="handleMobDateChange" />
               </el-form-item>
             </el-col>
             <el-col :span="12">
               <el-form-item label="Tasa BCV">
                 <el-input-number v-model="mobPagoForm.tasa_bcv" :min="0.01" :precision="2" style="width: 100%" @change="recalculateMobUsd" />
               </el-form-item>
             </el-col>
          </el-row>
          <el-row :gutter="20">
             <el-col :span="12">
               <el-form-item label="Monto Bs">
                 <el-input-number v-model="mobPagoForm.monto_bs" :min="0" :precision="2" style="width: 100%" @change="recalculateMobUsd" />
               </el-form-item>
             </el-col>
             <el-col :span="12">
               <el-form-item label="Monto USD ($)">
                 <el-input-number v-model="mobPagoForm.cantidad" :min="0" :precision="2" style="width: 100%" @change="recalculateMobBs" />
               </el-form-item>
             </el-col>
          </el-row>
          <el-form-item label="Referencia">
            <el-input v-model="mobPagoForm.numero_referencia" placeholder="Ref. de pago" />
          </el-form-item>
          <el-button type="warning" style="width: 100%; height: 50px; font-weight: bold;" @click="savePagoMobiliario" :loading="saving">
            PAGAR Y ASIGNAR MOBILIARIO
          </el-button>
        </el-form>
      </div>
    </el-dialog>

    <!-- Dialogo Ver Detalles -->
    <el-dialog title="Detalle de Reservación" v-model="verDialogVisible" width="650px" custom-class="ver-detalle-dialog">
      <div v-if="verData" style="font-family: 'Outfit', sans-serif;">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="ID">{{ verData.id_reservacion }}</el-descriptions-item>
          <el-descriptions-item label="Fecha">{{ new Date(verData.created_at).toLocaleDateString() }}</el-descriptions-item>
          <el-descriptions-item label="Tienda Principal" :span="2">
            <b>{{ verData.usuario?.nombre_tienda || verData.usuario?.nombre }}</b>
          </el-descriptions-item>
          <el-descriptions-item label="Compartido con" v-if="verData.usuario2" :span="2">
            {{ verData.usuario2?.nombre_tienda || verData.usuario2?.nombre }}
          </el-descriptions-item>
          <el-descriptions-item label="Estado Stand">
            <el-tag :type="getStatusColor(verData.status)">{{ formatStatus(verData.status) }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="Mobiliario Pagado">
            <el-tag type="success" effect="plain">${{ (verData.monto_mobiliario || 0).toFixed(2) }}</el-tag>
          </el-descriptions-item>
        </el-descriptions>

        <h4 style="margin: 20px 0 10px; color: var(--sakura-purple); border-bottom: 2px solid #f0f2f5; padding-bottom: 5px;">
          <el-icon><Location /></el-icon> Stands Reservados
        </h4>
        <el-table :data="verData.detalles" size="small" border stripe>
          <el-table-column label="Stand">
            <template #default="s"><b>{{ s.row.stand?.name }}</b></template>
          </el-table-column>
          <el-table-column label="Precio" align="right">
            <template #default="s">${{ parseFloat(s.row.stand?.precio || 0).toFixed(2) }}</template>
          </el-table-column>
        </el-table>

        <h4 style="margin: 20px 0 10px; color: var(--sakura-purple); border-bottom: 2px solid #f0f2f5; padding-bottom: 5px;">
          <el-icon><Money /></el-icon> Historial de Pagos
        </h4>
        <el-table :data="verData.pagos" size="small" border stripe max-height="300px">
          <el-table-column prop="fecha" label="Fecha" width="95" />
          <el-table-column prop="tipo" label="Tipo" width="80">
            <template #default="p">
              <el-tag :type="p.row.tipo === 'stand' ? 'primary' : 'warning'" size="small">
                {{ p.row.tipo === 'stand' ? 'Stand' : 'Mob.' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="Monto ($)" align="right" width="95">
            <template #default="p"><b>${{ parseFloat(p.row.cantidad).toFixed(2) }}</b></template>
          </el-table-column>
          <el-table-column label="Monto (Bs)" align="right" width="120">
             <template #default="p">
               <div style="font-size: 11px;">Bs {{ parseFloat(p.row.monto_bs || 0).toFixed(2) }}</div>
               <div style="font-size: 10px; color: #909399;">(Tasa: {{ p.row.tasa_bcv }})</div>
             </template>
          </el-table-column>
          <el-table-column prop="numero_referencia" label="Ref" width="90" show-overflow-tooltip />
          <el-table-column label="Banco" width="100" align="center">
            <template #default="p">
              <el-switch
                v-model="p.row.conciliado"
                active-text="✅"
                inactive-text="⏳"
                active-color="#67C23A"
                inactive-color="#E6A23C"
                @change="toggleConciliacion(p.row)"
                :loading="p.row._toggling"
                size="small"
              />
            </template>
          </el-table-column>
        </el-table>
        
        <div style="margin-top: 20px; text-align: right; font-weight: bold; font-size: 16px; border-top: 1px solid #ebeef5; padding-top: 15px; display: flex; justify-content: space-between; align-items: center;">
           <el-button type="success" @click="printReceipt" icon="Printer">Imprimir Comprobante</el-button>
           <div>
             Total General Pagado: 
             <span style="color: var(--sakura-purple); margin-left: 10px;">
               ${{ (calcularPagado(verData, 'stand') + calcularPagado(verData, 'mobiliario')).toFixed(2) }}
             </span>
           </div>
        </div>
      </div>
    </el-dialog>

    <!-- Plantilla Imprimible (Oculta) -->
    <div id="printable-receipt" v-if="verData" class="print-only">
      <div class="receipt-header">
        <div class="logo">🌸 Sakura Fest</div>
        <div class="title">COMPROBANTE DE RESERVACIÓN</div>
        <div class="receipt-id">ID: #{{ verData.id_reservacion }}</div>
      </div>

      <div class="receipt-section">
        <div class="section-title">Datos del Emprendedor</div>
        <div class="data-row"><span>Tienda:</span> <b>{{ verData.usuario?.nombre_tienda || verData.usuario?.nombre }}</b></div>
        <div class="data-row" v-if="verData.usuario2"><span>Socio:</span> <b>{{ verData.usuario2?.nombre_tienda || verData.usuario2?.nombre }}</b></div>
        <div class="data-row"><span>Fecha Emisión:</span> {{ new Date().toLocaleDateString() }}</div>
      </div>

      <div class="receipt-section">
        <div class="section-title">Detalle de Stands</div>
        <table class="receipt-table">
          <thead>
            <tr>
              <th>Stand</th>
              <th style="text-align: right;">Precio</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in verData.detalles" :key="s.id_detalle">
              <td>{{ s.stand?.name }}</td>
              <td style="text-align: right;">${{ parseFloat(s.stand?.precio || 0).toFixed(2) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td><b>TOTAL STANDS</b></td>
              <td style="text-align: right;"><b>${{ calcularTotalStands(verData).toFixed(2) }}</b></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="receipt-section">
        <div class="section-title">Historial de Pagos</div>
        <table class="receipt-table">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Tipo</th>
              <th>Ref</th>
              <th style="text-align: right;">Monto</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in verData.pagos" :key="p.id_pago">
              <td>{{ p.fecha }}</td>
              <td>{{ p.tipo === 'stand' ? 'Stand' : 'Mob.' }}</td>
              <td>{{ p.numero_referencia || '-' }}</td>
              <td style="text-align: right;">${{ parseFloat(p.cantidad).toFixed(2) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="receipt-summary">
        <div class="summary-line"><span>Mobiliario Pagado:</span> <span>${{ (verData.monto_mobiliario || 0).toFixed(2) }}</span></div>
        <div class="summary-line"><span>Total Pagado Stand:</span> <span>${{ calcularPagado(verData, 'stand').toFixed(2) }}</span></div>
        <div class="summary-line total">
          <span>TOTAL GENERAL PAGADO:</span> 
          <span>${{ (calcularPagado(verData, 'stand') + (verData.monto_mobiliario || 0)).toFixed(2) }}</span>
        </div>
      </div>

      <div class="receipt-footer">
        <p>Gracias por ser parte de la familia Sakura Fest 🌸</p>
        <p style="font-size: 10px; color: #777;">Este documento es un comprobante digital de pago y asignación.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import axios from 'axios'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Calendar, Delete, Plus, Loading, Box, View, Location, Money, Printer } from '@element-plus/icons-vue'

const reservaciones = ref([])
const usuarios = ref([])
const standsDisponibles = ref([])
const allStands = ref([])
const eventos = ref([])
const filterEvento = ref(null)

const loading = ref(false)
const saving = ref(false)
const dialogVisible = ref(false)
const pagoDialogVisible = ref(false)
const mobDialogVisible = ref(false)
const verDialogVisible = ref(false)
const currentRes = ref(null)
const verData = ref(null)
const tasaBcv = ref(0.00)

const formRef = ref(null)
const form = ref({
  usuarios_id: '',
  usuario_2_id: null,
  stands: [],
  descripcion: '',
  monto_pago: 0.00,
  monto_bs: 0.00,
  fecha_pago: new Date().toISOString().split('T')[0],
  tasa_bcv: 0.00,
  referencia_pago: ''
})

const pagoForm = ref({
  reservacion_id: null,
  cantidad: 0.00,
  monto_bs: 0.00,
  fecha_pago: new Date().toISOString().split('T')[0],
  tasa_bcv: 0.00,
  numero_referencia: ''
})

const mobPagoForm = ref({
  reservacion_id: null,
  cantidad: 0.00,
  monto_bs: 0.00,
  fecha_pago: new Date().toISOString().split('T')[0],
  tasa_bcv: 0.00,
  numero_referencia: ''
})

const rules = {
  usuarios_id: [{ required: true, message: 'El emprendedor es obligatorio', trigger: 'change' }],
  stands: [{ required: true, message: 'Seleccione al menos 1 stand', type: 'array', min: 1, trigger: 'change' }]
}

// Helpers Logica
const calcularPagado = (res, tipo) => {
  if (!res || !res.pagos) return 0.00
  return res.pagos.filter(p => p.tipo === tipo).reduce((t, p) => t + parseFloat(p.cantidad || 0), 0.00)
}

const calcularTotalStands = (res) => {
  if (!res || !res.detalles) return 0.00
  return res.detalles.reduce((t, d) => t + parseFloat(d.stand?.precio || 0), 0.00)
}

// Estados
const formatStatus = (status) => {
  if (status === 'confirmada') return 'SOLVENTE'
  if (status === 'abonada') return 'ABONADO'
  return 'PENDIENTE'
}

const getStatusColor = (status) => {
  if (status === 'confirmada') return 'success'
  if (status === 'abonada') return 'warning'
  return 'info'
}

const isSolventeStand = (res) => (calcularTotalStands(res) - calcularPagado(res, 'stand')) <= 0

const currentStandRemainingDebt = computed(() => {
  if (!currentRes.value) return 0.00
  const total = calcularTotalStands(currentRes.value)
  const paid = calcularPagado(currentRes.value, 'stand')
  return Math.max(0, total - paid)
})

// Validaciones Hardened (OnBlur)
const validatePaymentAmount = () => {
  const remaining = currentStandRemainingDebt.value
  if (pagoForm.value.cantidad > (remaining + 0.001)) {
    ElMessage.warning(`Monto excedido. Ajustado a deuda: $${remaining.toFixed(2)}`)
    pagoForm.value.cantidad = parseFloat(remaining.toFixed(2))
    recalculateBs()
  }
}

const validateFormPayment = () => {
  const total = totalStandsUsd.value
  if (form.value.monto_pago > (total + 0.001)) {
    ElMessage.warning(`El anticipo no puede superar el stand: $${total.toFixed(2)}`)
    form.value.monto_pago = parseFloat(total.toFixed(2))
    recalculateFormBs()
  }
}

// Calculadoras
const recalculateUsd = () => { if (pagoForm.value.tasa_bcv > 0) pagoForm.value.cantidad = parseFloat((pagoForm.value.monto_bs / pagoForm.value.tasa_bcv).toFixed(2)) }
const recalculateBs = () => { if (pagoForm.value.tasa_bcv > 0) pagoForm.value.monto_bs = parseFloat((pagoForm.value.cantidad * pagoForm.value.tasa_bcv).toFixed(2)) }
const recalculateMobUsd = () => { if (mobPagoForm.value.tasa_bcv > 0) mobPagoForm.value.cantidad = parseFloat((mobPagoForm.value.monto_bs / mobPagoForm.value.tasa_bcv).toFixed(2)) }
const recalculateMobBs = () => { if (mobPagoForm.value.tasa_bcv > 0) mobPagoForm.value.monto_bs = parseFloat((mobPagoForm.value.cantidad * mobPagoForm.value.tasa_bcv).toFixed(2)) }
const recalculateFormUsd = () => { if (form.value.tasa_bcv > 0) form.value.monto_pago = parseFloat((form.value.monto_bs / form.value.tasa_bcv).toFixed(2)) }
const recalculateFormBs = () => { if (form.value.tasa_bcv > 0) form.value.monto_bs = parseFloat((form.value.monto_pago * form.value.tasa_bcv).toFixed(2)) }

// Sincronización
const fetchReservaciones = async () => {
  if (!filterEvento.value) return reservaciones.value = []
  loading.value = true
  try {
    const res = await axios.get(`/api/reservaciones?evento_id=${filterEvento.value}`)
    reservaciones.value = res.data
    standsDisponibles.value = allStands.value.filter(s => s.eventos_id == filterEvento.value && s.status === 'disponible')
    syncCurrentRes()
  } catch(e) {} finally { loading.value = false }
}

const syncCurrentRes = () => {
  if (currentRes.value) {
    const found = reservaciones.value.find(r => r.id_reservacion === currentRes.value.id_reservacion)
    if (found) currentRes.value = found
  }
}

const fetchInitialData = async () => {
  const [resUsr, resStands, resEvent, resTasa] = await Promise.all([
    axios.get('/api/usuarios'),
    axios.get('/api/stands'),
    axios.get('/api/eventos'),
    axios.get('/api/tasa-bcv')
  ])
  usuarios.value = resUsr.data.filter(u => u.role_id === 2)
  allStands.value = resStands.data.map(s => ({ ...s, precio: parseFloat(s.precio) }))
  eventos.value = resEvent.data
  tasaBcv.value = parseFloat(resTasa.data.tasa) || 0.00
}

const totalStandsUsd = computed(() => {
  return form.value.stands.reduce((t, id) => t + (allStands.value.find(s => s.id_stands === id)?.precio || 0.00), 0.00)
})

const handleDateChange = (val) => fetchHistoryRate(val, pagoForm.value, recalculateUsd)
const handleMobDateChange = (val) => fetchHistoryRate(val, mobPagoForm.value, recalculateMobUsd)
const handleFormDateChange = (val) => fetchHistoryRate(val, form.value, recalculateFormUsd)

const fetchHistoryRate = async (fecha, targetForm, callback) => {
  if (!fecha) return
  if (fecha === new Date().toISOString().split('T')[0]) {
    targetForm.tasa_bcv = tasaBcv.value
    callback()
    return
  }
  try {
    const res = await axios.get(`/api/tasa-bcv/historico?fecha=${fecha}`)
    targetForm.tasa_bcv = parseFloat(res.data.tasa)
    callback()
  } catch (e) { targetForm.tasa_bcv = tasaBcv.value; callback() }
}

// Handlers
const selectedUser = computed(() => {
  if (!form.value.usuarios_id) return null
  return usuarios.value.find(u => u.id === form.value.usuarios_id)
})

const getStatusColorCRM = (status) => {
  if (status === 'Documentos OK') return 'success'
  if (status === 'Bloqueado') return 'danger'
  return 'warning'
}

const handleUsuarioChange = (val) => {
  const user = usuarios.value.find(u => u.id === val)
  if (user && user.estado_registro === 'Bloqueado') {
    ElMessage.error('Este emprendedor está BLOQUEADO. No se le puede asignar stands.')
  }
}

const openDialog = () => {
  form.value = { 
    usuarios_id: '', usuario_2_id: null, stands: [],
    monto_pago: 0.00, monto_bs: 0.00, tasa_bcv: tasaBcv.value,
    fecha_pago: new Date().toISOString().split('T')[0], referencia_pago: ''
  }
  nextTick(() => { if (formRef.value) formRef.value.clearValidate() })
  dialogVisible.value = true
}

const saveReservacion = async () => {
  if (selectedUser.value?.estado_registro === 'Bloqueado') {
    return ElMessage.error('Operación abortada: El emprendedor principal está bloqueado por administración.')
  }
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    saving.value = true
    try {
      await axios.post('/api/reservaciones', { ...form.value, evento_id: filterEvento.value })
      ElMessage.success('Reservación creada')
      dialogVisible.value = false
      fetchReservaciones()
    } catch (e) { 
      ElMessage.error(e.response?.data?.message || 'Error al crear reservación') 
    }
    finally { saving.value = false }
  })
}

const openPagoDialog = (res) => {
  currentRes.value = res
  pagoForm.value = { 
    reservacion_id: res.id_reservacion, tipo: 'stand', 
    cantidad: 0.00, monto_bs: 0.00, tasa_bcv: tasaBcv.value, 
    fecha_pago: new Date().toISOString().split('T')[0], numero_referencia: '' 
  }
  pagoDialogVisible.value = true
}

const openMobiliarioDialog = (res) => {
  currentRes.value = res
  mobPagoForm.value = { 
    reservacion_id: res.id_reservacion, tipo: 'mobiliario', 
    cantidad: 0.00, monto_bs: 0.00, tasa_bcv: tasaBcv.value, 
    fecha_pago: new Date().toISOString().split('T')[0], numero_referencia: '' 
  }
  mobDialogVisible.value = true
}

const openVerDialog = (res) => {
  verData.value = res
  verDialogVisible.value = true
}

const printReceipt = () => {
  window.print()
}

// === CONCILIACION BANCARIA ===
const hasConciliado = (res) => {
  return res?.pagos?.some(p => p.conciliado) || false
}

const toggleConciliacion = async (pago) => {
  pago._toggling = true
  try {
    await axios.patch(`/api/pagos/${pago.id_pagos}/conciliacion`)
    ElMessage.success(pago.conciliado ? 'Pago conciliado ✅' : 'Marcado como pendiente ⏳')
    await fetchReservaciones()
    // Refresh detail view if open
    if (verData.value) {
      const updated = reservaciones.value.find(r => r.id_reservacion === verData.value.id_reservacion)
      if (updated) verData.value = updated
    }
  } catch (e) {
    pago.conciliado = !pago.conciliado // Revert
    if (e.response && e.response.status === 422 && e.response.data.errors) {
      const msgs = Array.isArray(e.response.data.errors) 
        ? e.response.data.errors.join(' | ') 
        : Object.values(e.response.data.errors).flat().join(' | ')
      ElMessage.error(msgs || e.response.data.message)
    } else {
      ElMessage.error('Error al cambiar estado de conciliación')
    }
  } finally {
    pago._toggling = false
  }
}

const confirmDelete = (res) => {
  if (hasConciliado(res)) {
    return ElMessage.warning('No se puede eliminar: tiene pagos conciliados. Desconcilie primero.')
  }
  ElMessageBox.confirm(
    `¿Está seguro de eliminar la reservación de "${res.usuario?.nombre_tienda || res.usuario?.nombre}"? Esta acción liberará los stands asociados.`,
    'Confirmar Eliminación',
    {
      confirmButtonText: 'Eliminar',
      cancelButtonText: 'Cancelar',
      type: 'warning',
      confirmButtonClass: 'el-button--danger'
    }
  ).then(async () => {
    try {
      await axios.delete(`/api/reservaciones/${res.id_reservacion}`)
      ElMessage.success('Reservación eliminada correctamente')
      fetchReservaciones()
    } catch (e) {
      ElMessage.error('Error al eliminar reservación')
    }
  }).catch(() => {})
}

const savePago = async (tipo) => {
  const f = pagoForm.value
  validatePaymentAmount()
  if (f.cantidad <= 0.00) return ElMessage.warning('Monto inválido')
  saving.value = true
  try {
    await axios.post('/api/pagos', { ...f, fecha: f.fecha_pago })
    ElMessage.success(`Pago de ${tipo} registrado`)
    pagoDialogVisible.value = false
    await fetchReservaciones()
  } catch (e) { ElMessage.error('Error en pago') }
  finally { saving.value = false }
}

const savePagoMobiliario = async () => {
  const f = mobPagoForm.value
  if (f.cantidad <= 0.00) return ElMessage.warning('Ingrese un monto válido')
  saving.value = true
  try {
    await axios.post(`/api/reservaciones/${currentRes.value.id_reservacion}/pago-mobiliario`, { ...f, fecha: f.fecha_pago })
    ElMessage.success('Mobiliario pagado y asignado')
    mobDialogVisible.value = false
    await fetchReservaciones()
  } catch (e) { ElMessage.error('Error al registrar pago de mobiliario') }
  finally { saving.value = false }
}

const getGlobalSummaries = (param) => {
  const { columns, data } = param
  const sums = []
  columns.forEach((col, i) => {
    if (i === 0) return sums[i] = 'TOTALES'
    if (col.label === 'Mobiliario Pagado') {
       sums[i] = `$${data.reduce((p, c) => p + parseFloat(c.monto_mobiliario || 0), 0).toFixed(2)}`
    }
    if (!sums[i]) sums[i] = ''
  })
  return sums
}

onMounted(() => { fetchInitialData() })
</script>

<style scoped>
.print-only {
  display: none;
}

@media print {
  body * {
    visibility: hidden;
  }
  #printable-receipt, #printable-receipt * {
    visibility: visible;
  }
  #printable-receipt {
    display: block !important;
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    padding: 40px;
    background: white;
    font-family: 'Outfit', sans-serif !important;
    color: #333;
  }
  .receipt-header {
    text-align: center;
    margin-bottom: 30px;
    border-bottom: 3px solid #FFB7C5;
    padding-bottom: 15px;
  }
  .logo {
    font-size: 28px;
    font-weight: bold;
    color: #FFB7C5;
  }
  .receipt-header .title {
    font-size: 18px;
    color: #555;
    margin-top: 5px;
  }
  .receipt-id {
    font-size: 14px;
    color: #999;
  }
  .receipt-section {
    margin-bottom: 25px;
  }
  .section-title {
    font-weight: bold;
    text-transform: uppercase;
    font-size: 13px;
    color: #9d4edd;
    border-bottom: 1px solid #eee;
    margin-bottom: 10px;
    padding-bottom: 3px;
  }
  .data-row {
    margin-bottom: 5px;
    display: flex;
    justify-content: space-between;
  }
  .receipt-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
  }
  .receipt-table th, .receipt-table td {
    padding: 8px;
    border-bottom: 1px solid #f0f0f0;
    text-align: left;
    font-size: 13px;
  }
  .receipt-table th {
    background: #fafafa;
    color: #666;
  }
  .receipt-summary {
    margin-top: 20px;
    padding: 15px;
    background: #fff9fb;
    border-radius: 8px;
  }
  .summary-line {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
    font-size: 14px;
  }
  .summary-line.total {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 2px solid #FFB7C5;
    font-weight: bold;
    font-size: 18px;
    color: #9d4edd;
  }
  .receipt-footer {
    margin-top: 50px;
    text-align: center;
    font-size: 12px;
    color: #999;
  }
}
</style>
