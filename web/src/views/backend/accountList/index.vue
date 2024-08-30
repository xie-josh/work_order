<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['refresh', 'add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('account.quick Search Fields') })"
        >
        <template #refreshPrepend>
            <!-- 刷新按钮前插槽内容 -->
        </template>

        <template #default  >
            <el-button v-auth="'audit'" v-blur :disabled="baTable.table.selection!.length > 0 ? false:true" class="table-header-operate" type="success" @click="accountAuditFn(1)">
                <Icon color="#ffffff" name="el-icon-RefreshRight" />
                <span class="table-header-operate-text">审核</span>
            </el-button>
            <el-button v-auth="'dispose'" v-blur :disabled="baTable.table.selection!.length > 0 ? false:true" class="table-header-operate" type="success" @click="accountAuditFn(2)">
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
import { onMounted, provide, ref, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import PopupForm from './popupForm.vue'
import { baTableApi } from '/@/api/common'
import { defaultOptButtons} from '/@/components/table'
import { requestThenFn, tips } from '/@/utils/common';
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'
import { editIs_} from '/@/api/backend/index.ts';
import { number } from 'echarts'

defineOptions({
    name: 'account',
})

const { t } = useI18n()
const tableRef = ref()
const optButtons: OptButton[] = defaultOptButtons([])


let newButton: OptButton[] = [
    {
        // 渲染方式:tipButton=带tip的按钮,confirmButton=带确认框的按钮,moveButton=移动按钮
        render: 'tipButton',
        // 按钮名称
        name: 'info',
        // 鼠标放置时的 title 提示
        title: '',
        // 直接在按钮内显示的文字，title 有值时可为空
        text: '修改状态',
        // 按钮类型，请参考 element plus 的按钮类型
        type: 'primary',
        // 按钮 icon
        icon: '',
        class: 'table-row-info',
        // tipButton 禁用 tip
        disabledTip: false,
        // 自定义点击事件
        click: (row: TableRow, field: TableColumn) => {
            accountAuditFn()
        },
        // 按钮是否显示，请返回布尔值
        display: (row: TableRow, field: TableColumn) => {
            return true
        },
        // 按钮是否禁用，请返回布尔值
        disabled: (row: TableRow, field: TableColumn) => {
            return false
        },
        // 自定义el-button属性
        attr: {}
    },
]

optButtons.push(...newButton);


/**
 * baTable 内包含了表格的所有数据且数据具备响应性，然后通过 provide 注入给了后代组件
 */
const baTable = new baTableClass(
    new baTableApi('/admin/Account/'),
    {
        pk: 'id',
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: t('accountList.id'), prop: 'id', align: 'center', width: 70,operator: false },
            { label: t('accountList.name'), prop: 'name', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            { label: t('accountList.account_id'), prop: 'account_id', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false ,render: 'tags'},
            { label: t('accountList.time_zone'), prop: 'time_zone', align: 'center',operator: false },
            // { label: t('accountList.bm'), prop: 'bm', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            // { label: t('accountList.type'), prop: 'type', align: 'center', render: 'tag', operator: 'eq', sortable: false, replaceValue: { '1': t('accountList.type 1'), '2': t('accountList.type 2'), '3': t('accountList.type 3'), '4': t('accountList.type 4'), '5': t('accountList.type 5') } },
            // { label: t('accountList.status'), prop: 'status', align: 'center', render: 'tag', operator: 'eq', sortable: false, replaceValue: { '0': t('accountList.status 0'), '1': t('accountList.status 1'), '2': t('accountList.status 2') } },
            // { label: t('accountList.dispose_status'), prop: 'dispose_status', align: 'center', render: 'tag', operator: 'eq', sortable: false, replaceValue: { '0': t('accountList.dispose_status 0'), '1': t('accountList.dispose_status 1') } },
            { label: t('accountList.money'), prop: 'money', align: 'center', operator: false, sortable: false },
            { label: t('accountList.bm'), prop: 'bm_list', render: 'customTemplate',operator: false, customTemplate: (row: TableRow, field: TableColumn, value: any, column, index: number) => {
                let data = '';
                value.forEach(element => {
                    //console.log(element)
                    data += '<span>'+element+'</span><br/>'
                    
                });
                return  data;
            } },
            { label: t('accountList.status'), prop: 'is_', align: 'center', render: 'customTemplate', operator: 'eq', sortable: false,comSearchRender:'select',
            replaceValue: { '1': t('accountList.status 1'), '2': t('accountList.status 2') } ,
                customTemplate: (row: TableRow, field: TableColumn, value: any, column, index: number) => {                    
                    if(value == 1){
                        return '<span style="background-color:#67c23a;padding:4px 9px;color:#FFF;border-radius:4px">'+t('accountList.status 1')+'</span>';
                    }else if(value == 2){
                        return '<span style="background-color:#ff5151;padding:4px 9px;color:#FFF;border-radius:4px">'+t('accountList.status 2')+'</span>';
                    }
                    return '<span>' + value + '</span>';
                }
        
            },            
            // { label: t('accountList.admin__username'), prop: 'admin.username', align: 'center', operatorPlaceholder: t('Fuzzy query'), render: 'tags', operator: 'LIKE' },
            { label: t('accountList.create_time'), prop: 'create_time', align: 'center', render: 'datetime', operator: false, sortable: 'custom', width: 160, timeFormat: 'yyyy-mm-dd hh:MM' },
            { label: t('accountList.update_time'), prop: 'update_time', align: 'center', render: 'datetime', operator: false, sortable: 'custom', width: 160, timeFormat: 'yyyy-mm-dd hh:MM' },
            { label: t('Operate'), align: 'center', width: 100, render: 'buttons', buttons: optButtons, operator: false },
        ],
        dblClickNotEditColumn: ['all'],
        filter: {
            status:3,
            limit:100
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

const accountAuditFn = () => {
    addPurchasingManagementDialog.statusList = [
        {
            id: 1, name: '正常'
        },
        {
            id: 2, name: '异常'
        }
    ]
    addPurchasingManagementDialog.show = true   
}

const DialogCloseFn = (type:number = 1) => {
    addPurchasingManagementDialog.show = false
    addPurchasingManagementDialog.show2 = false
}



//审核
const confirmAddCommodityFn = (type:number = 1) => {

    let confirmFn = async () => {
        let postData = {
            ids:baTable.getSelectionIds(),
            status:addPurchasingManagementDialog.status,           
        }
        console.log(postData)
        let res: anyObj = await editIs_(postData)
        
        tips('状态变更成功', 'success')
        addPurchasingManagementDialog.show = false
        baTable.onTableHeaderAction('refresh',[])
    }
    confirmFn()
    //confirmElMessageBox('确定要添加为采购单吗？', confirmFn)
}


provide('baTable', baTable)
// baTable.table.filter!.status = 1
// baTable.table.filter!.limit = 100
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
