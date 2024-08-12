<template>
    <div class="default-main ba-table-box">
        <el-alert class="ba-table-alert" v-if="baTable.table.remark" :title="baTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <!-- 自定义按钮请使用插槽，甚至公共搜索也可以使用具名插槽渲染，参见文档 -->
        <TableHeader
            :buttons="['refresh', 'add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('addaccountrequest.accountrequest.quick Search Fields') })"
        ></TableHeader>

        <!-- 表格 -->
        <!-- 表格列有多种自定义渲染方式，比如自定义组件、具名插槽等，参见文档 -->
        <!-- 要使用 el-table 组件原有的属性，直接加在 Table 标签上即可 -->
        <Table ref="tableRef"></Table>

        <!-- 表单 -->
        <PopupForm />



        <div class="addPurchasingManagement-dialog">
            <el-dialog title="account ids" :z-index="1000" v-model="addPurchasingManagementDialog.show" @close="DialogCloseFn" center destroy-on-close draggable>
                <div class="dialog-body">

                    <div style="padding: 5px;">BM: {{ addPurchasingManagementDialog.selectItem.bm }}</div>
                    
                    <!-- <div style="padding: 10px 0;">
                        <el-input style="width: 230px;" v-model="addPurchasingManagementDialog.affiliationBm" placeholder="affiliation BM"></el-input>
                    </div> -->
                    <!-- table列表 -->
                    <div class="tableList">
                        <el-input type="textarea" :rows="20" v-model="addPurchasingManagementDialog.idsList"></el-input>
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
import { requestThenFn, tips } from '/@/utils/common';
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import baTableClass from '/@/utils/baTable'
import { accountrequestAudit} from '/@/api/backend/index.ts';

defineOptions({
    name: 'addaccountrequest/accountrequest',
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
        icon: 'fa fa-search-plus',
        class: 'table-row-info',
        // tipButton 禁用 tip
        disabledTip: false,
        // 自定义点击事件
        click: (row: TableRow, field: TableColumn) => {
            accountAuditFn(row,field)
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
    new baTableApi('/admin/addaccountrequest.Accountrequest/'),
    {
        pk: 'id',
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: t('addaccountrequest.accountrequest.id'), prop: 'id', align: 'center', width: 70, operator: 'RANGE', sortable: 'custom' },
            { label: t('addaccountrequest.accountrequest.bm'), prop: 'bm', align: 'center', operatorPlaceholder: t('Fuzzy query'), operator: 'LIKE', sortable: false },
            { label: t('addaccountrequest.accountrequest.number'), prop: 'number', align: 'center', operator: 'RANGE', sortable: false },
            { label: t('addaccountrequest.accountrequest.affiliation_bm'), prop: 'affiliation_bm', align: 'center', operator: 'LIKE', sortable: false },
            // { label: t('addaccountrequest.accountrequest.admin__username'), prop: 'admin.username', align: 'center', operatorPlaceholder: t('Fuzzy query'), render: 'tags', operator: 'LIKE' },
            
            { label: t('addaccountrequest.accountrequest.status'), prop: 'status', align: 'center', render: 'tag', operator: 'eq', sortable: false, replaceValue: { '0': t('addaccountrequest.accountrequest.status 0'), '1': t('addaccountrequest.accountrequest.status 1') } },

            //{ label: t('addaccountrequest.accountrequest.status'), prop: 'status', align: 'center', render: 'switch', operator: 'eq', sortable: false, replaceValue: { '0': t('addaccountrequest.accountrequest.status 0'), '1': t('addaccountrequest.accountrequest.status 1') } },
            { label: t('addaccountrequest.accountrequest.create_time'), prop: 'create_time', align: 'center', render: 'datetime', operator: 'RANGE', sortable: 'custom', width: 160, timeFormat: 'yyyy-mm-dd hh:MM' },
            { label: t('addaccountrequest.accountrequest.update_time'), prop: 'update_time', align: 'center', render: 'datetime', operator: 'RANGE', sortable: 'custom', width: 160, timeFormat: 'yyyy-mm-dd hh:MM' },
            { label: t('Operate'), align: 'center', width: 100, render: 'buttons', buttons: optButtons, operator: false },
        ],
        dblClickNotEditColumn: ['all', 'status'],
        filter: {
            limit:20
        }
    },
    {
        defaultItems: { number: 0 },
    }
)

// 添加审核************************************************************************************************************
interface AddPurchasingManagementDialog {
    show: boolean
    id:number
    ids:Array<SkuItem>
    idsList: string,
    idsNumber:number,
    selectItem:anyObj,
    affiliationBm:string
}
const addPurchasingManagementDialog: AddPurchasingManagementDialog = reactive({
    show: false,
    id:0,
    ids:[],
    idsList:'',
    idsNumber:0,
    selectItem:{}
})

const accountAuditFn = (row: TableRow, field: TableColumn) => {
    
    if(row.status != 0){
        tips('finished!')
        return
    }
    addPurchasingManagementDialog.show = true
    addPurchasingManagementDialog.id = row.id
    addPurchasingManagementDialog.idsNumber = row.number
    addPurchasingManagementDialog.selectItem = row
}

const DialogCloseFn = () => {
    addPurchasingManagementDialog.show = false
}


//审核
const confirmAddCommodityFn = () => {


    const processedIds = addPurchasingManagementDialog.idsList
        .split(/\r?\n/)
        .map((item) => item.replace(/\t/g, '').trim())
        .filter((line) => line.trim() !== '')

    const validLineCount = processedIds.length;

    if(validLineCount < addPurchasingManagementDialog.idsNumber){
        tips('number wrong')
        return
    }

    // 将处理后的 idsList 转换为逗号分隔的字符串
    let list = processedIds.join(',');

    //console.log(validLineCount,addPurchasingManagementDialog.idsNumber,addPurchasingManagementDialog.idsList)

    //return

    let confirmFn = async () => {
        let postData = {
            ids:list,
            id:addPurchasingManagementDialog.id,
            //affiliationBm:addPurchasingManagementDialog.affiliationBm,        
        }
        console.log(postData)
        let res: anyObj = await accountrequestAudit(postData)
        
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
