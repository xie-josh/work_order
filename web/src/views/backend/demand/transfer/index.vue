<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['refresh', 'add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('demand.transfer.quick Search Fields') })"
        >
        

        <template #default  >
            <el-button v-auth="'audit'" style="margin-left: 12px;" v-blur :disabled="baTable.table.selection!.length > 0 ? false:true" class="table-header-operate" type="success" @click="accountAuditFn(1)">
                <Icon color="#ffffff" name="el-icon-RefreshRight" />
                <span class="table-header-operate-text">{{t('demand.transfer.audit')}}</span>
            </el-button>
        </template>
    
    
        </TableHeader>

        <!-- 表格 -->
        <!-- 表格列有多种自定义渲染方式，比如自定义组件、具名插槽等，参见文档 -->
        <!-- 要使用 el-table 组件原有的属性，直接加在 Table 标签上即可 -->
        <Table ref="tableRef"></Table>

        <!-- 表单 -->
        <PopupForm />


        <div class="addPurchasingManagement-dialog">
            <el-dialog title="审核" :z-index="1000" v-model="addPurchasingManagementDialog.show" @close="DialogCloseFn(1)" center destroy-on-close draggable>
                <div class="dialog-body">

                    <!-- table列表 -->
                    <div class="tableList">
                        <el-select v-model="addPurchasingManagementDialog.status"  style="width: 200px" placeholder="状态">
                            <el-option v-for="item in addPurchasingManagementDialog.statusList" :key="item.id" :label="item.name" :value="item.id" />
                        </el-select>
                    </div>

                </div>

                <template #footer>
                    <div class="dialog-footer">
                        <el-button type="primary" @click="confirmAddCommodityFn(1)" >确认</el-button>
                        <el-button @click="DialogCloseFn">取消</el-button>
                    </div>
                </template>
            </el-dialog>
        </div>

    </div>
</template>

<script setup lang="ts">
import { onMounted, provide, ref,reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import PopupForm from './popupForm.vue'
import { baTableApi } from '/@/api/common'
import { defaultOptButtons } from '/@/components/table'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'
import { requestThenFn, tips } from '/@/utils/common';
import { transferStatus} from '/@/api/backend/index.ts';
import { TableColumnCtx } from 'element-plus'


defineOptions({
    name: 'demand/transfer',
})

const { t } = useI18n()
const tableRef = ref()
const optButtons: OptButton[] = defaultOptButtons(['edit', 'delete'])

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/demand.Transfer/'),
    {
        pk: 'id',
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: t('demand.transfer.id'), prop: 'id', align: 'center', width: 70, operator: 'RANGE', sortable: 'custom' },
            { label: t('demand.transfer.start_account_id'), prop: 'start_account_id', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            { label: t('demand.transfer.start_account_name'), prop: 'start_account_name', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            { label: t('demand.transfer.end_account_id'), prop: 'end_account_id', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            { label: t('demand.transfer.end_account_name'), prop: 'end_account_name', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            { label: t('demand.transfer.money'), prop: 'money', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            { label: t('demand.transfer.audit'), prop: 'audit', align: 'center', render: 'tag', operator: 'eq', sortable: false, replaceValue: { '0': t('demand.transfer.audit 0'), '1': t('demand.transfer.audit 1'), '2': t('demand.transfer.audit 2') } },

            // { label: t('demand.transfer.audit'), prop: 'audit', align: 'center', render: 'customTemplate', operator: 'eq', sortable: false,
            //     customTemplate: (row: TableRow, field: TableColumn, value: any, column: TableColumnCtx<TableRow>, index: number) => {
                    
            //         if(value == 0){
            //             return '<span style="background-color:#d9ecff;padding:4px 9px;color:#409eff;border-radius:4px">待审核</span>';
            //         }else if(value == 1){
            //             return '<span style="background-color:#b7ffa5;padding:4px 9px;color:#409eff;border-radius:4px">审核成功</span>';
            //         }else if(value == 2){
            //             return '<span style="background-color:#ff8888;padding:4px 9px;color:#ff0101;border-radius:4px">审核失败</span>';
            //         }
            //         return '<span>' + value + '</span>';
            //     }


            // },

            { label: t('demand.transfer.create_time'), prop: 'create_time', align: 'center', render: 'datetime', operator: 'RANGE', sortable: 'custom', width: 160, timeFormat: 'yyyy-mm-dd hh:MM:ss' },
            { label: t('demand.transfer.update_time'), prop: 'update_time', align: 'center', render: 'datetime', operator: 'RANGE', sortable: 'custom', width: 160, timeFormat: 'yyyy-mm-dd hh:MM:ss' },
            { label: t('Operate'), align: 'center', width: 100, render: 'buttons', buttons: optButtons, operator: false },
        ],
        dblClickNotEditColumn: ['all'],
        filter: {
            limit:20
        }
    },
    {
        defaultItems: {},
    }
)


// 添加弹窗组件************************************************************************************************************
interface AddPurchasingManagementDialog {
    show: boolean
    status:number
    statusList:Array<any>
}
const addPurchasingManagementDialog: AddPurchasingManagementDialog = reactive({
    show: false,
})


const accountAuditFn = (type:number = 1) => {
    addPurchasingManagementDialog.show = true
    addPurchasingManagementDialog.statusList = [
        {
            id: 1, name: '处理完成'
        },
        {
            id: 2, name: '异常'
        }
    ]
}

const DialogCloseFn = (type:number = 1) => {
    addPurchasingManagementDialog.show = false
}


const confirmAddCommodityFn = (type:number = 1) => {
    let confirmFn = async () => {
        let postData = {
            ids:baTable.getSelectionIds(),
            status:addPurchasingManagementDialog.status,           
        }
        console.log(postData)
        let res: anyObj = await transferStatus(postData)
        
        tips('分配成功', 'success')
        addPurchasingManagementDialog.show = false
        baTable.onTableHeaderAction('refresh',[])
    }
    confirmFn()
    //confirmElMessageBox('确定要添加为采购单吗？', confirmFn)
}

provide('baTable', baTable)

onMounted(() => {
    baTable.table.ref = tableRef.value
    baTable.table.showComSearch = true
    baTable.mount()
    baTable.getIndex()?.then(() => {
        baTable.initSort()
        baTable.dragSort()
    })
})
</script>

<style scoped lang="scss"></style>
