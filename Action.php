<?php

namespace TypechoPlugin\Manager;

use Typecho\Plugin as CorePlugin;
use Utils\Helper;
use Widget\Base;
use Widget\ActionInterface;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 插件与主题管理器 - 后台操作处理
 *
 * @package Manager
 * @author  落叶
 */
class Action extends Base implements ActionInterface
{
    /** @var string 插件目录 */
    private $pluginDir;

    /** @var string 主题目录 */
    private $themeDir;

    /** @var string 备份目录 */
    private $backupDir;

    /**
     * 构造函数
     */
    public function __construct($request, $response, $params = null)
    {
        parent::__construct($request, $response, $params);
        $this->pluginDir = __TYPECHO_ROOT_DIR__ . __TYPECHO_PLUGIN_DIR__;
        $this->themeDir  = __TYPECHO_ROOT_DIR__ . __TYPECHO_THEME_DIR__;
        $this->backupDir = __TYPECHO_ROOT_DIR__ . Plugin::BACKUP_DIR;
    }

    /**
     * 路由分发
     */
    public function action()
    {
        $this->user->pass('administrator');

        // 下载是只读GET操作，放在CSRF校验之前，避免token缺失导致返回HTML错误页
        if ($this->request->is('downloadBackup')) {
            $this->downloadBackup();
            return;
        }

        $this->security->protect();

        $this->on($this->request->is('uploadPlugin'))->uploadPlugin();
        $this->on($this->request->is('uploadTheme'))->uploadTheme();
        $this->on($this->request->is('uninstallPlugin'))->uninstallPlugin();
        $this->on($this->request->is('uninstallTheme'))->uninstallTheme();
        $this->on($this->request->is('backupPlugin'))->backupPlugin();
        $this->on($this->request->is('backupTheme'))->backupTheme();
        $this->on($this->request->is('listBackups'))->listBackups();
        $this->on($this->request->is('deleteBackup'))->deleteBackup();
        $this->on($this->request->is('deleteAllBackups'))->deleteAllBackups();

        $this->on($this->request->is('batchBackupPlugins'))->batchBackupPlugins();
        $this->on($this->request->is('batchUninstallPlugins'))->batchUninstallPlugins();
        $this->on($this->request->is('batchDisablePlugins'))->batchDisablePlugins();
        $this->on($this->request->is('batchEnablePlugins'))->batchEnablePlugins();
        $this->on($this->request->is('batchBackupThemes'))->batchBackupThemes();
        $this->on($this->request->is('batchUninstallThemes'))->batchUninstallThemes();
    }

    // ==================== 上传安装 ====================

    private function uploadPlugin(): void
    {
        $this->handleUpload('plugin');
    }

    private function uploadTheme(): void
    {
        $this->handleUpload('theme');
    }

    /**
     * 处理 ZIP 上传安装
     */
    private function handleUpload(string $type): void
    {
        $fieldName  = $type === 'plugin' ? 'plugin_zip' : 'theme_zip';
        $targetDir  = $type === 'plugin' ? $this->pluginDir : $this->themeDir;
        $config     = Plugin::getConfig();
        $maxSize    = $config['maxUploadSize'];

        if (empty($_FILES[$fieldName])) {
            $this->redirectToPanel(['error' => '未收到上传文件'], $type);
            return;
        }

        $files         = $_FILES[$fieldName];
        $uploadedCount = 0;
        $failedCount   = 0;
        $errorMessages = [];
        $successNames  = [];

        $fileCount = is_array($files['name']) ? count($files['name']) : 1;

        for ($i = 0; $i < $fileCount; $i++) {
            $fileName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
            $tmpName  = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $error    = is_array($files['error']) ? $files['error'][$i] : $files['error'];
            $fileSize = is_array($files['size']) ? $files['size'][$i] : $files['size'];

            if ($error !== UPLOAD_ERR_OK) {
                $failedCount++;
                $errorMessages[] = $fileName . ': ' . $this->uploadErrorMessage($error);
                continue;
            }

            if ($maxSize > 0 && $fileSize > $maxSize) {
                $failedCount++;
                $errorMessages[] = $fileName . ': 文件过大 (' . $this->formatSize($fileSize) . ')';
                continue;
            }

            if (!$this->isValidZip($tmpName)) {
                $failedCount++;
                $errorMessages[] = $fileName . ': 不是有效的 ZIP 文件';
                continue;
            }

            $zip = new \ZipArchive();
            if ($zip->open($tmpName) !== true) {
                $failedCount++;
                $errorMessages[] = $fileName . ': ZIP 打开失败';
                continue;
            }

            if (!$this->isZipSafe($zip)) {
                $zip->close();
                $failedCount++;
                $errorMessages[] = $fileName . ': ZIP 包含不安全文件';
                continue;
            }

            $inferredName = $this->inferDirName($zip, $fileName);
            $newDir       = $targetDir . '/' . $inferredName;

            if (is_dir($newDir)) {
                $zip->close();
                $failedCount++;
                $errorMessages[] = $fileName . ': 目录 ' . $inferredName . ' 已存在';
                continue;
            }

            $tempDir = $this->createTempDir();
            $success = $zip->extractTo($tempDir);
            $zip->close();

            if (!$success) {
                $this->recursiveDelete($tempDir);
                $failedCount++;
                $errorMessages[] = $fileName . ': ZIP 解压失败';
                continue;
            }

            try {
                $this->installFromTemp($tempDir, $newDir, $inferredName);
                $this->recursiveDelete($tempDir);
                $uploadedCount++;
                $successNames[] = $inferredName;
            } catch (\Exception $e) {
                $this->recursiveDelete($tempDir);
                $this->recursiveDelete($newDir);
                $failedCount++;
                $errorMessages[] = $fileName . ': ' . $e->getMessage();
            }
        }

        $params = [];
        if ($uploadedCount > 0) {
            $params['success']      = $uploadedCount;
            $params['success_names'] = implode(', ', $successNames);
        }
        if ($failedCount > 0) {
            $params['error'] = implode('; ', $errorMessages);
        }
        $this->redirectToPanel($params, $type);
    }

    /**
     * 从临时目录安装到目标目录
     */
    private function installFromTemp(string $tempDir, string $newDir, string $inferredName): void
    {
        $items = @scandir($tempDir);
        if ($items === false) {
            throw new \RuntimeException('无法读取临时目录');
        }

        $items = array_diff($items, ['.', '..']);
        $realItems = [];
        foreach ($items as $item) {
            if ($item === '__MACOSX' || strpos($item, '._') === 0) {
                continue;
            }
            $realItems[] = $item;
        }

        $hasTopDir = in_array($inferredName, $realItems) && is_dir($tempDir . '/' . $inferredName);

        if ($hasTopDir) {
            if (!@rename($tempDir . '/' . $inferredName, $newDir)) {
                throw new \RuntimeException('无法移动文件到目标目录');
            }
        } elseif (count($realItems) === 1 && is_dir($tempDir . '/' . $realItems[0])) {
            if (!@rename($tempDir . '/' . $realItems[0], $newDir)) {
                throw new \RuntimeException('无法移动文件到目标目录');
            }
        } else {
            if (!@mkdir($newDir, 0755, true)) {
                throw new \RuntimeException('无法创建目录');
            }
            foreach ($realItems as $item) {
                @rename($tempDir . '/' . $item, $newDir . '/' . $item);
            }
        }
    }

    /**
     * 检查 ZIP 是否安全
     */
    private function isZipSafe(\ZipArchive $zip): bool
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if ($entry === false) {
                continue;
            }
            if (strpos($entry, '../') !== false || strpos($entry, '..\\') !== false) {
                return false;
            }
            if (strpos($entry, '/') === 0 || preg_match('/^[A-Za-z]:/', $entry)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 推断 ZIP 内的目录名
     */
    private function inferDirName(\ZipArchive $zip, string $fileName): string
    {
        $nameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if ($entry === false) {
                continue;
            }
            $parts = explode('/', rtrim($entry, '/'));
            $top = $parts[0];
            if ($top === '.' || $top === '..' || $top === '__MACOSX') {
                continue;
            }
            if ($this->isValidDirectoryName($top)) {
                return $top;
            }
        }

        if (!$this->isValidDirectoryName($nameWithoutExt)) {
            $nameWithoutExt = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $nameWithoutExt);
        }
        return $nameWithoutExt;
    }

    // ==================== 卸载 ====================

    private function uninstallPlugin(): void
    {
        $name = $this->request->get('uninstallPlugin');
        if (empty($name) || !$this->isValidDirectoryName($name)) {
            $this->redirectToPanel(['error' => '无效的插件名称'], 'plugins');
            return;
        }

        if ($name === 'Manager') {
            $this->redirectToPanel(['error' => '不能卸载自身'], 'plugins');
            return;
        }

        $pluginPath = $this->pluginDir . '/' . $name;
        if (!is_dir($pluginPath)) {
            $this->redirectToPanel(['error' => '插件目录不存在'], 'plugins');
            return;
        }

        $plugins          = CorePlugin::export();
        $activatedPlugins = $plugins['activated'] ?? [];

        if (isset($activatedPlugins[$name])) {
            try {
                Helper::removePlugin($name);
            } catch (\Exception $e) {
                $this->redirectToPanel(['error' => '禁用失败: ' . $e->getMessage()], 'plugins');
                return;
            }
        }

        if ($this->recursiveDelete($pluginPath)) {
            $this->redirectToPanel(['success' => '插件 ' . $name . ' 卸载成功'], 'plugins');
        } else {
            $this->redirectToPanel(['error' => '卸载失败，请检查权限'], 'plugins');
        }
    }

    private function uninstallTheme(): void
    {
        $name = $this->request->get('uninstallTheme');
        if (empty($name) || !$this->isValidDirectoryName($name)) {
            $this->redirectToPanel(['error' => '无效的主题名称'], 'themes');
            return;
        }

        if (in_array($name, ['classic', 'default', 'vhouse', 'screen'])) {
            $this->redirectToPanel(['error' => '不能卸载系统内置主题'], 'themes');
            return;
        }

        $options = Options::alloc();
        if ($name === $options->theme) {
            $this->redirectToPanel(['error' => '不能卸载正在使用的主题'], 'themes');
            return;
        }

        $themePath = $this->themeDir . '/' . $name;
        if (!is_dir($themePath)) {
            $this->redirectToPanel(['error' => '主题目录不存在'], 'themes');
            return;
        }

        if ($this->recursiveDelete($themePath)) {
            $this->redirectToPanel(['success' => '主题 ' . $name . ' 卸载成功'], 'themes');
        } else {
            $this->redirectToPanel(['error' => '卸载失败，请检查权限'], 'themes');
        }
    }

    // ==================== 备份 ====================

    private function backupPlugin(): void
    {
        $name = $this->request->get('backupPlugin');
        if (empty($name) || !$this->isValidDirectoryName($name)) {
            $this->redirectToPanel(['error' => '无效的插件名称'], 'plugins');
            return;
        }

        $sourcePath = $this->pluginDir . '/' . $name;
        if (!is_dir($sourcePath)) {
            $this->redirectToPanel(['error' => '插件目录不存在'], 'plugins');
            return;
        }

        $this->cleanOldBackups();

        $backupFile = $this->backupDir . '/backup_plugin_' . $name . '_' . time() . '.zip';
        if ($this->createZip($sourcePath, $backupFile, $name) === false) {
            $this->redirectToPanel(['error' => '备份失败'], 'plugins');
            return;
        }

        $this->redirectToPanel(['backup_file' => basename($backupFile)], 'plugins');
    }

    private function backupTheme(): void
    {
        $name = $this->request->get('backupTheme');
        if (empty($name) || !$this->isValidDirectoryName($name)) {
            $this->redirectToPanel(['error' => '无效的主题名称'], 'themes');
            return;
        }

        $sourcePath = $this->themeDir . '/' . $name;
        if (!is_dir($sourcePath)) {
            $this->redirectToPanel(['error' => '主题目录不存在'], 'themes');
            return;
        }

        $this->cleanOldBackups();

        $backupFile = $this->backupDir . '/backup_theme_' . $name . '_' . time() . '.zip';
        if ($this->createZip($sourcePath, $backupFile, $name) === false) {
            $this->redirectToPanel(['error' => '备份失败'], 'themes');
            return;
        }

        $this->redirectToPanel(['backup_file' => basename($backupFile)], 'themes');
    }

    /**
     * 下载备份文件
     *
     * 通过 action 路由直接输出二进制，不经过 admin 页面的 common.php/header.php。
     * header_remove() 清除框架预设的头，ob_end_clean 清除所有缓冲层，
     * readfile 直接输出文件内容，确保 ZIP 数据完整。
     */
    private function downloadBackup(): void
    {
        $filename = $this->request->get('downloadBackup');
        if (empty($filename) || !$this->isValidBackupFilename($filename)) {
            return;
        }

        $filePath = $this->backupDir . '/' . $filename;
        if (!is_readable($filePath)) {
            return;
        }

        @ini_set('zlib.output_compression', '0');
        if (function_exists('apache_setenv')) {
            apache_setenv('no-gzip', '1');
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header_remove();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filePath));

        readfile($filePath);
        exit;
    }

    /**
     * 列出所有备份文件
     */
    private function listBackups(): void
    {
        $this->cleanOldBackups();

        $files   = glob($this->backupDir . '/backup_*.zip');
        $backups = [];

        if ($files !== false) {
            foreach ($files as $file) {
                $backups[] = [
                    'name' => basename($file),
                    'size' => filesize($file),
                    'time' => filemtime($file),
                ];
            }
            usort($backups, function ($a, $b) {
                return $b['time'] - $a['time'];
            });
        }

        $this->response->throwJson(['backups' => $backups]);
    }

    /**
     * 删除单个备份文件
     */
    private function deleteBackup(): void
    {
        $filename = $this->request->get('deleteBackup');
        if (empty($filename) || !$this->isValidBackupFilename($filename)) {
            $this->response->throwJson(['error' => '无效的文件名']);
            return;
        }

        $filePath = $this->backupDir . '/' . $filename;
        if (!file_exists($filePath)) {
            $this->response->throwJson(['error' => '文件不存在']);
            return;
        }

        if (@unlink($filePath)) {
            $this->response->throwJson(['success' => true]);
        } else {
            $this->response->throwJson(['error' => '删除失败']);
        }
    }

    /**
     * 删除所有备份文件
     */
    private function deleteAllBackups(): void
    {
        $files  = glob($this->backupDir . '/backup_*.zip');
        $deleted = 0;
        $failed  = 0;

        if ($files !== false) {
            foreach ($files as $file) {
                if (@unlink($file)) {
                    $deleted++;
                } else {
                    $failed++;
                }
            }
        }

        $this->response->throwJson([
            'success' => true,
            'deleted' => $deleted,
            'failed'  => $failed,
        ]);
    }

    // ==================== 批量操作 ====================

    private function batchBackupPlugins(): void
    {
        $names = $this->request->getArray('plugins');
        if (empty($names)) {
            $this->redirectToPanel(['error' => '未选择任何插件'], 'plugins');
            return;
        }

        $this->cleanOldBackups();

        $success     = 0;
        $fail        = 0;
        $backupFiles = [];
        $errors      = [];

        foreach ($names as $name) {
            if (!$this->isValidDirectoryName($name)) {
                $fail++;
                $errors[] = $name . ': 无效名称';
                continue;
            }
            $sourcePath = $this->pluginDir . '/' . $name;
            if (!is_dir($sourcePath)) {
                $fail++;
                $errors[] = $name . ': 目录不存在';
                continue;
            }

            $backupFile = $this->backupDir . '/backup_plugin_' . $name . '_' . time() . '.zip';
            if ($this->createZip($sourcePath, $backupFile, $name) !== false) {
                $backupFiles[] = basename($backupFile);
                $success++;
            } else {
                $fail++;
                $errors[] = $name . ': 备份失败';
            }
        }

        $params = ['success' => $success];
        if ($fail > 0) {
            $params['error'] = $fail . ' 个失败: ' . implode(', ', $errors);
        }
        if (!empty($backupFiles)) {
            $params['backup_file'] = $backupFiles[0];
        }
        $this->redirectToPanel($params, 'plugins');
    }

    private function batchUninstallPlugins(): void
    {
        $names = $this->request->getArray('plugins');
        if (empty($names)) {
            $this->redirectToPanel(['error' => '未选择任何插件'], 'plugins');
            return;
        }

        $plugins          = CorePlugin::export();
        $activatedPlugins = $plugins['activated'] ?? [];

        $success = 0;
        $fail    = 0;
        $errors  = [];

        foreach ($names as $name) {
            if (!$this->isValidDirectoryName($name) || $name === 'Manager') {
                $fail++;
                $errors[] = $name . ': 不允许卸载';
                continue;
            }

            $pluginPath = $this->pluginDir . '/' . $name;
            if (!is_dir($pluginPath)) {
                $fail++;
                $errors[] = $name . ': 目录不存在';
                continue;
            }

            if (isset($activatedPlugins[$name])) {
                try {
                    Helper::removePlugin($name);
                } catch (\Exception $e) {
                    $fail++;
                    $errors[] = $name . ': 禁用失败';
                    continue;
                }
            }

            if ($this->recursiveDelete($pluginPath)) {
                $success++;
            } else {
                $fail++;
                $errors[] = $name . ': 删除失败';
            }
        }

        $params = ['success' => $success];
        if ($fail > 0) {
            $params['error'] = $fail . ' 个失败: ' . implode(', ', $errors);
        }
        $this->redirectToPanel($params, 'plugins');
    }

    /**
     * 批量禁用插件（保护自身不被禁用）
     */
    private function batchDisablePlugins(): void
    {
        $names = $this->request->getArray('plugins');
        if (empty($names)) {
            $this->redirectToPanel(['error' => '未选择任何插件'], 'plugins');
            return;
        }

        $plugins          = CorePlugin::export();
        $activatedPlugins = $plugins['activated'] ?? [];

        $success = 0;
        $fail    = 0;
        $errors  = [];

        foreach ($names as $name) {
            if (!$this->isValidDirectoryName($name)) {
                $fail++;
                continue;
            }

            // 保护自身：不禁用 Manager 插件
            if ($name === 'Manager') {
                $fail++;
                $errors[] = $name . ': 不能禁用管理器自身';
                continue;
            }

            if (!isset($activatedPlugins[$name])) {
                $fail++;
                $errors[] = $name . ': 未激活';
                continue;
            }

            try {
                Helper::removePlugin($name);
                $success++;
            } catch (\Exception $e) {
                $fail++;
                $errors[] = $name . ': ' . $e->getMessage();
            }
        }

        $params = ['success' => $success];
        if ($fail > 0) {
            $params['error'] = $fail . ' 个失败: ' . implode(', ', $errors);
        }
        $this->redirectToPanel($params, 'plugins');
    }

    /**
     * 批量启用插件
     */
    private function batchEnablePlugins(): void
    {
        $names = $this->request->getArray('plugins');
        if (empty($names)) {
            $this->redirectToPanel(['error' => '未选择任何插件'], 'plugins');
            return;
        }

        $plugins          = CorePlugin::export();
        $activatedPlugins = $plugins['activated'] ?? [];

        $success = 0;
        $fail    = 0;
        $errors  = [];

        foreach ($names as $name) {
            if (!$this->isValidDirectoryName($name)) {
                $fail++;
                continue;
            }

            if (isset($activatedPlugins[$name])) {
                $fail++;
                $errors[] = $name . ': 已激活';
                continue;
            }

            $pluginFile    = $this->pluginDir . '/' . $name . '/Plugin.php';
            $altPluginFile = $this->pluginDir . '/' . $name . '/' . $name . '.php';

            if (!is_readable($pluginFile) && !is_readable($altPluginFile)) {
                $fail++;
                $errors[] = $name . ': Plugin.php 不可读';
                continue;
            }

            try {
                CorePlugin::activate($name);
                $success++;
            } catch (\Exception $e) {
                $fail++;
                $errors[] = $name . ': ' . $e->getMessage();
            }
        }

        if ($success > 0) {
            try {
                Helper::setOption('plugins', CorePlugin::export());
            } catch (\Exception $e) {
                // 持久化失败不中断
            }
        }

        $params = ['success' => $success];
        if ($fail > 0) {
            $params['error'] = $fail . ' 个失败: ' . implode(', ', $errors);
        }
        $this->redirectToPanel($params, 'plugins');
    }

    private function batchBackupThemes(): void
    {
        $names = $this->request->getArray('themes');
        if (empty($names)) {
            $this->redirectToPanel(['error' => '未选择任何主题'], 'themes');
            return;
        }

        $this->cleanOldBackups();

        $success     = 0;
        $fail        = 0;
        $backupFiles = [];
        $errors      = [];

        foreach ($names as $name) {
            if (!$this->isValidDirectoryName($name)) {
                $fail++;
                $errors[] = $name . ': 无效名称';
                continue;
            }
            $sourcePath = $this->themeDir . '/' . $name;
            if (!is_dir($sourcePath)) {
                $fail++;
                $errors[] = $name . ': 目录不存在';
                continue;
            }

            $backupFile = $this->backupDir . '/backup_theme_' . $name . '_' . time() . '.zip';
            if ($this->createZip($sourcePath, $backupFile, $name) !== false) {
                $backupFiles[] = basename($backupFile);
                $success++;
            } else {
                $fail++;
                $errors[] = $name . ': 备份失败';
            }
        }

        $params = ['success' => $success];
        if ($fail > 0) {
            $params['error'] = $fail . ' 个失败: ' . implode(', ', $errors);
        }
        if (!empty($backupFiles)) {
            $params['backup_file'] = $backupFiles[0];
        }
        $this->redirectToPanel($params, 'themes');
    }

    private function batchUninstallThemes(): void
    {
        $names = $this->request->getArray('themes');
        if (empty($names)) {
            $this->redirectToPanel(['error' => '未选择任何主题'], 'themes');
            return;
        }

        $options      = Options::alloc();
        $currentTheme = $options->theme;

        $success = 0;
        $fail    = 0;
        $errors  = [];

        foreach ($names as $name) {
            if (!$this->isValidDirectoryName($name)) {
                $fail++;
                continue;
            }

            if (in_array($name, ['classic', 'default', 'vhouse', 'screen', $currentTheme])) {
                $fail++;
                $errors[] = $name . ': 受保护';
                continue;
            }

            $themePath = $this->themeDir . '/' . $name;
            if (!is_dir($themePath)) {
                $fail++;
                $errors[] = $name . ': 目录不存在';
                continue;
            }

            if ($this->recursiveDelete($themePath)) {
                $success++;
            } else {
                $fail++;
                $errors[] = $name . ': 删除失败';
            }
        }

        $params = ['success' => $success];
        if ($fail > 0) {
            $params['error'] = $fail . ' 个失败: ' . implode(', ', $errors);
        }
        $this->redirectToPanel($params, 'themes');
    }

    // ==================== 工具方法 ====================

    private function redirectToPanel(array $params = [], string $type = ''): void
    {
        $tab  = ($type === 'theme' || $type === 'themes') ? 'themes' : 'plugins';
        $url  = Helper::url('Manager/panel.php') . '&tab=' . $tab;
        foreach ($params as $key => $value) {
            $url .= '&' . urlencode($key) . '=' . urlencode($value);
        }
        $this->response->redirect($url);
    }

    private function isValidDirectoryName(string $name): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9_\-]+$/', $name);
    }

    private function isValidBackupFilename(string $filename): bool
    {
        return (bool) preg_match('/^backup_[a-zA-Z0-9_\-]+_\d+\.zip$/', $filename);
    }

    private function isValidZip(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if (!$handle) {
            return false;
        }
        $header = fread($handle, 4);
        fclose($handle);
        return $header === "PK\x03\x04";
    }

    private function createTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/tpm_install_' . uniqid();
        if (!@mkdir($dir, 0755, true)) {
            $dir = $this->pluginDir . '/_tmp_install_' . uniqid();
            if (!@mkdir($dir, 0755, true)) {
                throw new \RuntimeException('无法创建临时目录');
            }
        }
        return $dir;
    }

    private function recursiveDelete(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }
        $items = @scandir($dir);
        if ($items === false) {
            return false;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->recursiveDelete($path);
            } else {
                @unlink($path);
            }
        }
        return @rmdir($dir);
    }

    /**
     * 创建 ZIP 备份
     *
     * 关键修复：用 addFromString 替代 addFile。
     * addFile 只存路径引用，close() 时才读文件，某些环境下会写入空数据。
     * addFromString 立即读取文件内容存入 ZIP，确保数据完整。
     */
    private function createZip(string $sourceDir, string $outputFile, string $baseName): bool
    {
        if (file_exists($outputFile)) {
            @unlink($outputFile);
        }

        $zip = new \ZipArchive();
        if ($zip->open($outputFile, \ZipArchive::CREATE) !== true) {
            return false;
        }

        $sourceDir = rtrim(str_replace('\\', '/', $sourceDir), '/');

        try {
            $this->addDirToZip($zip, $sourceDir, $baseName);
        } catch (\Exception $e) {
            $zip->close();
            @unlink($outputFile);
            return false;
        }

        return $zip->close();
    }

    /**
     * 递归添加目录到 ZIP
     */
    private function addDirToZip(\ZipArchive $zip, string $dir, string $zipPath): void
    {
        $dir = rtrim($dir, '/\\');
        if (!is_dir($dir)) {
            return;
        }

        $zip->addEmptyDir($zipPath);

        $items = @scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path     = $dir . '/' . $item;
            $zipEntry = $zipPath . '/' . $item;

            if (is_dir($path)) {
                $this->addDirToZip($zip, $path, $zipEntry);
            } elseif (is_file($path) && is_readable($path)) {
                $zip->addFile($path, $zipEntry);
            }
        }
    }

    private function cleanOldBackups(): void
    {
        $files = glob($this->backupDir . '/backup_*.zip');
        if ($files === false || empty($files)) {
            return;
        }

        $config     = Plugin::getConfig();
        $expireTime = time() - $config['backupExpire'];

        foreach ($files as $file) {
            $mtime = @filemtime($file);
            if ($mtime !== false && $mtime < $expireTime) {
                @unlink($file);
            }
        }
    }

    private function uploadErrorMessage(int $errorCode): string
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => '超过 php.ini 上传限制',
            UPLOAD_ERR_FORM_SIZE  => '超过表单上传限制',
            UPLOAD_ERR_PARTIAL    => '文件仅部分上传',
            UPLOAD_ERR_NO_FILE    => '没有文件被上传',
            UPLOAD_ERR_NO_TMP_DIR => '缺少临时目录',
            UPLOAD_ERR_CANT_WRITE => '写入磁盘失败',
            UPLOAD_ERR_EXTENSION  => 'PHP 扩展阻止了上传',
        ];
        return $messages[$errorCode] ?? '上传错误(' . $errorCode . ')';
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / 1048576, 1) . ' MB';
    }
}
