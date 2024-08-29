import createAxios from '/@/utils/axios'
import { useAdminInfo } from '/@/stores/adminInfo'

export const url = '/admin/Index/'

export function index() {
    return createAxios({
        url: url + 'index',
        method: 'get',
    })
}

export function login(method: 'get' | 'post', params: object = {}) {
    return createAxios({
        url: url + 'login',
        data: params,
        method: method,
    })
}

export function logout() {
    const adminInfo = useAdminInfo()
    return createAxios({
        url: url + 'logout',
        method: 'POST',
        data: {
            refreshToken: adminInfo.getToken('refresh'),
        },
    })
}

// export function admin(data: anyObj) {
//     return createAxios({
//         url: `/admin/auth.Admin/channel`,
//         method: 'get',
//         data: data,
//     })
// }

export function getAdminList(data: anyObj) {
    return createAxios({
        url: `/admin/auth.Admin/channel`,
        method: 'get',
        data: data,
    })
}

export function getAccountAudit(data: anyObj) {
    return createAxios({
        url: `/admin/Account/audit`,
        method: 'post',
        data: data,
    })
}

export function editIs_(data: anyObj) {
    return createAxios({
        url: `/admin/Account/editIs_`,
        method: 'post',
        data: data,
    })
}

export function getAccountNumber(data: anyObj) {
    return createAxios({
        url: `/admin/Account/getAccountNumber`,
        method: 'get',
    })
}

export function allAccountAudit(data: anyObj) {
    return createAxios({
        url: `/admin/Account/allAudit`,
        method: 'post',
        data: data,
    })
}


export function accountrequestAudit(data: anyObj) {
    return createAxios({
        url: `/admin/addaccountrequest.Accountrequest/audit`,
        method: 'post',
        data: data,
    })
}

export function AccountrequestProposalIndex(data: anyObj) {
    return createAxios({
        url: `/admin/addaccountrequest.AccountrequestProposal/index`,
        method: 'get',
        params: data,
    })
}

export function AccountrequestProposalAudit(data: anyObj) {
    return createAxios({
        url: `/admin/addaccountrequest.AccountrequestProposal/audit`,
        method: 'post',
        data: data,
    })
}

export function getAccountDisposeStatus(data: anyObj) {
    return createAxios({
        url: `/admin/Account/disposeStatus`,
        method: 'post',
        data: data,
    })
}


export function rechargeAudit(data: anyObj) {
    return createAxios({
        url: `/admin/demand.Recharge/audit`,
        method: 'post',
        data: data,
    })
}

export function bmAudit(data: anyObj) {
    return createAxios({
        url: `/admin/demand.Bm/audit`,
        method: 'post',
        data: data,
    })
}


export function bmDisposeStatus(data: anyObj) {
    return createAxios({
        url: `/admin/demand.Bm/disposeStatus`,
        method: 'post',
        data: data,
    })
}

export function getBmList(data: anyObj) {
    return createAxios({
        url: `/admin/demand.Bm/getBmList`,
        method: 'get',
        params: data,
    })
}

export function transferStatus(data: anyObj) {
    return createAxios({
        url: `/admin/demand.Transfer/audit`,
        method: 'post',
        data: data,
    })
}



export function accountCountMoney(data: anyObj) {
    return createAxios({
        url: `/admin/Account/accountCountMoney`,
        method: 'get',
        data: data,
    })
}



