<?php

namespace TypechoPlugin\Manager;

use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Radio;
use Utils\Helper;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 插件与主题管理器
 *
 * 提供插件与主题的在线上传安装、卸载、备份、批量管理功能
 *
 * @package Manager
 * @author  落叶
 * @version 1.0
 * @link   
 */
class Plugin implements PluginInterface
{
    /** 插件版本号 */
    public const VERSION = '1.0';

    /** 备份目录相对路径 */
    public const BACKUP_DIR = '/usr/backups';

    /** 默认备份过期时间（秒） */
    public const DEFAULT_BACKUP_EXPIRE = 3600;

    /**
     * 激活插件
     *
     * @throws \Typecho\Plugin\Exception
     */
    public static function activate()
    {
        if (!class_exists('ZipArchive')) {
            throw new \Typecho\Plugin\Exception('需要 PHP ZipArchive 扩展，请在 php.ini 中启用 zip 扩展');
        }

        $pluginDir = __TYPECHO_ROOT_DIR__ . __TYPECHO_PLUGIN_DIR__;
        $themeDir  = __TYPECHO_ROOT_DIR__ . __TYPECHO_THEME_DIR__;
        if (!is_writable($pluginDir)) {
            throw new \Typecho\Plugin\Exception('插件目录不可写: ' . $pluginDir);
        }
        if (!is_writable($themeDir)) {
            throw new \Typecho\Plugin\Exception('主题目录不可写: ' . $themeDir);
        }

        Helper::addAction('manager', 'TypechoPlugin\Manager\Action');

        Helper::addPanel(
            1,
            'Manager/panel.php',
            '插件与主题管理',
            '上传安装或卸载插件和主题，备份插件',
            'administrator'
        );

        self::ensureBackupDir();

        return '管理器 v' . self::VERSION . ' 已激活，请通过后台「控制台 → 插件与主题管理」使用';
    }

    /**
     * 停用插件
     */
    public static function deactivate()
    {
        Helper::removeAction('manager');
        Helper::removePanel(1, 'Manager/panel.php');
    }

    /**
     * 插件配置面板
     *
     * @param Form $form
     */
    public static function config(Form $form)
    {
        $backupExpire = new Text(
            'backupExpire',
            null,
            (string) self::DEFAULT_BACKUP_EXPIRE,
            '备份过期时间（秒）',
            '超过此时间未下载的备份文件将自动清理'
        );
        $form->addInput($backupExpire->addRule('integer', '请输入数字'));

        $maxUpload = new Text(
            'maxUploadSize',
            null,
            '0',
            '最大上传大小（字节，0=不限）',
            '超过此大小的 ZIP 将被拒绝（0 表示仅受 php.ini 限制）'
        );
        $form->addInput($maxUpload->addRule('integer', '请输入数字'));

        $showDetails = new Radio(
            'showDetails',
            ['1' => '显示', '0' => '隐藏'],
            '1',
            '显示详细信息列',
            '是否在列表中显示版本和作者信息'
        );
        $form->addInput($showDetails);
    }

    /**
     * 个人配置面板
     *
     * @param Form $form
     */
    public static function personalConfig(Form $form)
    {
    }

    /**
     * 确保备份目录存在且受保护
     */
    private static function ensureBackupDir(): void
    {
        $backupDir = __TYPECHO_ROOT_DIR__ . self::BACKUP_DIR;

        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }

        $htaccess = $backupDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents(
                $htaccess,
                "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n" .
                "<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n"
            );
        }

        $indexHtml = $backupDir . '/index.html';
        if (!file_exists($indexHtml)) {
            @file_put_contents($indexHtml, '');
        }
    }

    /**
     * 获取插件配置
     *
     * @return array
     */
    public static function getConfig(): array
    {
        $config = Helper::options()->plugin('Manager');
        return [
            'backupExpire'  => isset($config->backupExpire) && $config->backupExpire > 0
                ? (int) $config->backupExpire : self::DEFAULT_BACKUP_EXPIRE,
            'maxUploadSize' => isset($config->maxUploadSize) ? (int) $config->maxUploadSize : 0,
            'showDetails'   => isset($config->showDetails) ? (int) $config->showDetails : 1,
        ];
    }
}
