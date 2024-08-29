<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['refresh', 'add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('demand.recharge.quick Search Fields') })"
        >
    
        <!-- <template #default  >
            <el-button v-auth="'audit'" style="margin-left: 12px;" v-blur :disabled="baTable.table.selection!.length > 0 ? false:true" class="table-header-operate" type="success" @click="accountAuditFn(1)">
                <Icon color="#ffffff" name="el-icon-RefreshRight" />
                <span class="table-header-operate-text">{{t('demand.recharge.status')}}</span>
            </el-button>
        </template> -->
    
    
        </TableHeader>

        <!-- 表格 -->
        <!-- 表格列有多种自定义渲染方式，比如自定义组件、具名插槽等，参见文档 -->
        <!-- 要使用 el-table 组件原有的属性，直接加在 Table 标签上即可 -->
        <Table ref="tableRef">

            <template #account_id>

                <el-table-column :label="t('demand.recharge.account_id')" width="180" align="center">
                    <template #default="scope">
                        <div><span :style="getColumnStyle(scope.$index)" @click="copyText(scope.row.account_id,scope.$index)" > {{ scope.row.account_id }}</span></div>
                        <!-- color:#409eff;background-color:#ecf5ff;border-radius:2px;padding: 5px 8px; -->
                    </template>
                </el-table-column>
                <!-- 在插槽内，您可以随意发挥，通常使用 el-table-column 组件 -->
            </template>

        </Table>

        <!-- 表单 -->
        <PopupForm />






        <div class="addPurchasingManagement-dialog">
            <el-dialog title="审核" :z-index="1000" v-model="addPurchasingManagementDialog.show" @close="DialogCloseFn(1)" center destroy-on-close draggable>
                <div class="dialog-body">

                    <!-- table列表 -->
                    <div class="tableList">
                        <div style="padding: 7px 0;width:200px">
                            <el-select v-if="addPurchasingManagementDialog.type == 3 || addPurchasingManagementDialog.type == 4" v-model="addPurchasingManagementDialog.type"  style="width: 200px" placeholder="清零方式">
                                <el-option v-for="item in addPurchasingManagementDialog.typeList" :key="item.id" :label="item.name" :value="item.id" />
                            </el-select>
                        </div>
                        <el-select v-model="addPurchasingManagementDialog.status"  style="width: 200px" placeholder="状态">
                            <el-option v-for="item in addPurchasingManagementDialog.statusList" :key="item.id" :label="item.name" :value="item.id" />
                        </el-select>
                        <div style="padding: 7px 0;width:200px">
                            <el-input style="" v-if="addPurchasingManagementDialog.type == 3 || addPurchasingManagementDialog.type == 4" v-model="addPurchasingManagementDialog.money" placeholder="金额"></el-input>
                        </div>
                        
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
import { onMounted, provide, ref,reactive,nextTick  } from 'vue'
import { useI18n } from 'vue-i18n'
import PopupForm from './popupForm.vue'
import { baTableApi } from '/@/api/common'
import { defaultOptButtons } from '/@/components/table'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'
import { requestThenFn, tips ,auth} from '/@/utils/common';
import { rechargeAudit} from '/@/api/backend/index.ts';

defineOptions({
    name: 'demand/recharge',
})

const { t } = useI18n()
const tableRef = ref()
const optButtons: OptButton[] = defaultOptButtons(['edit', 'delete'])


let newButton: OptButton[] = [
    {
        // 渲染方式:tipButton=带tip的按钮,confirmButton=带确认框的按钮,moveButton=移动按钮
        render: 'tipButton',
        // 按钮名称
        name: 'info',
        // 鼠标放置时的 title 提示
        title: '审核',
        // 直接在按钮内显示的文字，title 有值时可为空
        text: '审核',
        // 按钮类型，请参考 element plus 的按钮类型
        type: 'primary',
        // 按钮 icon
        icon: '',
        class: 'table-row-info',
        // tipButton 禁用 tip
        disabledTip: false,
        // 自定义点击事件
        click: (row: TableRow, field: TableColumn) => {
            accountAuditFn(row,field)
        },
        // 按钮是否显示，请返回布尔值
        display: (row: TableRow, field: TableColumn) => {
            return auth('audit')
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
    new baTableApi('/admin/demand.Recharge/'),
    {
        pk: 'id',
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: t('demand.recharge.id'), prop: 'uuid', align: 'center', width: 100, operator: false, sortable: 'custom' },
            { label: t('demand.recharge.accountrequestProposal__bm'), prop: 'accountrequestProposal.bm', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            { label: t('demand.recharge.account_name'), prop: 'account_name', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            { label: t('demand.recharge.account_id'), prop: 'account_id', render: 'slot', slotName: 'account_id', operator: 'LIKE'},
            { label: t('demand.recharge.type'), prop: 'type', align: 'center', render: 'customTemplate', operator: 'eq', sortable: false,comSearchRender:'select',
                replaceValue: { '1': t('demand.recharge.type 1'), '2': t('demand.recharge.type 2'), '3': t('demand.recharge.type 3'), '4': t('demand.recharge.type 4') }, 
                customTemplate: (row: TableRow, field: TableColumn, value: any, column, index: number) => {                    
                    if(value == 1 ){
                        return '<span style="background-color:#ecf5ff;padding:4px 9px;color:#409eff;border-radius:4px;font-size:12px">'+t('demand.recharge.type 1')+'</span>';
                    }else if(value == 2){
                        return '<span style="background-color:#ffebdf;padding:4px 9px;color:#d17c30;border-radius:4px;font-size:12px">'+t('demand.recharge.type 2')+'</span>';
                    }else if(value == 3){
                        return '<span style="background-color:#ecf5ff;padding:4px 9px;color:#409eff;border-radius:4px;font-size:12px">'+t('demand.recharge.type 3')+'</span>';
                    }else if(value == 4){
                        return '<span style="background-color:#ecf5ff;padding:4px 9px;color:#409eff;border-radius:4px;font-size:12px">'+t('demand.recharge.type 4')+'</span>';
                    }
                    return '<span>' + value + '</span>';
                }
            },
            { label: t('demand.recharge.number'), prop: 'number', align: 'center', operator: false, sortable: false },
            { label: t('demand.recharge.status'), prop: 'status', align: 'center', render: 'customTemplate', operator: 'eq', sortable: false,comSearchRender:'select',
             replaceValue: { '0': t('demand.recharge.status 0'), '1': t('demand.recharge.status 1'), '2': t('demand.recharge.status 2') } ,
                customTemplate: (row: TableRow, field: TableColumn, value: any, column, index: number) => {                    
                    if(value == 0){
                        return '<span style="background-color:#469ff7;padding:4px 9px;color:#FFF;border-radius:4px">'+t('demand.recharge.status 0')+'</span>';
                    }else if(value == 1){
                        return '<span style="background-color:#67c23a;padding:4px 9px;color:#FFF;border-radius:4px">'+t('demand.recharge.status 1')+'</span>';
                    }else if(value == 2){
                        return '<span style="background-color:#ff5151;padding:4px 9px;color:#FFF;border-radius:4px">'+t('demand.recharge.status 2')+'</span>';
                    }
                    return '<span>' + value + '</span>';
                }
            },
            { label: t('demand.recharge.create_time'), prop: 'create_time', align: 'center', render: 'datetime', operator: 'RANGE', sortable: 'custom', width: 160, timeFormat: 'yyyy-mm-dd hh:MM' },
            { label: t('demand.recharge.update_time'), prop: 'update_time', align: 'center', render: 'datetime', operator: false, sortable: 'custom', width: 160, timeFormat: 'yyyy-mm-dd hh:MM' },
            { label: t('Operate'), align: 'center', width: 150, render: 'buttons', buttons: optButtons, operator: false },
        ],
        dblClickNotEditColumn: ['all'],
        filter: {
            limit:20
        }
    },
    {
        defaultItems: { type: '1', number: 0 },
    }
)



// 添加弹窗组件************************************************************************************************************
interface AddPurchasingManagementDialog {
    id:number
    show: boolean
    status:number
    statusList:Array<any>
    money:string
    type:number
    typeList:Array<any>
}
const addPurchasingManagementDialog: AddPurchasingManagementDialog = reactive({
    show: false,
    type:0
})



const accountAuditFn = (row: TableRow, field: TableColumn) => {
    
    //console.log(1213213223,auth('audit'))
    if(row.status != 0){
        tips('finished!')
        return
    }
    
    addPurchasingManagementDialog.id = row.id
    addPurchasingManagementDialog.show = true
    addPurchasingManagementDialog.statusList = [
        {
            id: 1, name: '处理完成'
        },
        {
            id: 2, name: '异常'
        }
    ]

    addPurchasingManagementDialog.typeList = [
            {
                id: '3', name: '封户清零'
            },
            {
                id: '4', name: '活跃清零'
            }
        ]

    addPurchasingManagementDialog.type = row.type
   
}

const DialogCloseFn = (type:number = 1) => {
    addPurchasingManagementDialog.show = false
}


const confirmAddCommodityFn = (type:number = 1) => {
    let confirmFn = async () => {
        let postData = {
            ids:[addPurchasingManagementDialog.id],
            status:addPurchasingManagementDialog.status,       
            money:addPurchasingManagementDialog.money,
            type:addPurchasingManagementDialog.type
        }
        console.log(postData)
        let res: anyObj = await rechargeAudit(postData)
        
        tips('分配成功', 'success')
        addPurchasingManagementDialog.show = false
        baTable.onTableHeaderAction('refresh',[])
    }
    confirmFn()
    //confirmElMessageBox('确定要添加为采购单吗？', confirmFn)
}

const activeColumn = ref(-1);
const copyText = async (text:string,index:number) => {
    activeColumn.value = index;

    console.log(activeColumn);
  try {
    await navigator.clipboard.writeText(text);
    tips('复制成功', 'success')
  } catch (err) {
    tips('复制异常', 'error')
  }
};

const getColumnStyle = (index:number) => {
    return {
        //backgroundColor: activeColumn.value === index ? '#e26fff' : '#ffffff', // 根据条件动态设置背景色
        borderRadius:'2px',
        padding: '0px 8px',
        border:'3px solid '+(activeColumn.value === index ? '#e26fff' : '#ffffff')
    };
};


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
