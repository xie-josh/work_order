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
                    <FormItem :label="t('demand.bm.account_id')" type="string" v-model="baTable.form.items!.account_id" prop="account_id" :placeholder="t('Please input field', { field: t('demand.bm.account_id') })" />
                    <FormItem v-if="baTable.form.items!.account_id" :label="t('demand.bm.demand_type')" type="select" v-model="baTable.form.items!.demand_type" prop="demand_type" :input-attr="{ content: { '1': t('demand.bm.demand_type 1'), '2': t('demand.bm.demand_type 2') }, onChange:citiesFn }" :placeholder="t('Please select field', { field: t('demand.bm.demand_type') })" />
                    <FormItem :label="t('demand.bm.bm')" type="string" v-model="baTable.form.items!.bm" prop="bm" :placeholder="t('Please input field', { field: t('demand.bm.bm') })" />


                    <el-checkbox
                        v-model="checkAll"
                        :indeterminate="isIndeterminate"
                        @change="handleCheckAllChange"
                        v-if="baTable.form.items!.demand_type == 2"
                    >
                        Check all(选择,如果没有请手动输入需要操作的BM)
                    </el-checkbox>

                    <el-checkbox-group
                    v-model="checkedCities"
                    @change="handleCheckedCitiesChange"
                    v-if="baTable.form.items!.demand_type == 2"
                    >
                    <el-checkbox v-for="city in cities" :key="city" :label="city" :value="city">
                    {{ city }}
                    </el-checkbox>
                    </el-checkbox-group>




                    <!-- <br/>
                    <br/>
                    <br/>
                    <span style="color: red;">选择如果没有请手动输入需要操作的BM</span> -->

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
import {getBmList} from '/@/api/backend/index.ts';

const config = useConfig()
const formRef = ref<FormInstance>()
const baTable = inject('baTable') as baTableClass

const { t } = useI18n()


const checkAll = ref(false)
const isIndeterminate = ref(true)
const checkedCities = ref()
const cities = ref()



const handleCheckAllChange = (val: boolean) => {
  //console.log('1cities',cities.value)
  checkedCities.value = val ? cities.value : []
  isIndeterminate.value = false
  baTable.form.items!.checkList = checkedCities.value
}
const handleCheckedCitiesChange = (value: string[]) => {
    console.log('2cities',cities.value)
  const checkedCount = value.length
  checkAll.value = checkedCount === cities.value.length
  isIndeterminate.value = checkedCount > 0 && checkedCount < cities.value.length
  baTable.form.items!.checkList = checkedCities.value
}

const citiesFn = () => {
    if(baTable.form.items!.account_id && baTable.form.items!.demand_type == 2){
        let confirmFn = async () => {
            let postData = {
                account_id:baTable.form.items!.account_id,
            }
            console.log(postData)
            let res: anyObj = await getBmList(postData)
            
            cities.value = res.data.bmList
            //baTable.onTableHeaderAction('refresh',[])
        }
        confirmFn()
    }

    //console.log(cities.value);
}

const rules: Partial<Record<string, FormItemRule[]>> = reactive({
    demand_type: [buildValidatorData({ name: 'required', title: t('demand.bm.demand_type') })],
    account_id: [buildValidatorData({ name: 'required', title: t('demand.bm.create_time') })],
    update_time: [buildValidatorData({ name: 'date', title: t('demand.bm.update_time') })],
})

interface AddPurchasingManagementDialog {
    checkList: Array<any>
}
const addPurchasingManagementDialog: AddPurchasingManagementDialog = reactive({
    checkList:[]
})






</script>

<style scoped lang="scss"></style>
