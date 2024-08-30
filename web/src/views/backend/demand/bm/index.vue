<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['refresh', 'add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('demand.bm.quick Search Fields') })"
        >

        <!-- <template #default  >
            <el-button v-auth="'audit'" style="margin-left: 12px;" v-blur :disabled="baTable.table.selection!.length > 0 ? false:true" class="table-header-operate" type="success" @click="accountAuditFn(1)">
                <Icon color="#ffffff" name="el-icon-RefreshRight" />
                <span class="table-header-operate-text">{{t('demand.bm.status')}}</span>
            </el-button>
            <el-button v-auth="'dispose'" v-blur :disabled="baTable.table.selection!.length > 0 ? false:true" class="table-header-operate" type="success" @click="accountAuditFn(2)">
                <Icon color="#ffffff" name="el-icon-RefreshRight" />
                <span class="table-header-operate-text">{{t('demand.bm.dispose_type')}}</span>
            </el-button>
        </template> -->
    
    
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


        <div class="addPurchasingManagement-dialog">
            <el-dialog title="处理" :z-index="1000" v-model="addPurchasingManagementDialog.show2" @close="DialogCloseFn(2)" center destroy-on-close draggable>
                <div class="dialog-body">

                    <!-- table列表 -->
                    <div class="tableList">
                        <el-select v-model="addPurchasingManagementDialog.disposeStatus"  style="width: 200px" placeholder="状态">
                            <el-option v-for="item in addPurchasingManagementDialog.disposeStatusList" :key="item.id" :label="item.name" :value="item.id" />
                        </el-select>
                    </div>

                </div>

                <template #footer>
                    <div class="dialog-footer">
                        <el-button type="primary" @click="confirmAddCommodityFn(2)" >确认</el-button>
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
import { bmAudit ,bmDisposeStatus} from '/@/api/backend/index.ts';

defineOptions({
    name: 'demand/bm',
})

const { t } = useI18n()
const tableRef = ref()
const optButtons: OptButton[] = defaultOptButtons(['edit', 'delete'])

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/demand.Bm/'),
    {
        pk: 'id',
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: t('demand.bm.id'), prop: 'uuid', align: 'center', width: 100, operator: 'eq', sortable: 'custom' },
            { label: t('demand.bm.affiliation_bm'), prop: 'accountrequestProposal.affiliation_bm', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            { label: t('demand.bm.account_name'), prop: 'account_name', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false ,width:170},
            { label: t('demand.bm.account_id'), prop: 'account_id', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false ,render: 'tags'},
            { label: t('demand.bm.bm'), prop: 'bm', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false ,width:150},
            { label: t('demand.bm.demand_type'), prop: 'demand_type', align: 'center', render: 'tag', operator: 'eq', sortable: false, replaceValue: { '1': t('demand.bm.demand_type 1'), '2': t('demand.bm.demand_type 2'), '3': t('demand.bm.demand_type 3'), '4': t('demand.bm.demand_type 4') } },
            { label: t('demand.bm.status'), prop: 'status', align: 'center', render: 'customTemplate', operator: 'eq', sortable: false, comSearchRender:'select',
            replaceValue: { '0': t('demand.bm.status 0'), '1': t('demand.bm.status 1'), '2': t('demand.bm.status 2') } ,
                customTemplate: (row: TableRow, field: TableColumn, value: any, column, index: number) => {                    
                    if(value == 0){
                        return '<span style="background-color:#469ff7;padding:4px 9px;color:#FFF;border-radius:4px">'+t('demand.bm.status 0')+'</span>';
                    }else if(value == 1){
                        return '<span style="background-color:#67c23a;padding:4px 9px;color:#FFF;border-radius:4px">'+t('demand.bm.status 1')+'</span>';
                    }else if(value == 2){
                        return '<span style="background-color:#ff5151;padding:4px 9px;color:#FFF;border-radius:4px">'+t('demand.bm.status 2')+'</span>';
                    }
                    return '<span>' + value + '</span>';
                }
            },
            { label: t('demand.bm.dispose_type'), prop: 'dispose_type', align: 'center', render: 'customTemplate', operator: 'eq', sortable: false, comSearchRender:'select',
            replaceValue: { '0': t('demand.bm.dispose_type 0'), '1': t('demand.bm.dispose_type 1'), '2': t('demand.bm.dispose_type 2') } ,
                customTemplate: (row: TableRow, field: TableColumn, value: any, column, index: number) => {                    
                    if(value == 0){
                        return '<span style="background-color:#469ff7;padding:4px 9px;color:#FFF;border-radius:4px">'+t('demand.bm.dispose_type 0')+'</span>';
                    }else if(value == 1){
                        return '<span style="background-color:#67c23a;padding:4px 9px;color:#FFF;border-radius:4px">'+t('demand.bm.dispose_type 1')+'</span>';
                    }else if(value == 2){
                        return '<span style="background-color:#ff5151;padding:4px 9px;color:#FFF;border-radius:4px">'+t('demand.bm.dispose_type 2')+'</span>';
                    }
                    return '<span>' + value + '</span>';
                }
        
            },
            { label: t('demand.bm.create_time'), prop: 'create_time', align: 'center', render: 'datetime', operator: 'RANGE', sortable: 'custom', width: 160, timeFormat: 'yyyy-mm-dd hh:MM' },
            { label: t('demand.bm.update_time'), prop: 'update_time', align: 'center', render: 'datetime', operator: 'RANGE', sortable: 'custom', width: 160, timeFormat: 'yyyy-mm-dd hh:MM' },
            { label: t('Operate'), align: 'center', width: 100, render: 'buttons', buttons: optButtons, operator: false },
        ],
        dblClickNotEditColumn: ['all'],
        filter: {
            limit:20
        }
    },
    {
        defaultItems: { demand_type: '1' },
    }
)



// 添加弹窗组件************************************************************************************************************
interface AddPurchasingManagementDialog {
    show: boolean
    purchasingId: string | number
    tableData: Array<any>
    adminList: Array<any>
    admin_id:number | string
    status:number
    statusList:Array<any>
    accountId:string
    accountIds:Array<any>
    dispose_status:number
    show2:boolean
    disposeStatusList:Array<any>
    disposeStatus:number
}
const addPurchasingManagementDialog: AddPurchasingManagementDialog = reactive({
    show: false,
    purchasingId: '',
    tableData: [],
    adminList: [],
    // admin_id:1,
    // status:0,
    statusList:[]
})

const accountAuditFn = (type:number = 1) => {
    if(type == 2){
        addPurchasingManagementDialog.show2 = true
        addPurchasingManagementDialog.disposeStatusList = [
            {
                id: 1, name: '处理完成'
            },
            {
                id: 2, name: '异常'
            }
        ]
    }else{
        addPurchasingManagementDialog.show = true
        addPurchasingManagementDialog.statusList = [
            {
                id: 1, name: '审核通过'
            },
            {
                id: 2, name: '审核拒绝'
            }
        ]
    }
}

const DialogCloseFn = (type:number = 1) => {
    addPurchasingManagementDialog.show = false
    addPurchasingManagementDialog.show2 = false
}

//审核
const confirmAddCommodityFn = (type:number = 1) => {

    if(type == 2){
        let confirmFn = async () => {
            let postData = {
                ids:baTable.getSelectionIds(),
                status:addPurchasingManagementDialog.disposeStatus,           
            }
            console.log(postData)
            let res: anyObj = await bmDisposeStatus(postData)
            
            tips('处理成功', 'success')
            addPurchasingManagementDialog.show2 = false
            baTable.onTableHeaderAction('refresh',[])
        }
        confirmFn()
    }else{
        let confirmFn = async () => {
            let postData = {
                ids:baTable.getSelectionIds(),
                status:addPurchasingManagementDialog.status,           
            }
            console.log(postData)
            let res: anyObj = await bmAudit(postData)
            
            tips('分配成功', 'success')
            addPurchasingManagementDialog.show = false
            baTable.onTableHeaderAction('refresh',[])
        }
        confirmFn()
    }
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
