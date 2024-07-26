<template>
    <!-- 对话框表单 -->
    <!-- 建议使用 Prettier 格式化代码 -->
    <!-- el-form 内可以混用 el-form-item、FormItem、ba-input 等输入组件 -->
    <el-dialog
        class="ba-operate-dialog"
        :close-on-click-modal="false"
        :model-value="['Add', 'Edit'].includes(baTable.form.operate!)"
        @close="baTable.toggleForm"
        width="50%"
    >
        <template #header>
            <div class="title" v-drag="['.ba-operate-dialog', '.el-dialog__header']" v-zoom="'.ba-operate-dialog'">
                {{ baTable.form.operate ? t(baTable.form.operate) : '' }}
            </div>
        </template>
        <el-scrollbar v-loading="baTable.form.loading" class="ba-table-form-scrollbar">
            <div
                class="ba-operate-form"
                :class="'ba-' + baTable.form.operate + '-form'"
                :style="config.layout.shrink ? '':'width: calc(100% - ' + baTable.form.labelWidth! / 2 + 'px)'"
            >
                <el-form
                    v-if="!baTable.form.loading"
                    ref="formRef"
                    @submit.prevent=""
                    @keyup.enter="baTable.onSubmit(formRef)"
                    :model="baTable.form.items"
                    :label-position="config.layout.shrink ? 'top' : 'right'"
                    :label-width="baTable.form.labelWidth + 'px'"
                    :rules="rules"
                >
                    <FormItem :label="t('demand.recharge.account_id')" type="string" v-model="baTable.form.items!.account_id" prop="account_id" :placeholder="t('Please input field', { field: t('demand.recharge.account_id') })" />
                    <FormItem :label="t('demand.recharge.type')" type="select" v-model="baTable.form.items!.type" prop="type" :input-attr="{ content: { '1': t('demand.recharge.type 1'), '2': t('demand.recharge.type 2'), '3': t('demand.recharge.type 3') } }" :placeholder="t('Please select field', { field: t('demand.recharge.type') })" />
                    <FormItem :label="t('demand.recharge.number')" type="number" prop="number" :input-attr="{ step: 1 }" v-model.number="baTable.form.items!.number" :placeholder="t('Please input field', { field: t('demand.recharge.number') })" />
                </el-form>
            </div>
        </el-scrollbar>
        <template #footer>
            <div :style="'width: calc(100% - ' + baTable.form.labelWidth! / 1.8 + 'px)'">
                <el-button @click="baTable.toggleForm()">{{ t('Cancel') }}</el-button>
                <el-button v-blur :loading="baTable.form.submitLoading" @click="baTable.onSubmit(formRef)" type="primary">
                    {{ baTable.form.operateIds && baTable.form.operateIds.length > 1 ? t('Save and edit next item') : t('Save') }}
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import type { FormInstance, FormItemRule } from 'element-plus'
import { inject, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import FormItem from '/@/components/formItem/index.vue'
import { useConfig } from '/@/stores/config'
import type baTableClass from '/@/utils/baTable'
import { buildValidatorData } from '/@/utils/validate'

const config = useConfig()
const formRef = ref<FormInstance>()
const baTable = inject('baTable') as baTableClass

const { t } = useI18n()

const rules: Partial<Record<string, FormItemRule[]>> = reactive({
    account_id: [buildValidatorData({ name: 'required', title: t('demand.recharge.account_id') })],
    type: [buildValidatorData({ name: 'required', title: t('demand.recharge.type') })],
    number: [buildValidatorData({ name: 'float', title: t('demand.recharge.number') })],
    create_time: [buildValidatorData({ name: 'date', title: t('demand.recharge.create_time') })],
    update_time: [buildValidatorData({ name: 'date', title: t('demand.recharge.update_time') })],
})
</script>

<style scoped lang="scss"></style>
