<template>
    <el-aside v-if="!navTabs.state.tabFullScreen" :class="'layout-aside-' + config.layout.layoutMode + ' ' + (config.layout.shrink ? 'shrink' : '')">
        <Logo v-if="config.layout.menuShowTopBar" />
        <MenuVerticalChildren v-if="config.layout.layoutMode == 'Double'" />
        <MenuVertical v-else />
        <NavMenus />
    </el-aside>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Logo from '/@/layouts/backend/components/logo.vue'
import MenuVertical from '/@/layouts/backend/components/menus/menuVertical.vue'
import MenuVerticalChildren from '/@/layouts/backend/components/menus/menuVerticalChildren.vue'
import { useConfig } from '/@/stores/config'
import { useNavTabs } from '/@/stores/navTabs'
import NavMenus from './navMenus.vue'

defineOptions({
    name: 'layout/aside',
})

const config = useConfig()
const navTabs = useNavTabs()

const menuWidth = computed(() => config.menuWidth())
</script>

<style scoped lang="scss">
.layout-aside-Default {
    background: var(--ba-bg-color-overlay);
    //margin: 16px 0 16px 16px;
    margin: 0 0 0 12px;
    height: calc(100vh);
    //height: calc(100vh - 32px);
    //box-shadow: var(--el-box-shadow-light);
    border-radius: var(--el-border-radius-base);
    overflow: hidden;
    transition: width 0.3s ease;
    width: v-bind(menuWidth);
    border-right: 1px solid #f3f2f2;
    //border-inline-end: 1px solid rgba(5,5,5,.06);
    border-inline-end: 2px solid #e5e5e5;
}
.layout-aside-Classic,
.layout-aside-Double {
    background: var(--ba-bg-color-overlay);
    margin: 0;
    height: 100vh;
    overflow: hidden;
    transition: width 0.3s ease;
    width: v-bind(menuWidth);
}
.shrink {
    position: fixed;
    top: 0;
    left: 0;
    z-index: 9999999;
}
</style>
