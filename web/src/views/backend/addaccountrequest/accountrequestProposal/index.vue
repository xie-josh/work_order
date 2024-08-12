<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['refresh', 'add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('addaccountrequest.accountrequestProposal.quick Search Fields') })"
        >

        <template #default  >
            <el-button style="margin-left: 12px;" v-auth="'dispose'"  class="table-header-operate" type="success" @click="accountAuditFn()">
                <Icon color="#ffffff" name="el-icon-RefreshRight" />
                <span class="table-header-operate-text">处理</span>
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
            <el-dialog title="account ids" :z-index="1000" v-model="addPurchasingManagementDialog.show" @close="DialogCloseFn" center destroy-on-close draggable>
                <div class="dialog-body">

                    <div style="width: 200px;padding: 0 0 10px 0;">
                        <el-input v-model="addPurchasingManagementDialog.affiliation_bm" placeholder="归属 Bm"></el-input>
                    </div>

                    <div style="width: 200px;padding: 0 0 10px 0;">
                        <el-input v-model="addPurchasingManagementDialog.bm" placeholder="Bm"></el-input>
                    </div>

                    <div style="width: 200px;padding: 0 0 10px 0;">
                        <el-select v-model="addPurchasingManagementDialog.timeZone" placeholder="时区">
                            <el-option v-for="item in addPurchasingManagementDialog.timeZoneList" :key="item" :label="item" :value="item" />
                        </el-select>
                    </div>


                    <div style="width: 200px;padding: 0 0 10px 0;">
                        <el-select v-model="addPurchasingManagementDialog.adminId" style="width: 200px" placeholder="渠道">
                            <el-option v-for="item in addPurchasingManagementDialog.adminList" :key="item.id" :label="item.name" :value="item.id" />
                        </el-select>
                    </div>

                    <div>
                        <el-input type="textarea" :rows="20" v-model="addPurchasingManagementDialog.idsList" placeholder="账户，一行一个账户，账户不可重复"></el-input>
                    </div>

                </div>

                <template #footer>
                    <div class="dialog-footer">
                        <el-button type="primary" @click="confirmAddCommodityFn" >确认</el-button>
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
import { AccountrequestProposalAudit,getAdminList} from '/@/api/backend/index.ts';

defineOptions({
    name: 'addaccountrequest/accountrequestProposal',
})

const { t } = useI18n()
const tableRef = ref()
const optButtons: OptButton[] = defaultOptButtons(['edit', 'delete'])

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/addaccountrequest.AccountrequestProposal/'),
    {
        pk: 'id',
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: t('addaccountrequest.accountrequestProposal.id'), prop: 'id', align: 'center', width: 70, operator: 'RANGE', sortable: 'custom' },
            { label: t('addaccountrequest.accountrequestProposal.bm'), prop: 'bm', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            { label: t('addaccountrequest.accountrequestProposal.affiliation_bm'), prop: 'affiliation_bm', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            // { label: t('addaccountrequest.accountrequestProposal.admin__username'), prop: 'admin.username', align: 'center', operatorPlaceholder: t('Fuzzy query'), render: 'tags', operator: 'LIKE' },
            
            { label: t('addaccountrequest.accountrequestProposal.account_id'), prop: 'account_id', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false ,render: 'tags'},
            { label: t('addaccountrequest.accountrequestProposal.status'), prop: 'status', align: 'center',comSearchRender:'select', render: 'customTemplate', operator: 'eq', sortable: false, 
            replaceValue: { '0': t('addaccountrequest.accountrequestProposal.status 0'), '1': t('addaccountrequest.accountrequestProposal.status 1') } ,
                customTemplate: (row: TableRow, field: TableColumn, value: any, column, index: number) => {                    
                    if(value == 0){
                        return '<span style="background-color:#67c23a;padding:4px 9px;color:#FFF;border-radius:4px">'+t('addaccountrequest.accountrequestProposal.status 0')+'</span>';
                    }else if(value == 1){
                        return '<span style="background-color:#ff5151;padding:4px 9px;color:#FFF;border-radius:4px">'+t('addaccountrequest.accountrequestProposal.status 1')+'</span>';
                    }
                    return '<span>' + value + '</span>';
                }
            },
            
            { label: t('addaccountrequest.accountrequestProposal.admin_username'), prop: 'admin.nickname', align: 'center', operator: false, sortable: false },
            { label: t('addaccountrequest.accountrequestProposal.time_zone'), prop: 'time_zone', align: 'center', operator: false, sortable: false },
            { label: t('addaccountrequest.accountrequestProposal.affiliationAdmin_username'), prop: 'affiliationAdmin.nickname', align: 'center', operator: false, sortable: false },

            { label: t('addaccountrequest.accountrequestProposal.create_time'), prop: 'create_time', align: 'center', render: 'datetime', operator: 'RANGE', sortable: 'custom', width: 160, timeFormat: 'yyyy-mm-dd hh:MM' },
            { label: t('addaccountrequest.accountrequestProposal.update_time'), prop: 'update_time', align: 'center', render: 'datetime', operator: 'RANGE', sortable: 'custom', width: 160, timeFormat: 'yyyy-mm-dd hh:MM' },
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


// 添加审核************************************************************************************************************
interface AddPurchasingManagementDialog {
    show: boolean
    idsList:string
    bm:string
    affiliation_bm:string
    timeZone:string
    timeZoneList:Array<any>
    adminList:Array<any>
    adminId:number
}
const addPurchasingManagementDialog: AddPurchasingManagementDialog = reactive({
    show: false,
    timeZone:'',
    timeZoneList:[
        'GMT -12:00',
        'GMT -11:00',
        'GMT -10:00',
        'GMT -9:00',
        'GMT -8:00',
        'GMT -7:00',
        'GMT -6:00',
        'GMT -5:00',
        'GMT -4:00',
        'GMT -3:00',
        'GMT -2:00',
        'GMT -1:00',
        'GMT 0:00',
        'GMT +1:00',
        'GMT +2:00',
        'GMT +3:00',
        'GMT +4:00',
        'GMT +5:00',
        'GMT +5:30',
        'GMT +6:00',
        'GMT +7:00',
        'GMT +8:00',
        'GMT +9:00',
        'GMT +10:00',
        'GMT +11:00',
        'GMT +12:00',
        'GMT +13:00',
        'GMT +14:00',
    ]
})


const accountAuditFn = () => {
    addPurchasingManagementDialog.show = true
    getWarehouseZoneIndexFn()
}

const DialogCloseFn = () => {
    addPurchasingManagementDialog.show = false
}


const getWarehouseZoneIndexFn = async ()=>{
    // 仓库地区
    let postData = {
    }
    let res: anyObj = await getAdminList(postData)

    addPurchasingManagementDialog.adminList = res.data.list.map((item: anyObj) => {
        return { id: item.id, name: item.username }
    })
}

//审核
const confirmAddCommodityFn = () => {

    const processedIds = addPurchasingManagementDialog.idsList
        .split(/\r?\n/)
        .map((item) => item.replace(/\t/g, '').trim())
        .filter((line) => line.trim() !== '')
        processedIds.join(',');

    let confirmFn = async () => {
        let postData = {
            ids:processedIds,
            bm:addPurchasingManagementDialog.bm,
            affiliationBm:addPurchasingManagementDialog.affiliation_bm,            
            timeZone:addPurchasingManagementDialog.timeZone,
            adminId:addPurchasingManagementDialog.adminId,
        }
        let res: anyObj = await AccountrequestProposalAudit(postData)
        
        tips('success', 'success')
        addPurchasingManagementDialog.show = false
    }
    confirmFn()
    baTable.onTableHeaderAction('refresh',[])
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
