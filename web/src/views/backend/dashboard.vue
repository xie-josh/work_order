<template>
    <div class="default-main">
        <div style="">
            账户总金额：{{ dataDialog.totalMoney }}<br/>
            使用金额：{{ dataDialog.usedMoney }}<br/>
            剩余金额：{{ dataDialog.usableMoney }}
        </div>
    </div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted, reactive, nextTick, onActivated, watch, onBeforeMount } from 'vue'
import headerSvg from '/@/assets/dashboard/header-1.svg'
import coffeeSvg from '/@/assets/dashboard/coffee.svg'
import { CountUp } from 'countup.js'
import * as echarts from 'echarts'
import { useNavTabs } from '/@/stores/navTabs'
import { useTemplateRefsList } from '@vueuse/core'
import { index } from '/@/api/backend/dashboard'
import { useI18n } from 'vue-i18n'
import { Local } from '/@/utils/storage'
import { useAdminInfo } from '/@/stores/adminInfo'
import { WORKING_TIME } from '/@/stores/constant/cacheKey'
import { fullUrl, getGreet } from '/@/utils/common'
import { useEventListener } from '@vueuse/core'
import { accountCountMoney } from '/@/api/backend/index.ts';
let workTimer: number

defineOptions({
    name: 'dashboard',
})

const d = new Date()
const { t } = useI18n()
const navTabs = useNavTabs()
const adminInfo = useAdminInfo()
const chartRefs = useTemplateRefsList<HTMLDivElement>()

const state: {
    charts: any[]
    remark: string
    workingTimeFormat: string
    pauseWork: boolean
    AccountCountMoney:string
} = reactive({
    charts: [],
    remark: 'dashboard.Loading',
    workingTimeFormat: '',
    pauseWork: false,
    AccountCountMoney:''
})


const dataDialog: {
    totalMoney:string
    usedMoney:string
    usableMoney:string
} = reactive({
    totalMoney:'0',
    usedMoney:'0',
    usableMoney:'0'
})

index().then((res) => {
    state.remark = res.data.remark
})

const countUpFun = (id: string) => {
    nextTick(() => {
        let value = document.getElementById(id)?.innerText
        if (value) {
            new CountUp(id, parseInt(value), {
                startVal: 0,
                duration: 3,
            }).start()
        }
    })
}

const initCountUp = () => {
    countUpFun('user_reg_number')
    countUpFun('file_number')
    countUpFun('users_number')
    countUpFun('addons_number')
}


const getAccountCountMoneyFn = async ()=>{

    let postData = {}
    let res: anyObj = await accountCountMoney(postData)
    // console.log(res,res.data.money);
    dataDialog.totalMoney = String(res.data.totalMoney);
    dataDialog.usedMoney = String(res.data.usedMoney);
    dataDialog.usableMoney = String(res.data.usableMoney);
    // console.log('dataDialog.AccountCountMoney:',dataDialog.AccountCountMoney);
}
getAccountCountMoneyFn()


onMounted(() => {
    // startWork()
    // initCountUp()
    // initUserGrowthChart()
    // initFileGrowthChart()
    // initUserSourceChart()
    // initUserSurnameChart()
    // useEventListener(window, 'resize', echartsResize)
})

onBeforeMount(() => {
    for (const key in state.charts) {
        state.charts[key].dispose()
    }
})

onUnmounted(() => {
    clearInterval(workTimer)
})

</script>

<style scoped lang="scss">
.welcome {
    background: #e1eaf9;
    border-radius: 6px;
    display: flex;
    align-items: center;
    padding: 15px 20px !important;
    box-shadow: 0 0 30px 0 rgba(82, 63, 105, 0.05);
    .welcome-img {
        height: 100px;
        margin-right: 10px;
        user-select: none;
    }
    .welcome-title {
        font-size: 1.5rem;
        line-height: 30px;
        color: var(--ba-color-primary-light);
    }
    .welcome-note {
        padding-top: 6px;
        font-size: 15px;
        color: var(--el-text-color-primary);
    }
}
.working {
    height: 130px;
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    height: 100%;
    position: relative;
    &:hover {
        .working-coffee {
            -webkit-transform: translateY(-4px) scale(1.02);
            -moz-transform: translateY(-4px) scale(1.02);
            -ms-transform: translateY(-4px) scale(1.02);
            -o-transform: translateY(-4px) scale(1.02);
            transform: translateY(-4px) scale(1.02);
            z-index: 999;
        }
    }
    .working-coffee {
        transition: all 0.3s ease;
        width: 80px;
    }
    .working-text {
        display: block;
        width: 100%;
        font-size: 15px;
        text-align: center;
        color: var(--el-text-color-primary);
    }
    .working-opt {
        position: absolute;
        top: -40px;
        right: 10px;
        background-color: rgba($color: #000000, $alpha: 0.3);
        padding: 10px 20px;
        border-radius: 20px;
        color: var(--ba-bg-color-overlay);
        transition: all 0.3s ease;
        cursor: pointer;
        opacity: 0;
        z-index: 999;
        &:active {
            background-color: rgba($color: #000000, $alpha: 0.6);
        }
    }
    &:hover {
        .working-opt {
            opacity: 1;
            top: 0;
        }
        .working-done {
            opacity: 1;
            top: 50px;
        }
    }
}
.small-panel-box {
    margin-top: 20px;
}
.small-panel {
    background-color: #e9edf2;
    border-radius: var(--el-border-radius-base);
    padding: 25px;
    margin-bottom: 20px;
    .small-panel-title {
        color: #92969a;
        font-size: 15px;
    }
    .small-panel-content {
        display: flex;
        align-items: flex-end;
        margin-top: 20px;
        color: #2c3f5d;
        .content-left {
            font-size: 24px;
            .icon {
                margin-right: 10px;
            }
            span {
                display: inline-block;
                font-size: 28px;
            }
        }
        .content-right {
            font-size: 18px;
            margin-left: auto;
        }
        .color-success {
            color: var(--el-color-success);
        }
        .color-warning {
            color: var(--el-color-warning);
        }
        .color-danger {
            color: var(--el-color-danger);
        }
        .color-info {
            color: var(--el-text-color-secondary);
        }
    }
}
.growth-chart {
    margin-bottom: 20px;
}
.user-growth-chart,
.file-growth-chart {
    height: 260px;
}
.new-user-growth {
    height: 300px;
}

.user-source-chart,
.user-surname-chart {
    height: 400px;
}
.new-user-item {
    display: flex;
    align-items: center;
    padding: 20px;
    margin: 10px 15px;
    box-shadow: 0 0 30px 0 rgba(82, 63, 105, 0.05);
    background-color: var(--ba-bg-color-overlay);
    .new-user-avatar {
        height: 48px;
        width: 48px;
        border-radius: 50%;
    }
    .new-user-base {
        margin-left: 10px;
        color: #2c3f5d;
        .new-user-name {
            font-size: 15px;
        }
        .new-user-time {
            font-size: 13px;
        }
    }
    .new-user-arrow {
        margin-left: auto;
    }
}
.new-user-card :deep(.el-card__body) {
    padding: 0;
}

@media screen and (max-width: 425px) {
    .welcome-img {
        display: none;
    }
}
@media screen and (max-width: 1200px) {
    .lg-mb-20 {
        margin-bottom: 20px;
    }
}
html.dark {
    .welcome {
        background-color: var(--ba-bg-color-overlay);
    }
    .small-panel {
        background-color: var(--ba-bg-color-overlay);
        .small-panel-content {
            color: var(--el-text-color-regular);
        }
    }
    .new-user-item {
        .new-user-base {
            color: var(--el-text-color-regular);
        }
    }
}
</style>
