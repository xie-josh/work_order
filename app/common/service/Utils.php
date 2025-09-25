<?php
namespace app\common\service;

class Utils
{

    /**
     * Summary of getExcelFolders
     * @param mixed $folders 路径（xxx/xxx/xxx）
     * @param mixed $typeTime 是否以时间创建新的文件夹（0=否，1=是）
     * @param mixed $timeFormat 以时间创建新的文件夹格式（Ym）
     * @return array
     */
    public function getExcelFolders($folders = 'excel',$typeTime = 1,$timeFormat = 'Ym')
    {
        $time = date('Ym');
        if($typeTime){
            $filePath = 'storage/'.$folders.'/'.$time;
        }else{
            $filePath = 'storage/'.$folders;
        }

        $puth = public_path().$filePath;

        if (!is_dir($puth)) {
            // 创建文件夹，第三个参数 true 表示递归创建多级目录
            mkdir($puth, 0755, true);
            // 将文件夹归属到 www 组
            // chown($puth, 'www');
            // 设置文件夹权限为 755
            chmod($puth, 0755);
        } 

        $milliseconds = (int)round(microtime(true) * 1000);
        return [
            'path'=>$puth,
            'name'=>$milliseconds,
            'filePath'=>$filePath
        ];
    }

}