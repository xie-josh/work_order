<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['refresh', 'add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('bindingCard.quick Search Fields') })"
        >
        <template #refreshPrepend>
            <!-- 刷新按钮前插槽内容 -->
        </template>

        <template #default  >            
            <el-button v-blur :disabled="baTable.table.selection!.length > 0 ? false:true" class="table-header-operate" type="success" @click="accountAuditFn(5,[])">
                <span class="table-header-operate-text">批量分配</span>
            </el-button>
        </template>

    
        </TableHeader>

        <!-- 表格 -->
        <!-- 表格列有多种自定义渲染方式，比如自定义组件、具名插槽等，参见文档 -->
        <!-- 要使用 el-table 组件原有的属性，直接加在 Table 标签上即可 -->
        <Table ref="tableRef">
            <template #name>
                <el-table-column :label="t('bindingCard.name')" width="180" align="center">
                    <template #default="scope">
                        <div><span :style="getColumnStyle(scope.$index)" @click="copyText(scope.row.name,scope.$index)" > {{ scope.row.name }}</span></div>
                    </template>
                </el-table-column>
                <!-- 在插槽内，您可以随意发挥，通常使用 el-table-column 组件 -->
            </template>
            
            <template #account_id>

                <el-table-column :label="t('bindingCard.account_id')" width="180" align="center">
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
                        <el-select v-model="addPurchasingManagementDialog.admin_id" style="width: 200px" placeholder="渠道" @change="getAccountrequestProposalFn">
                            <el-option v-for="item in addPurchasingManagementDialog.adminList" :key="item.id" :label="item.name" :value="item.id" />
                        </el-select>
                        <el-select v-model="addPurchasingManagementDialog.account_id" :disabled="!addPurchasingManagementDialog.admin_id" style="width: 200px" placeholder="账户">
                            <el-option v-for="item in addPurchasingManagementDialog.accountList" :key="item.id" :label="item.name" :value="item.id" />
                        </el-select>
                    </div>

                </div>

                <template #footer>
                    <div class="dialog-footer">
                        <el-button type="primary" @click="confirmAddCommodityFn(3)" >确认</el-button>
                        <el-button @click="DialogCloseFn">取消</el-button>
                    </div>
                </template>
            </el-dialog>
        </div>


        <div class="addPurchasingManagement-dialog">
            <el-dialog title="处理" style="width: 600px;" :z-index="1000" v-model="addPurchasingManagementDialog.show2" @close="DialogCloseFn(2)" center destroy-on-close draggable>
                <div class="dialog-body">
                    开户是否成功：
                    <!-- table列表 -->
                    <!-- <div class="tableList">
                        <el-select v-model="addPurchasingManagementDialog.disposeStatus"  style="width: 200px" placeholder="状态">
                            <el-option v-for="item in addPurchasingManagementDialog.disposeStatusList" :key="item.id" :label="item.name" :value="item.id" />
                        </el-select>
                    </div> -->

                </div>

                <template #footer>
                    <div>
                        <el-button type="primary" icon="el-icon-Delete" @click="confirmAddCommodityFn(6)" >重新分配</el-button>
                        <el-button type="danger" @click="confirmAddCommodityFn(5)" >失败</el-button>
                        <el-button type="success" @click="confirmAddCommodityFn(4)" >确认</el-button>
                        <el-button @click="DialogCloseFn" style="right: 20px;position: absolute;">取消</el-button>
                    </div>
                </template>
            </el-dialog>
        </div>

        <div class="addPurchasingManagement-dialog">
            <el-dialog title="批量分配" style="width: 600px;" :z-index="1000" v-model="addPurchasingManagementDialog.show5" @close="DialogCloseFn(2)" center destroy-on-close draggable>
                <div class="dialog-body">
                    <div class="tableList">
                        <el-select v-model="addPurchasingManagementDialog.admin_id" style="width: 200px" placeholder="渠道" @change="getAccountrequestProposalFn">
                            <el-option v-for="item in addPurchasingManagementDialog.adminList" :key="item.id" :label="item.name" :value="item.id" />
                        </el-select>
                        <el-select multiple collapse-tags v-model="addPurchasingManagementDialog.account_ids" :disabled="!addPurchasingManagementDialog.admin_id" style="width: 200px" placeholder="账户">
                            <el-option v-for="item in addPurchasingManagementDialog.accountList" :key="item.id" :label="item.name" :value="item.id" />
                        </el-select>
                    </div>
                </div>

                <template #footer>
                    <div>
                        <el-button type="success" @click="confirmAddCommodityFn(7)" >确认</el-button>
                        <el-button @click="DialogCloseFn" style="right: 20px;position: absolute;">取消</el-button>
                    </div>
                </template>
            </el-dialog>
        </div>


    </div>
</template>

<script setup lang="ts">
import { onMounted, provide, ref, reactive,h } from 'vue'
import { useI18n } from 'vue-i18n'
import PopupForm from './popupForm.vue'
import { baTableApi } from '/@/api/common'
import { defaultOptButtons} from '/@/components/table'
import { requestThenFn, tips } from '/@/utils/common';
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'
import { getAdminList ,getAccountAudit,getAccountDisposeStatus,AccountrequestProposalIndex,allAccountAudit} from '/@/api/backend/index.ts';
import { number } from 'echarts'

defineOptions({
    name: 'account',
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
        title: '',
        // 直接在按钮内显示的文字，title 有值时可为空
        text: '分配',
        // 按钮类型，请参考 element plus 的按钮类型
        type: 'primary',
        // 按钮 icon
        icon: '',
        class: 'table-row-info',
        // tipButton 禁用 tip
        disabledTip: false,
        // 自定义点击事件
        click: (row: TableRow, field: TableColumn) => {
            if(row.status != 1){
                tips('已经分配账户，不需要在分配！', 'success')
            }else{
                accountAuditFn(3,row)
            }
            
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
    {
        // 渲染方式:tipButton=带tip的按钮,confirmButton=带确认框的按钮,moveButton=移动按钮
        render: 'tipButton',
        // 按钮名称
        name: 'info',
        // 鼠标放置时的 title 提示
        title: '',
        // 直接在按钮内显示的文字，title 有值时可为空
        text: '处理',
        // 按钮类型，请参考 element plus 的按钮类型
        type: 'success',
        // 按钮 icon
        icon: '',
        class: 'table-row-info',
        // tipButton 禁用 tip
        disabledTip: false,
        // 自定义点击事件
        click: (row: TableRow, field: TableColumn) => {
            // console.log(row, field);
            if(row.status != 3){
                tips('请先分配账户或该数据已经完成！', 'success')
            }else{
                addPurchasingManagementDialog.id = row.id
                accountAuditFn(4,row)
                //confirmAddCommodityFn(4)
            }
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
        attr: {
        },
        popconfirm:{
            title:'确定完成吗？' ,
            cancelButtonText:'失败',
            // onCancel: (row, field) => {
            //     console.log('取消按钮点击');
            //     console.log(row, field);
            //     // 这里添加取消按钮点击时的处理逻辑
            // }
        }
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
            { label: t('bindingCard.admin__username'), prop: 'admin.nickname', align: 'center', operatorPlaceholder: t('Fuzzy query'), render: 'tags', operator: 'LIKE' ,width:100},
            { label: t('bindingCard.id'), prop: 'id', align: 'center', width: 70, operator: false, sortable: 'custom' },
            { label: t('bindingCard.bm'), prop: 'accountrequestProposal.bm', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            // { label: t('bindingCard.name'), prop: 'name', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            // { label: t('bindingCard.account_id'), prop: 'account_id', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false ,render: 'tags'},
            { label: t('bindingCard.name'),prop: 'name', render: 'slot', slotName: 'name', operator: 'LIKE'},
            { label: t('bindingCard.account_id'),prop: 'account_id', render: 'slot', slotName: 'account_id', operator: 'LIKE'},
            { label: t('bindingCard.time_zone'), prop: 'time_zone', align: 'center', operator: false, sortable: false,  render: 'customTemplate',width:90,
                customTemplate: (row: TableRow, field: TableColumn, value: any, column, index: number) => {
                    value = ref(value.replace('GMT ', '')).value;
                    let color = getTimeZoneColor(value)
                    return '<span style="border: 2px solid '+color+';border-radius:9px;display:block;">'+value+'</span>';
                }
            },            
            // { label: '管理员', render: 'slot', slotName: 'test', operator: 'LIKE' },
            // { label: t('bindingCard.type'), prop: 'type', align: 'center', render: 'tag', operator: 'eq', sortable: false, replaceValue: { '1': t('bindingCard.type 1'), '2': t('bindingCard.type 2'), '3': t('bindingCard.type 3'), '4': t('bindingCard.type 4'), '5': t('bindingCard.type 5') } },
            { label: t('bindingCard.money'), prop: 'money', align: 'center', operator: 'RANGE', sortable: false ,width:100},
            { label: t('bindingCard.status'), prop: 'status', align: 'center', operator: 'eq', sortable: false, comSearchRender:'select', render: 'customTemplate',
                replaceValue: { '0': t('bindingCard.status 0'), '1': t('bindingCard.status 1'), '2': t('bindingCard.status 2'), '3': t('bindingCard.status 3'), '4': t('bindingCard.status 4'), '5': t('bindingCard.status 5') }, 
                customTemplate: (row: TableRow, field: TableColumn, value: any, column, index: number) => {
                    
                    if(value == 0){
                        return '<span style="background-color:#469ff7;padding:4px 9px;color:#FFF;border-radius:4px">'+t('bindingCard.status 0')+'</span>';
                    }else if(value == 1){
                        return '<span style="background-color:#409eff;padding:4px 9px;color:#FFF;border-radius:4px">'+t('bindingCard.status 1')+'</span>';
                    }else if(value == 2){
                        return '<span style="background-color:#ff5151;padding:4px 9px;color:#FFF;border-radius:4px">'+t('bindingCard.status 2')+'</span>';
                    }else if(value == 3){
                        return '<span style="background-color:#e6a23c;padding:4px 9px;color:#FFF;border-radius:4px">'+t('bindingCard.status 3')+'</span>';
                    }else if(value == 4){
                        return '<span style="background-color:#67c23a;padding:4px 9px;color:#FFF;border-radius:4px">'+t('bindingCard.status 4')+'</span>';
                    }else if(value == 5){
                        return '<span style="background-color:#ff5151;padding:4px 9px;color:#FFF;border-radius:4px">'+t('bindingCard.status 5')+'</span>';
                    }
                    return '<span>' + value + '</span>';
                }
            }, 
            // { label: t('bindingCard.dispose_status'), prop: 'dispose_status', align: 'center', render: 'tag', operator: 'eq', sortable: false, replaceValue: { '0': t('bindingCard.dispose_status 0'), '1': t('bindingCard.dispose_status 1') } },
            { label: t('bindingCard.create_time'), prop: 'create_time', align: 'center', render: 'datetime', operator: 'RANGE', sortable: 'custom', width: 160, timeFormat: 'yyyy-mm-dd hh:MM' },
            { label: t('bindingCard.update_time'), prop: 'update_time', align: 'center', render: 'datetime', operator: 'RANGE', sortable: 'custom', width: 160, timeFormat: 'yyyy-mm-dd hh:MM' },
            { label: t('Operate'), align: 'center', width: 150, render: 'buttons', buttons: optButtons, operator: false },
        ],
        dblClickNotEditColumn: ['all'],
        filter: {
            limit:20,
            status:1
        }
    },
    {
        defaultItems: { time: null, time_zone: 0, type: '1', status: '0', dispose_status: '0', money: 0 },
    }
)





// 添加弹窗组件************************************************************************************************************
interface AddPurchasingManagementDialog {
    show: boolean
    id:number
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
    account_id:number
    accountList:Array<any>,
    show5:boolean,
    account_ids:Array<any>,
}
const addPurchasingManagementDialog: AddPurchasingManagementDialog = reactive({
    show: false,
    show5:false,
    purchasingId: '',
    tableData: [],
    adminList: [],
    //admin_id:0,
    // status:0,
    statusList:[],
})

const activeColumn = ref(-1);
//let activeColumn:number = -1;

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


const accountAuditFn = (type:number = 1,row: TableRow) => {
    addPurchasingManagementDialog.id = row.id
    if(type == 3){
        addPurchasingManagementDialog.show = true
        getWarehouseZoneIndexFn()
    }else if(type == 4){
        addPurchasingManagementDialog.show2 = true
    }else if(type == 5){
        getWarehouseZoneIndexFn()
        addPurchasingManagementDialog.show5 = true
    }
}

const DialogCloseFn = (type:number = 1) => {
    addPurchasingManagementDialog.show = false
    addPurchasingManagementDialog.show2 = false
    addPurchasingManagementDialog.show5 = false
}

const getWarehouseZoneIndexFn = async ()=>{
    // 仓库地区
    let postData = {
    }
    let res: anyObj = await getAdminList(postData)

    addPurchasingManagementDialog.adminList = res.data.list.map((item: anyObj) => {
        return { id: item.id, name: item.username }
    })

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

const getAccountrequestProposalFn = async ()=>{

    
    let postData = {
        'search':[
            // {
            //     'field':'affiliation_admin_id',
            //     'val':'=',
            //     'operator':'NULL',
            // },
            {
                'field':'accountrequest_proposal.admin_id',
                'val':addPurchasingManagementDialog.admin_id,
                'operator':'=',
            },{
                'field':'accountrequest_proposal.status',
                'val':0,
                'operator':'=',
            }
        ],
        'limit':999
    }
    let res: anyObj = await AccountrequestProposalIndex(postData)

    addPurchasingManagementDialog.accountList = res.data.list.map((item: anyObj) => {
        return { id: item.account_id, name: item.account_id+'('+item.time_zone+')'}
    })


    addPurchasingManagementDialog.accountList.reverse();



    //console.log(addPurchasingManagementDialog.accountList);
}


//审核
const confirmAddCommodityFn = (type:number = 1) => {

    if(type == 3){
        if(!addPurchasingManagementDialog.admin_id){
            tips('请选择渠道')
            return
        }
        let confirmFn = async () => {
            let postData = {
                ids:[addPurchasingManagementDialog.id],
                account_id:addPurchasingManagementDialog.account_id,
                status:3
            }
            console.log(postData)
            let res: anyObj = await getAccountAudit(postData)
            
            tips('分配成功', 'success')
            addPurchasingManagementDialog.show = false
            addPurchasingManagementDialog.admin_id = 0
            baTable.onTableHeaderAction('refresh',[])
        }
        confirmFn()
    }else if(type==4){
        let confirmFn = async () => {
            let postData = {
                ids:[addPurchasingManagementDialog.id],
                status:4,
            }
            console.log(postData)
            let res: anyObj = await getAccountAudit(postData)
            
            tips('分配成功', 'success')
            baTable.onTableHeaderAction('refresh',[])
        }
        confirmFn()
    }else if(type==5){
        let confirmFn = async () => {
            let postData = {
                ids:[addPurchasingManagementDialog.id],
                status:5,
            }
            console.log(postData)
            let res: anyObj = await getAccountAudit(postData)
            
            tips('分配成功', 'success')
            baTable.onTableHeaderAction('refresh',[])
        }
        confirmFn()
    }else if(type==6){
        let confirmFn = async () => {
            let postData = {
                ids:[addPurchasingManagementDialog.id],
                status:6,
            }
            console.log(postData)
            let res: anyObj = await getAccountAudit(postData)
            
            tips('分配重置', 'success')
            baTable.onTableHeaderAction('refresh',[])
        }
        confirmFn()
    }else if(type==7){
        if(!addPurchasingManagementDialog.admin_id){
            tips('请选择渠道')
            return
        }
        let confirmFn = async () => {
            let postData = {
                ids:baTable.getSelectionIds(),
                admin_id:addPurchasingManagementDialog.admin_id,
                account_ids:addPurchasingManagementDialog.account_ids,
            }
            console.log(postData)
            let res: anyObj = await allAccountAudit(postData)

            addPurchasingManagementDialog.show5 = false
            addPurchasingManagementDialog.admin_id = ''
            addPurchasingManagementDialog.account_ids = []

            tips('分配重置', 'success')
            baTable.onTableHeaderAction('refresh',[])
        }
        confirmFn()
    }
    DialogCloseFn()
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
