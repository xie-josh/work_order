<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['refresh', 'add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('addaccountrequest.sAgentBm.quick Search Fields') })"
        >


    
    
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
import { bmAudit ,bmDisposeStatus,getAdminList} from '/@/api/backend/index.ts';

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
            { label: t('addaccountrequest.sAgentBm.id'), prop: 'uuid', align: 'center', width: 100, operator: 'RANGE', sortable: 'custom' },
            { label: t('addaccountrequest.sAgentBm.affiliation_bm'), prop: 'accountrequestProposal.affiliation_bm', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            { label: t('addaccountrequest.sAgentBm.account_id'), prop: 'account_id', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false ,render: 'tags'},
            { label: t('addaccountrequest.sAgentBm.bm'), prop: 'bm', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            { label: t('addaccountrequest.sAgentBm.demand_type'), prop: 'demand_type', align: 'center', comSearchRender:'select', render: 'customTemplate', operator: 'eq', sortable: false,
             replaceValue: { '1': t('addaccountrequest.sAgentBm.demand_type 1'), '2': t('addaccountrequest.sAgentBm.demand_type 2') } ,
                customTemplate: (row: TableRow, field: TableColumn, value: any, column, index: number) => {
                    if(value == 1){
                        return '<span style="background-color:#67c23a;padding:4px 9px;color:#FFF;border-radius:4px">'+t('addaccountrequest.sAgentBm.demand_type 1')+'</span>';
                    }else if(value == 2){
                        return '<span style="background-color:#ff5151;padding:4px 9px;color:#FFF;border-radius:4px">'+t('addaccountrequest.sAgentBm.demand_type 2')+'</span>';
                    }
                    return '<span>' + value + '</span>';
                }
            },
            { label: t('addaccountrequest.sAgentBm.bm_permissions'), prop: 'bm_permissions', align: 'center', render: 'tag', operator: 'eq', sortable: false, replaceValue: { '1': t('addaccountrequest.sAgentBm.bm_permissions 1')} },
            { label: t('addaccountrequest.sAgentBm.dispose_type'), prop: 'dispose_type', align: 'center', render: 'tag', operator: 'eq', sortable: false, replaceValue: { '0': t('addaccountrequest.sAgentBm.dispose_type 0'), '1': t('addaccountrequest.sAgentBm.dispose_type 1'), '2': t('addaccountrequest.sAgentBm.dispose_type 2') } },



            // { label: '会员', prop: 'user_id', comSearchRender: 'remoteSelect', remote: {
            //     // 主键，下拉 value
            //     pk: 'id',
            //     // 字段，下拉 label
            //     field: 'username',
            //     // 远程接口URL
            //     // 比如想要获取 user(会员) 表的数据，后台`会员管理`控制器URL为`/index.php/admin/user.user/index`
            //     // 因为已经通过 CRUD 生成过`会员管理`功能，所以该URL地址可以从`/@/api/controllerUrls`导入使用，如下面的 userUser
            //     // 该URL地址通常等于对应后台管理功能的`查看`操作请求的URL
            //     remoteUrl: '/admin/auth.Admin/index',
            //     // 额外的请求参数
            //     params: {},
            // }},





            { label: t('addaccountrequest.sAgentBm.create_time'), prop: 'create_time', align: 'center', render: 'datetime', operator: 'RANGE', sortable: 'custom', width: 160, timeFormat: 'yyyy-mm-dd hh:MM' },
            { label: t('addaccountrequest.sAgentBm.update_time'), prop: 'update_time', align: 'center', render: 'datetime', operator: 'RANGE', sortable: 'custom', width: 160, timeFormat: 'yyyy-mm-dd hh:MM' },
        ],
        dblClickNotEditColumn: ['all'],
        filter: {
            limit:20,
            dispose_type:1
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
    baTable.mount()
    baTable.getIndex()?.then(() => {
        baTable.initSort()
        baTable.dragSort()
    })
})
</script>

<style scoped lang="scss"></style>
