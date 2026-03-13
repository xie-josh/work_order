# Goletter AKMS SDK

蓝瀚媒体代理业务 OpenAPI SDK（`https://akms-openapi.bluemediagroup.cn`），封装了文档中的主要接口：

- 数据概览：`/open_api/v1.0/dashboard/brand_dash`（消耗概览、消耗趋势、媒体详情、账户天级消耗）
- 开户管理：`/open_api/v1.0/user_open/...`（Facebook / Google / TikTok 开户及辅助信息）
- 账户管理与需求申请：`/open_api/v1.0/application/...`（额度管理、绑定、账户更名、新兴媒体等）

## 安装

在根项目的 `composer.json` 中通过 path 仓库或直接引用本包：

```json
{
  "require": {
    "goletter/akms": "*"
  }
}
```

然后执行：

```bash
composer install
```

## 快速开始

```php
use Goletter\Akms\Client;
use Goletter\Akms\DashboardApi;
use Goletter\Akms\OpenAccountApi;
use Goletter\Akms\ApplicationApi;

$client = new Client('your-access-token');

// 数据概览
$dashboard = new DashboardApi($client);
$overview = $dashboard->getBaseSpend();

// 开户管理
$openApi = new OpenAccountApi($client);
$fbList = $openApi->facebookList([
    'current_page' => 1,
    'page_size'    => 100,
]);

// 账户管理与需求申请
$appApi = new ApplicationApi($client);
$fbAccounts = $appApi->facebookAccounts([
    'current_page' => 1,
    'page_size'    => 100,
]);
```

所有接口在返回非 `code = 0` 时会抛出 `Goletter\Akms\Exceptions\AkmsApiException`，其中可通过 `getResponse()` 获取原始响应数据。

