<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('account.quick Search Fields') })"
        >
        <template #refreshPrepend>
            <!-- 刷新按钮前插槽内容 -->
        </template>

        <template #default  >
            <el-button v-auth="'audit'" style="margin-left: 12px;" v-blur :disabled="baTable.table.selection!.length > 0 ? false:true" class="table-header-operate" type="success" @click="accountAuditFn(1)">
                <Icon color="#ffffff" name="el-icon-RefreshRight" />
                <span class="table-header-operate-text">审核</span>
            </el-button>
            <el-button v-auth="'dispose'" v-blur :disabled="baTable.table.selection!.length > 0 ? false:true" class="table-header-operate" type="success" @click="accountAuditFn(2)">
                <Icon color="#ffffff" name="el-icon-RefreshRight" />
                <span class="table-header-operate-text">处理</span>
            </el-button>

            <span style="font-weight:bold;margin-left: 10px;"> 当日可提交新户需求数：</span><span>{{addPurchasingManagementDialog.isAccount != 1?'不能提交开户需求,请找管理员申请开户数量!':addPurchasingManagementDialog.residueAccountNumber}}</span>
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
                        <!-- <el-select v-model="addPurchasingManagementDialog.admin_id" :disabled="addPurchasingManagementDialog.status != 1" style="width: 200px" placeholder="渠道">
                            <el-option v-for="item in addPurchasingManagementDialog.adminList" :key="item.id" :label="item.name" :value="item.id" />
                        </el-select> -->
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
import { onMounted, provide, ref, reactive,watchEffect  } from 'vue'
import { useI18n } from 'vue-i18n'
import PopupForm from './popupForm.vue'
import { baTableApi } from '/@/api/common'
import { defaultOptButtons} from '/@/components/table'
import { requestThenFn, tips } from '/@/utils/common';
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'
import { getAdminList ,getAccountAudit,getAccountDisposeStatus,getAccountNumber} from '/@/api/backend/index.ts';
import { number } from 'echarts'

defineOptions({
    name: 'account',
})

const { t } = useI18n()
const tableRef = ref()
const optButtons: OptButton[] = defaultOptButtons(['edit', 'delete'])

/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/Account/'),
    {
        pk: 'id',
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: t('account.id'), prop: 'uuid', align: 'center', width: 100, operator: 'eq', sortable: 'custom' },
            { label: t('account.admin__username'), prop: 'admin.nickname', align: 'center', operatorPlaceholder: t('Fuzzy query'), render: 'tags', operator: 'LIKE' ,width:100},
            { label: t('account.name'), prop: 'name', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            { label: t('account.account_id'), prop: 'account_id', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false ,render: 'tags'},
            { label: t('account.time_zone'), prop: 'time_zone', align: 'center', operator: false, sortable: false ,render: 'customTemplate',width:90,
                customTemplate: (row: TableRow, field: TableColumn, value: any, column, index: number) => {
                    value = ref(value.replace('GMT ', '')).value;
                    let color = getTimeZoneColor(value)
                    return '<span style="border: 2px solid '+color+';border-radius:9px;display:block;">'+value+'</span>';
                }
            },
            { label: t('account.bm'), prop: 'bm', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            // { label: t('account.type'), prop: 'type', align: 'center', render: 'tag', operator: 'eq', sortable: false, replaceValue: { '1': t('account.type 1'), '2': t('account.type 2'), '3': t('account.type 3'), '4': t('account.type 4'), '5': t('account.type 5') } },
            { label: t('account.dispose_status'), prop: 'dispose_status', align: 'center', render: 'customTemplate', operator: 'eq', sortable: false,comSearchRender:'select',
             replaceValue: { '0': t('account.dispose_status 0'), '1': t('account.dispose_status 1') } ,
                customTemplate: (row: TableRow, field: TableColumn, value: any, column, index: number) => {
                    if(value == 0){
                        return '<span style="background-color:#e6a23c;padding:4px 9px;color:#FFF;border-radius:4px">'+t('account.status 0')+'</span>';
                    }else if(value == 1){
                        return '<span style="background-color:#67c23a;padding:4px 9px;color:#FFF;border-radius:4px">'+t('account.status 1')+'</span>';
                    }
                    return '<span>' + value + '</span>';
                }
        
        
            },
            { label: t('account.status'), prop: 'status', align: 'center', render: 'customTemplate', operator: 'eq', sortable: false, comSearchRender:'select',
                replaceValue: { '0': t('account.status 0'), '1': t('account.status 1'), '2': t('account.status 2'), '3': t('account.status 3'), '4': t('account.status 4'), '5': t('account.status 5') } ,
                customTemplate: (row: TableRow, field: TableColumn, value: any, column, index: number) => {
                    if(value == 0){
                        return '<span style="background-color:#d9ecff;padding:4px 9px;color:#FFF;border-radius:4px">'+t('account.status 0')+'</span>';
                    }else if(value == 1){
                        return '<span style="background-color:#409eff;padding:4px 9px;color:#FFF;border-radius:4px">'+t('account.status 1')+'</span>';
                    }else if(value == 2){
                        return '<span style="background-color:#ff5151;padding:4px 9px;color:#FFF;border-radius:4px">'+t('account.status 2')+'</span>';
                    }else if(value == 3){
                        return '<span style="background-color:#e6a23c;padding:4px 9px;color:#FFF;border-radius:4px">'+t('account.status 3')+'</span>';
                    }else if(value == 4){
                        return '<span style="background-color:#67c23a;padding:4px 9px;color:#FFF;border-radius:4px">'+t('account.status 4')+'</span>';
                    }else if(value == 5){
                        return '<span style="background-color:#ff5151;padding:4px 9px;color:#FFF;border-radius:4px">'+t('account.status 5')+'</span>';
                    }
                    return '<span>' + value + '</span>';
                }
            },
            // { label: t('account.money'), prop: 'money', align: 'center', operator: 'RANGE', sortable: false },
            // { label: t('account.admin__username'), prop: 'admin.username', align: 'center', operatorPlaceholder: t('Fuzzy query'), render: 'tags', operator: 'LIKE' },
            { label: t('account.create_time'), prop: 'create_time', align: 'center', render: 'datetime', operator: 'RANGE', sortable: 'custom', width: 160, timeFormat: 'yyyy-mm-dd hh:MM' },
            { label: t('account.update_time'), prop: 'update_time', align: 'center', render: 'datetime', operator: 'RANGE', sortable: 'custom', width: 160, timeFormat: 'yyyy-mm-dd hh:MM' },
            { label: t('Operate'), align: 'center', width: 100, render: 'buttons', buttons: optButtons, operator: false },
        ],
        dblClickNotEditColumn: ['all'],
        filter: {
            limit:20
        }
    },
    {
        defaultItems: { time: null, time_zone: 0, type: '1', status: '0', dispose_status: '0', money: 0 },
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
    residueAccountNumber:number,
    isAccount:number
}
const addPurchasingManagementDialog: AddPurchasingManagementDialog = reactive({
    show: false,
    purchasingId: '',
    tableData: [],
    adminList: [],
    // admin_id:1,
    // status:0,
    statusList:[],
    residueAccountNumber:0
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
        getWarehouseZoneIndexFn()
    }
}

const DialogCloseFn = (type:number = 1) => {
    addPurchasingManagementDialog.show = false
    addPurchasingManagementDialog.show2 = false
}

const colorList: { [key: string]: string } = {
        '-12:00':'#ffffff',
        '-11:00':'#d3e3ff',
        '-10:00':'#d4f3ff',
        '-9:00':'#d5f3e4',
        '-8:00':'#ffdcdb',
        '-7:00':'#ffecdb',
        '-6:00':'#fff5cc',
        '-5:00':'#fbdbff',
        '-4:00':'#ffdbea',
        '-3:00':'#dcdfe4',
        '-2:00':'#adcbff',
        '-1:00':'#ade4ff',
        '+0:00':'#ace2c5',
        '+1:00':'#ffb5b3',
        '+2:00':'#ffcea3',
        '+3:00':'#ffea99',
        '+4:00':'#e7b4ff',
        '+5:00':'#ffb3dc',
        '+5:30':'#81868f',
        '+6:00':'#2972f4',
        '+7:00':'#45b076',
        '+8:00':'#de3c36',
        '+9:00':'#f88825',
        '+10:00':'#f5c400',
        '+11:00':'#9a38d7',
        '+12:00':'#dd4097',
    };

const getTimeZoneColor = (timeZone: string): string | undefined => {
  return colorList[timeZone] || '#ffffff';
}

watchEffect(() => {
    console.log(1)
});



const getWarehouseZoneIndexFn = async ()=>{
    //审核状态:0=待审核,1=审核通过,2=审核拒绝
    addPurchasingManagementDialog.statusList = [
        {
            id: 1, name: '审核通过'
        },
        {
            id: 2, name: '审核拒绝'
        }
    ]

    console.log(addPurchasingManagementDialog.adminList)
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
            let res: anyObj = await getAccountDisposeStatus(postData)
            
            tips('处理成功', 'success')
            addPurchasingManagementDialog.show2 = false
            baTable.onTableHeaderAction('refresh',[])
        }
        confirmFn()
    }else{
        let confirmFn = async () => {
            let postData = {
                ids:baTable.getSelectionIds(),
                admin_id:addPurchasingManagementDialog.admin_id,
                status:addPurchasingManagementDialog.status,           
            }
            console.log(postData)
            let res: anyObj = await getAccountAudit(postData)
            
            tips('分配成功', 'success')
            addPurchasingManagementDialog.show = false
            baTable.onTableHeaderAction('refresh',[])
        }
        confirmFn()
    }
    //confirmElMessageBox('确定要添加为采购单吗？', confirmFn)
}

const getAccountNumberFn = () =>{
    let confirmFn = async () => {
        let postData = {}
        let res: anyObj = await getAccountNumber(postData)
        console.log(res)
        addPurchasingManagementDialog.residueAccountNumber = res.data[0].residue_account_number
        addPurchasingManagementDialog.isAccount = res.data[0].is_account
    }
    confirmFn()
}

provide('baTable', baTable)

onMounted(() => {
    getAccountNumberFn()
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
