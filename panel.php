<?php

include_once 'common.php';

$user = \Widget\User::alloc();
$user->pass('administrator');

include_once 'header.php';
include_once 'menu.php';

$options         = \Widget\Options::alloc();
$plugins         = \Typecho\Plugin::export();
$activatedPlugins = $plugins['activated'] ?? [];
$currentTheme    = $options->theme;

$activeTab    = isset($_GET['tab']) ? $_GET['tab'] : 'plugins';
$backupFile   = isset($_GET['backup_file']) ? basename($_GET['backup_file']) : '';
$successMsg   = isset($_GET['success']) ? $_GET['success'] : '';
$successNames = isset($_GET['success_names']) ? $_GET['success_names'] : '';
$errorMsg     = isset($_GET['error']) ? $_GET['error'] : '';

$pluginDir = __TYPECHO_ROOT_DIR__ . __TYPECHO_PLUGIN_DIR__;
$themeDir  = __TYPECHO_ROOT_DIR__ . __TYPECHO_THEME_DIR__;

try {
    $pluginConfig = \TypechoPlugin\Manager\Plugin::getConfig();
    $showDetails  = $pluginConfig['showDetails'];
} catch (\Exception $e) {
    $showDetails = 1;
}

// 扫描已安装插件
$installedPlugins = [];
$dirs = glob($pluginDir . '/*', GLOB_ONLYDIR);
if ($dirs !== false) {
    foreach ($dirs as $dir) {
        $name = basename($dir);
        if (strpos($name, '_tmp_install_') === 0) {
            continue;
        }
        $pluginFile = $dir . '/Plugin.php';
        $info = [
            'name'        => $name,
            'title'       => $name,
            'version'     => '',
            'author'      => '',
            'description' => '',
            'homepage'    => '',
            'active'      => isset($activatedPlugins[$name]),
            'hasConfig'   => false,
        ];
        if (is_readable($pluginFile)) {
            try {
                $parsed = \Typecho\Plugin::parseInfo($pluginFile);
                if ($parsed !== false) {
                    $info['title']       = $parsed['title'] ?? $name;
                    $info['version']     = $parsed['version'] ?? '';
                    $info['author']      = $parsed['author'] ?? '';
                    $info['description'] = $parsed['description'] ?? '';
                    $info['homepage']    = $parsed['homepage'] ?? '';
                    $info['hasConfig']   = $parsed['config'] ?? false;
                }
            } catch (\Throwable $e) {
            }
        }
        $installedPlugins[$name] = $info;
    }
}
uasort($installedPlugins, function ($a, $b) {
    if ($a['active'] !== $b['active']) { return $a['active'] ? -1 : 1; }
    return strcasecmp($a['title'], $b['title']);
});

// 扫描已安装主题
$installedThemes = [];
$themeDirs = glob($themeDir . '/*', GLOB_ONLYDIR);
if ($themeDirs !== false) {
    foreach ($themeDirs as $dir) {
        $name = basename($dir);
        if (strpos($name, '_tmp_install_') === 0) { continue; }
        $info = [
            'name'    => $name,
            'title'   => $name,
            'version' => '',
            'author'  => '',
            'active'  => $name === $currentTheme,
        ];
        $indexFile = $dir . '/index.php';
        if (is_readable($indexFile)) {
            try {
                $parsed = \Typecho\Plugin::parseInfo($indexFile);
                if ($parsed !== false) {
                    $info['title']   = $parsed['title'] ?? $name;
                    $info['version'] = $parsed['version'] ?? '';
                    $info['author']  = $parsed['author'] ?? '';
                }
            } catch (\Throwable $e) {
            }
        }
        $installedThemes[$name] = $info;
    }
}
uasort($installedThemes, function ($a, $b) {
    if ($a['active'] !== $b['active']) { return $a['active'] ? -1 : 1; }
    return strcasecmp($a['title'], $b['title']);
});

$pluginTotal  = count($installedPlugins);
$pluginActive = count(array_filter($installedPlugins, function ($p) { return $p['active']; }));
$themeTotal   = count($installedThemes);

$baseUrl    = \Utils\Helper::url('Manager/panel.php');
$actionBaseUrl = \Typecho\Router::url(
    'do',
    ['action' => 'manager'],
    \Typecho\Common::url('index.php', $options->rootUrl)
);

$uploadPluginUrl         = $actionBaseUrl . '?uploadPlugin=1';
$uploadThemeUrl          = $actionBaseUrl . '?uploadTheme=1';
$uninstallPluginUrl      = $actionBaseUrl . '?uninstallPlugin=';
$uninstallThemeUrl       = $actionBaseUrl . '?uninstallTheme=';
$backupPluginUrl         = $actionBaseUrl . '?backupPlugin=';
$backupThemeUrl          = $actionBaseUrl . '?backupTheme=';
$downloadBackupUrl       = $actionBaseUrl . '?downloadBackup=';
$deleteBackupUrl         = $actionBaseUrl . '?deleteBackup=';
$deleteAllBackupsUrl     = $actionBaseUrl . '?deleteAllBackups=1';
$listBackupsUrl          = $actionBaseUrl . '?listBackups=1';

$batchBackupPluginsUrl    = $actionBaseUrl . '?batchBackupPlugins=1';
$batchUninstallPluginsUrl = $actionBaseUrl . '?batchUninstallPlugins=1';
$batchDisablePluginsUrl   = $actionBaseUrl . '?batchDisablePlugins=1';
$batchEnablePluginsUrl    = $actionBaseUrl . '?batchEnablePlugins=1';
$batchBackupThemesUrl     = $actionBaseUrl . '?batchBackupThemes=1';
$batchUninstallThemesUrl  = $actionBaseUrl . '?batchUninstallThemes=1';

$securityToken = $security->getToken($request->getRequestUrl());
$tokenKey = '_';
?>
<style>
:root {
    --tpm-primary: #4f46e5;
    --tpm-primary-light: #6366f1;
    --tpm-primary-bg: #eef2ff;
    --tpm-success: #16a34a;
    --tpm-success-light: #22c55e;
    --tpm-success-bg: #f0fdf4;
    --tpm-danger: #dc2626;
    --tpm-danger-light: #ef4444;
    --tpm-danger-bg: #fef2f2;
    --tpm-warning: #d97706;
    --tpm-warning-bg: #fffbeb;
    --tpm-gray-50: #f9fafb;
    --tpm-gray-100: #f3f4f6;
    --tpm-gray-200: #e5e7eb;
    --tpm-gray-300: #d1d5db;
    --tpm-gray-400: #9ca3af;
    --tpm-gray-500: #6b7280;
    --tpm-gray-600: #4b5563;
    --tpm-gray-700: #374151;
    --tpm-gray-800: #1f2937;
    --tpm-gray-900: #111827;
    --tpm-radius: 8px;
    --tpm-shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05);
    --tpm-shadow: 0 1px 3px 0 rgba(0,0,0,0.1), 0 1px 2px 0 rgba(0,0,0,0.06);
    --tpm-shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
    --tpm-shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
}

.tpm-container { max-width: 1200px; margin: 0 auto; padding: 0 16px; }

.tpm-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
.tpm-stat-card { background: #fff; border: 1px solid var(--tpm-gray-200); border-radius: var(--tpm-radius); padding: 16px 20px; box-shadow: var(--tpm-shadow-sm); display: flex; align-items: center; gap: 12px; }
.tpm-stat-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
.tpm-stat-icon.purple { background: var(--tpm-primary-bg); color: var(--tpm-primary); }
.tpm-stat-icon.green { background: var(--tpm-success-bg); color: var(--tpm-success); }
.tpm-stat-icon.blue { background: #eff6ff; color: #2563eb; }
.tpm-stat-icon.amber { background: var(--tpm-warning-bg); color: var(--tpm-warning); }
.tpm-stat-info { flex: 1; min-width: 0; }
.tpm-stat-value { font-size: 24px; font-weight: 700; color: var(--tpm-gray-900); line-height: 1.2; }
.tpm-stat-label { font-size: 12px; color: var(--tpm-gray-500); margin-top: 2px; }

.tpm-tabs { display: flex; border-bottom: 2px solid var(--tpm-gray-200); margin-bottom: 20px; gap: 4px; }
.tpm-tab { padding: 10px 20px; cursor: pointer; border: none; background: none; font-size: 14px; color: var(--tpm-gray-500); border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.2s; font-weight: 500; border-radius: 6px 6px 0 0; }
.tpm-tab.active { color: var(--tpm-primary); border-bottom-color: var(--tpm-primary); font-weight: 600; }
.tpm-tab:hover:not(.active) { color: var(--tpm-gray-700); background: var(--tpm-gray-50); }
.tpm-tab-badge { display: inline-block; background: var(--tpm-gray-200); color: var(--tpm-gray-600); font-size: 11px; padding: 1px 7px; border-radius: 10px; margin-left: 6px; font-weight: 500; }
.tpm-tab.active .tpm-tab-badge { background: var(--tpm-primary-bg); color: var(--tpm-primary); }

.tpm-toast-container { position: fixed; top: 16px; right: 16px; z-index: 10000; display: flex; flex-direction: column; gap: 8px; max-width: 380px; }
.tpm-toast { display: flex; align-items: flex-start; gap: 10px; padding: 12px 16px; border-radius: var(--tpm-radius); box-shadow: var(--tpm-shadow-lg); animation: tpm-slide-in 0.3s ease; font-size: 13px; line-height: 1.5; }
.tpm-toast.success { background: #fff; border-left: 4px solid var(--tpm-success); color: var(--tpm-gray-700); }
.tpm-toast.error { background: #fff; border-left: 4px solid var(--tpm-danger); color: var(--tpm-gray-700); }
.tpm-toast.info { background: #fff; border-left: 4px solid var(--tpm-primary); color: var(--tpm-gray-700); }
.tpm-toast .toast-icon { font-size: 18px; flex-shrink: 0; line-height: 1; }
.tpm-toast .toast-close { cursor: pointer; color: var(--tpm-gray-400); font-size: 16px; margin-left: auto; flex-shrink: 0; background: none; border: none; }
@keyframes tpm-slide-in { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
@keyframes tpm-fade-out { from { opacity: 1; } to { opacity: 0; transform: translateX(20px); } }

.tpm-upload-zone { border: 2px dashed var(--tpm-gray-300); border-radius: var(--tpm-radius); padding: 28px; text-align: center; margin-bottom: 20px; transition: all 0.3s; position: relative; background: var(--tpm-gray-50); cursor: pointer; }
.tpm-upload-zone:hover { border-color: var(--tpm-primary); background: var(--tpm-primary-bg); }
.tpm-upload-zone.dragover { border-color: var(--tpm-primary); background: var(--tpm-primary-bg); transform: scale(1.01); }
.tpm-upload-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.tpm-upload-icon { width: 48px; height: 48px; margin: 0 auto 8px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: var(--tpm-primary-bg); color: var(--tpm-primary); font-size: 24px; }
.tpm-upload-text { color: var(--tpm-gray-700); font-size: 14px; font-weight: 500; }
.tpm-upload-hint { color: var(--tpm-gray-400); font-size: 12px; margin-top: 4px; }

.tpm-file-list { margin-top: 14px; }
.tpm-file-item { display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; background: #fff; border: 1px solid var(--tpm-gray-200); border-radius: 6px; margin-bottom: 6px; font-size: 13px; }
.tpm-file-item .file-info { display: flex; align-items: center; gap: 8px; }
.tpm-file-item .file-icon { color: var(--tpm-primary); }
.tpm-file-item .file-size { color: var(--tpm-gray-400); font-size: 11px; }
.tpm-file-item .remove-file { cursor: pointer; color: var(--tpm-danger); font-size: 18px; line-height: 1; margin-left: 8px; flex-shrink: 0; padding: 2px 6px; border-radius: 4px; transition: background 0.2s; background: none; border: none; }
.tpm-file-item .remove-file:hover { background: var(--tpm-danger-bg); }

.tpm-submit-row { margin-top: 14px; display: flex; gap: 8px; justify-content: center; }

.tpm-btn { display: inline-flex; align-items: center; gap: 4px; padding: 6px 14px; border: 1px solid var(--tpm-gray-300); border-radius: 6px; background: #fff; cursor: pointer; font-size: 13px; text-decoration: none; color: var(--tpm-gray-700); transition: all 0.2s; white-space: nowrap; font-weight: 500; }
.tpm-btn:hover { border-color: var(--tpm-primary); color: var(--tpm-primary); background: var(--tpm-primary-bg); }
.tpm-btn.primary { color: #fff; background: var(--tpm-primary); border-color: var(--tpm-primary); }
.tpm-btn.primary:hover { background: var(--tpm-primary-light); border-color: var(--tpm-primary-light); color: #fff; }
.tpm-btn.success { color: #fff; background: var(--tpm-success); border-color: var(--tpm-success); }
.tpm-btn.success:hover { background: var(--tpm-success-light); border-color: var(--tpm-success-light); color: #fff; }
.tpm-btn.danger { color: var(--tpm-danger); border-color: var(--tpm-gray-300); }
.tpm-btn.danger:hover { background: var(--tpm-danger-bg); border-color: var(--tpm-danger); color: var(--tpm-danger); }
.tpm-btn.danger.solid { color: #fff; background: var(--tpm-danger); border-color: var(--tpm-danger); }
.tpm-btn.danger.solid:hover { background: var(--tpm-danger-light); border-color: var(--tpm-danger-light); color: #fff; }
.tpm-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.tpm-btn-sm { padding: 4px 10px; font-size: 12px; }
.tpm-btn-lg { padding: 10px 28px; font-size: 14px; }

.tpm-toolbar { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; align-items: center; }
.tpm-toolbar .tpm-search-box { margin-left: auto; position: relative; }
.tpm-toolbar .tpm-search-box input { padding: 6px 12px 6px 32px; border: 1px solid var(--tpm-gray-300); border-radius: 6px; font-size: 13px; width: 200px; transition: all 0.2s; }
.tpm-toolbar .tpm-search-box input:focus { outline: none; border-color: var(--tpm-primary); box-shadow: 0 0 0 3px var(--tpm-primary-bg); }
.tpm-toolbar .tpm-search-box .search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--tpm-gray-400); font-size: 14px; }
.tpm-select-all { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; cursor: pointer; font-size: 13px; color: var(--tpm-gray-600); user-select: none; }
.tpm-select-all input { cursor: pointer; }

.tpm-table-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: var(--tpm-radius); border: 1px solid var(--tpm-gray-200); background: #fff; }
.tpm-table { width: 100%; border-collapse: collapse; min-width: 600px; }
.tpm-table th { background: var(--tpm-gray-50); padding: 10px 14px; text-align: left; font-size: 12px; color: var(--tpm-gray-500); border-bottom: 1px solid var(--tpm-gray-200); white-space: nowrap; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
.tpm-table td { padding: 12px 14px; border-bottom: 1px solid var(--tpm-gray-100); font-size: 13px; color: var(--tpm-gray-700); }
.tpm-table tr:last-child td { border-bottom: none; }
.tpm-table tr:hover { background: var(--tpm-gray-50); }
.tpm-table .col-check { width: 40px; text-align: center; }
.tpm-table .col-check input { cursor: pointer; }
.tpm-table .col-actions { width: 180px; white-space: nowrap; }
.tpm-table .plugin-name { font-weight: 600; color: var(--tpm-gray-900); }
.tpm-table .plugin-dir { color: var(--tpm-gray-400); font-size: 11px; margin-top: 2px; }
.tpm-table .plugin-desc { color: var(--tpm-gray-500); font-size: 12px; margin-top: 2px; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.tpm-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; white-space: nowrap; }
.tpm-badge.active { background: var(--tpm-success-bg); color: var(--tpm-success); }
.tpm-badge.inactive { background: var(--tpm-gray-100); color: var(--tpm-gray-500); }
.tpm-badge.current { background: var(--tpm-primary-bg); color: var(--tpm-primary); }
.tpm-badge .badge-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

.tpm-backup-box { background: var(--tpm-success-bg); border: 1px solid #bbf7d0; border-radius: var(--tpm-radius); padding: 12px 16px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
.tpm-backup-box .backup-info { display: flex; align-items: center; gap: 8px; color: var(--tpm-success); font-size: 13px; }
.tpm-backup-box .download-link { color: var(--tpm-primary); font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; padding: 6px 16px; border: 1px solid var(--tpm-primary); border-radius: 6px; font-size: 13px; transition: all 0.2s; }
.tpm-backup-box .download-link:hover { background: var(--tpm-primary); color: #fff; }

.tpm-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; padding: 16px; backdrop-filter: blur(2px); }
.tpm-modal-overlay.show { display: flex; animation: tpm-fade-in 0.2s ease; }
@keyframes tpm-fade-in { from { opacity: 0; } to { opacity: 1; } }
.tpm-modal { background: #fff; border-radius: 12px; padding: 24px; max-width: 440px; width: 100%; box-shadow: var(--tpm-shadow-lg); animation: tpm-scale-in 0.2s ease; }
@keyframes tpm-scale-in { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.tpm-modal h3 { margin: 0 0 12px; font-size: 18px; font-weight: 700; color: var(--tpm-gray-900); }
.tpm-modal .modal-icon { width: 48px; height: 48px; border-radius: 50%; background: var(--tpm-danger-bg); color: var(--tpm-danger); display: flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 16px; }
.tpm-modal p { margin: 0 0 20px; color: var(--tpm-gray-600); font-size: 14px; line-height: 1.6; }
.tpm-modal .modal-items { background: var(--tpm-gray-50); border-radius: 6px; padding: 8px 12px; margin-bottom: 20px; max-height: 120px; overflow-y: auto; }
.tpm-modal .modal-items .modal-item { padding: 4px 0; font-size: 13px; color: var(--tpm-gray-700); border-bottom: 1px solid var(--tpm-gray-100); }
.tpm-modal .modal-items .modal-item:last-child { border-bottom: none; }
.tpm-modal .modal-actions { display: flex; gap: 8px; justify-content: flex-end; }
.tpm-modal .countdown { display: inline-block; min-width: 20px; color: var(--tpm-danger); font-weight: bold; text-align: center; }

.tpm-empty { text-align: center; padding: 48px 20px; color: var(--tpm-gray-400); }
.tpm-empty .empty-icon { font-size: 48px; margin-bottom: 12px; opacity: 0.5; }
.tpm-empty .empty-text { font-size: 14px; }

.tpm-backup-list { display: flex; flex-direction: column; gap: 8px; }
.tpm-backup-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: #fff; border: 1px solid var(--tpm-gray-200); border-radius: var(--tpm-radius); transition: all 0.2s; }
.tpm-backup-item:hover { border-color: var(--tpm-primary); box-shadow: var(--tpm-shadow); }
.tpm-backup-item .backup-file-icon { width: 40px; height: 40px; border-radius: 8px; background: var(--tpm-primary-bg); color: var(--tpm-primary); display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
.tpm-backup-item .backup-info { flex: 1; min-width: 0; }
.tpm-backup-item .backup-name { font-size: 13px; font-weight: 600; color: var(--tpm-gray-900); word-break: break-all; }
.tpm-backup-item .backup-meta { font-size: 11px; color: var(--tpm-gray-400); margin-top: 2px; }
.tpm-backup-item .backup-actions { display: flex; gap: 4px; flex-shrink: 0; }

@media (max-width: 768px) {
    .tpm-container { padding: 0 10px; }
    .tpm-stats { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .tpm-stat-card { padding: 12px; }
    .tpm-stat-icon { width: 36px; height: 36px; font-size: 18px; }
    .tpm-stat-value { font-size: 20px; }
    .tpm-tab { padding: 8px 14px; font-size: 13px; }
    .tpm-upload-zone { padding: 20px 12px; }
    .tpm-table .col-actions { width: auto; }
    .tpm-table td, .tpm-table th { padding: 8px 8px; font-size: 12px; }
    .tpm-toolbar { gap: 4px; }
    .tpm-toolbar .tpm-search-box { margin-left: 0; width: 100%; margin-top: 4px; }
    .tpm-toolbar .tpm-search-box input { width: 100%; }
    .tpm-backup-item { flex-wrap: wrap; }
}
@media (max-width: 480px) {
    .tpm-stats { grid-template-columns: 1fr 1fr; }
    .tpm-tab { padding: 8px 10px; font-size: 12px; }
    .tpm-upload-zone { padding: 16px 8px; }
    .tpm-btn { padding: 5px 10px; font-size: 12px; }
    .tpm-btn-lg { width: 100%; justify-content: center; }
    .tpm-submit-row { flex-direction: column; }
    .tpm-table td, .tpm-table th { padding: 6px 4px; font-size: 11px; }
    .tpm-table .col-check { width: 30px; }
    .tpm-col-hide-mobile { display: none; }
    .tpm-badge { padding: 2px 6px; font-size: 10px; }
    .tpm-modal { padding: 16px; }
}
</style>

<div class="tpm-container">
    <div class="tpm-stats">
        <div class="tpm-stat-card">
            <div class="tpm-stat-icon purple">&#128268;</div>
            <div class="tpm-stat-info"><div class="tpm-stat-value"><?php echo $pluginTotal; ?></div><div class="tpm-stat-label">已安装插件</div></div>
        </div>
        <div class="tpm-stat-card">
            <div class="tpm-stat-icon green">&#9989;</div>
            <div class="tpm-stat-info"><div class="tpm-stat-value"><?php echo $pluginActive; ?></div><div class="tpm-stat-label">已启用插件</div></div>
        </div>
        <div class="tpm-stat-card">
            <div class="tpm-stat-icon blue">&#127968;</div>
            <div class="tpm-stat-info"><div class="tpm-stat-value"><?php echo $themeTotal; ?></div><div class="tpm-stat-label">已安装主题</div></div>
        </div>
        <div class="tpm-stat-card">
            <div class="tpm-stat-icon amber">&#127912;</div>
            <div class="tpm-stat-info"><div class="tpm-stat-value" style="font-size:16px;word-break:break-all;"><?php echo htmlspecialchars($currentTheme); ?></div><div class="tpm-stat-label">当前主题</div></div>
        </div>
    </div>

    <div class="tpm-tabs">
        <button class="tpm-tab <?php echo $activeTab === 'plugins' ? 'active' : ''; ?>" onclick="location.href='<?php echo $baseUrl; ?>&tab=plugins'">插件管理 <span class="tpm-tab-badge"><?php echo $pluginTotal; ?></span></button>
        <button class="tpm-tab <?php echo $activeTab === 'themes' ? 'active' : ''; ?>" onclick="location.href='<?php echo $baseUrl; ?>&tab=themes'">主题管理 <span class="tpm-tab-badge"><?php echo $themeTotal; ?></span></button>
        <button class="tpm-tab <?php echo $activeTab === 'backups' ? 'active' : ''; ?>" onclick="location.href='<?php echo $baseUrl; ?>&tab=backups'">备份管理</button>
    </div>

    <div class="tpm-toast-container" id="toastContainer"></div>

    <?php if ($successMsg !== ''): ?>
    <div id="tpmSuccessMsg" data-msg="<?php echo htmlspecialchars($successMsg . ' 个安装成功' . ($successNames !== '' ? '：' . $successNames : ''), ENT_QUOTES); ?>" style="display:none;"></div>
    <?php endif; ?>
    <?php if ($errorMsg !== ''): ?>
    <div id="tpmErrorMsg" data-msg="<?php echo htmlspecialchars($errorMsg, ENT_QUOTES); ?>" style="display:none;"></div>
    <?php endif; ?>

    <?php if ($backupFile !== ''): ?>
    <div class="tpm-backup-box" id="backupDownloadBox">
        <div class="backup-info"><span>&#9989; 备份文件已生成：<strong><?php echo htmlspecialchars($backupFile); ?></strong></span></div>
        <a class="download-link" href="<?php echo $downloadBackupUrl . urlencode($backupFile); ?>">&#11015; 点击下载</a>
    </div>
    <?php endif; ?>

    <?php if ($activeTab !== 'backups'): ?>
    <!-- 上传区域：file input 只覆盖此区域 -->
    <div class="tpm-upload-zone" id="uploadZone">
        <input type="file" id="fileInput" multiple accept=".zip" />
        <div class="tpm-upload-icon">+</div>
        <div class="tpm-upload-text">点击或拖拽 ZIP 文件到此处上传</div>
        <div class="tpm-upload-hint">支持 <?php echo $activeTab === 'themes' ? '主题' : '插件'; ?> 的 ZIP 安装包，可同时选择多个文件</div>
    </div>
    <!-- 文件列表和按钮在上传区域外部，避免被 file input 遮挡 -->
    <div class="tpm-file-list" id="fileList"></div>
    <div class="tpm-submit-row" id="submitRow" style="display:none;">
        <button class="tpm-btn primary tpm-btn-lg" id="submitBtn" onclick="submitUpload()">&#11015; 上传安装</button>
        <button class="tpm-btn tpm-btn-lg" onclick="clearFiles()">清除</button>
    </div>
    <?php endif; ?>

    <!-- 插件管理 -->
    <div id="tab-plugins" style="display:<?php echo $activeTab === 'plugins' ? 'block' : 'none'; ?>;">
        <div class="tpm-toolbar">
            <label class="tpm-select-all"><input type="checkbox" id="pluginSelectAll" onchange="toggleSelectAll('plugin-check', this)" /> 全选</label>
            <button class="tpm-btn success tpm-btn-sm" onclick="batchAction('<?php echo $batchEnablePluginsUrl; ?>', 'plugin-check')">&#9654; 启用</button>
            <button class="tpm-btn tpm-btn-sm" onclick="batchAction('<?php echo $batchDisablePluginsUrl; ?>', 'plugin-check')">&#9208; 禁用</button>
            <button class="tpm-btn tpm-btn-sm" onclick="batchAction('<?php echo $batchBackupPluginsUrl; ?>', 'plugin-check')">&#128190; 备份</button>
            <button class="tpm-btn danger tpm-btn-sm" onclick="batchUninstall('<?php echo $batchUninstallPluginsUrl; ?>', 'plugin-check')">&#128465; 卸载</button>
            <div class="tpm-search-box"><span class="search-icon">&#128269;</span><input type="text" placeholder="搜索插件..." oninput="filterTable('plugin-table-body', this.value)" /></div>
        </div>
        <?php if (empty($installedPlugins)): ?>
        <div class="tpm-empty"><div class="empty-icon">&#128268;</div><div class="empty-text">暂无已安装的插件</div></div>
        <?php else: ?>
        <div class="tpm-table-wrap"><table class="tpm-table">
            <thead><tr>
                <th class="col-check"></th><th>插件名称</th>
                <?php if ($showDetails): ?><th class="tpm-col-hide-mobile">版本</th><th class="tpm-col-hide-mobile">作者</th><?php endif; ?>
                <th>状态</th><th class="col-actions">操作</th>
            </tr></thead>
            <tbody id="plugin-table-body">
                <?php foreach ($installedPlugins as $name => $p): ?>
                <tr data-search="<?php echo htmlspecialchars($p['title'].' '.$name.' '.$p['author']); ?>">
                    <td class="col-check"><input type="checkbox" class="plugin-check" name="plugins[]" value="<?php echo htmlspecialchars($name); ?>" /></td>
                    <td>
                        <div class="plugin-name"><?php echo htmlspecialchars($p['title']); ?></div>
                        <?php if ($p['title'] !== $name): ?><div class="plugin-dir"><?php echo htmlspecialchars($name); ?></div><?php endif; ?>
                        <?php if (!empty($p['description']) && $showDetails): ?><div class="plugin-desc" title="<?php echo htmlspecialchars($p['description']); ?>"><?php echo htmlspecialchars($p['description']); ?></div><?php endif; ?>
                    </td>
                    <?php if ($showDetails): ?>
                    <td class="tpm-col-hide-mobile"><?php echo $p['version'] ? htmlspecialchars($p['version']) : '-'; ?></td>
                    <td class="tpm-col-hide-mobile"><?php if (!empty($p['author'])): ?><?php if (!empty($p['homepage'])): ?><a href="<?php echo htmlspecialchars($p['homepage']); ?>" target="_blank" style="color:var(--tpm-primary);text-decoration:none;"><?php echo htmlspecialchars($p['author']); ?></a><?php else: ?><?php echo htmlspecialchars($p['author']); ?><?php endif; ?><?php else: ?>-<?php endif; ?></td>
                    <?php endif; ?>
                    <td><?php if ($p['active']): ?><span class="tpm-badge active"><span class="badge-dot"></span>已启用</span><?php else: ?><span class="tpm-badge inactive"><span class="badge-dot"></span>未启用</span><?php endif; ?></td>
                    <td class="col-actions">
                        <?php if ($p['active'] && $p['hasConfig']): ?><a class="tpm-btn tpm-btn-sm" href="<?php echo $options->adminUrl; ?>options-plugin.php?config=<?php echo urlencode($name); ?>">设置</a><?php endif; ?>
                        <a class="tpm-btn tpm-btn-sm" href="<?php echo $security->getTokenUrl($backupPluginUrl . urlencode($name)); ?>" title="备份">&#128190;</a>
                        <?php if ($name !== 'Manager'): ?><a class="tpm-btn danger tpm-btn-sm" href="<?php echo $security->getTokenUrl($uninstallPluginUrl . urlencode($name)); ?>" onclick="return confirm('确认卸载插件 <?php echo htmlspecialchars($p['title']); ?> ?')" title="卸载">&#128465;</a><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>

    <!-- 主题管理 -->
    <div id="tab-themes" style="display:<?php echo $activeTab === 'themes' ? 'block' : 'none'; ?>;">
        <div class="tpm-toolbar">
            <label class="tpm-select-all"><input type="checkbox" id="themeSelectAll" onchange="toggleSelectAll('theme-check', this)" /> 全选</label>
            <button class="tpm-btn tpm-btn-sm" onclick="batchAction('<?php echo $batchBackupThemesUrl; ?>', 'theme-check')">&#128190; 备份</button>
            <button class="tpm-btn danger tpm-btn-sm" onclick="batchUninstall('<?php echo $batchUninstallThemesUrl; ?>', 'theme-check')">&#128465; 卸载</button>
            <div class="tpm-search-box"><span class="search-icon">&#128269;</span><input type="text" placeholder="搜索主题..." oninput="filterTable('theme-table-body', this.value)" /></div>
        </div>
        <?php if (empty($installedThemes)): ?>
        <div class="tpm-empty"><div class="empty-icon">&#127968;</div><div class="empty-text">暂无已安装的主题</div></div>
        <?php else: ?>
        <div class="tpm-table-wrap"><table class="tpm-table">
            <thead><tr>
                <th class="col-check"></th><th>主题名称</th>
                <?php if ($showDetails): ?><th class="tpm-col-hide-mobile">版本</th><th class="tpm-col-hide-mobile">作者</th><?php endif; ?>
                <th>状态</th><th class="col-actions">操作</th>
            </tr></thead>
            <tbody id="theme-table-body">
                <?php foreach ($installedThemes as $name => $t): ?>
                <tr data-search="<?php echo htmlspecialchars($t['title'].' '.$name.' '.$t['author']); ?>">
                    <td class="col-check"><input type="checkbox" class="theme-check" name="themes[]" value="<?php echo htmlspecialchars($name); ?>" /></td>
                    <td>
                        <div class="plugin-name"><?php echo htmlspecialchars($t['title']); ?></div>
                        <?php if ($t['title'] !== $name): ?><div class="plugin-dir"><?php echo htmlspecialchars($name); ?></div><?php endif; ?>
                    </td>
                    <?php if ($showDetails): ?><td class="tpm-col-hide-mobile"><?php echo $t['version'] ? htmlspecialchars($t['version']) : '-'; ?></td><td class="tpm-col-hide-mobile"><?php echo $t['author'] ? htmlspecialchars($t['author']) : '-'; ?></td><?php endif; ?>
                    <td><?php if ($t['active']): ?><span class="tpm-badge current"><span class="badge-dot"></span>当前主题</span><?php else: ?><span class="tpm-badge inactive"><span class="badge-dot"></span>未启用</span><?php endif; ?></td>
                    <td class="col-actions">
                        <a class="tpm-btn tpm-btn-sm" href="<?php echo $security->getTokenUrl($backupThemeUrl . urlencode($name)); ?>" title="备份">&#128190;</a>
                        <?php if (!$t['active']): ?><a class="tpm-btn danger tpm-btn-sm" href="<?php echo $security->getTokenUrl($uninstallThemeUrl . urlencode($name)); ?>" onclick="return confirm('确认卸载主题 <?php echo htmlspecialchars($t['title']); ?> ?')" title="卸载">&#128465;</a><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>

    <!-- 备份管理 -->
    <div id="tab-backups" style="display:<?php echo $activeTab === 'backups' ? 'block' : 'none'; ?>;">
        <div class="tpm-toolbar">
            <button class="tpm-btn primary tpm-btn-sm" onclick="loadBackups()">&#128260; 刷新列表</button>
            <button class="tpm-btn danger tpm-btn-sm" onclick="deleteAllBackups()">&#128465; 全部删除</button>
            <span style="font-size:12px;color:var(--tpm-gray-400);margin-left:auto;">备份文件超过配置时间未下载将自动清理</span>
        </div>
        <div class="tpm-backup-list" id="backupList">
            <div class="tpm-empty"><div class="empty-icon">&#128190;</div><div class="empty-text">点击「刷新列表」加载备份文件</div></div>
        </div>
    </div>
</div>

<!-- 确认模态框 -->
<div class="tpm-modal-overlay" id="confirmModal">
    <div class="tpm-modal">
        <div class="modal-icon">!</div>
        <h3 id="modalTitle">确认操作</h3>
        <p id="modalMessage"></p>
        <div class="modal-items" id="modalItems" style="display:none;"></div>
        <div class="modal-actions">
            <button class="tpm-btn" onclick="closeModal()">取消</button>
            <button class="tpm-btn danger solid" id="modalConfirmBtn" disabled>确认 <span class="countdown" id="modalCountdown"></span></button>
        </div>
    </div>
</div>

<script>
var activeTab = '<?php echo $activeTab; ?>';
var baseUrl = '<?php echo $baseUrl; ?>';
var actionBaseUrl = '<?php echo $actionBaseUrl; ?>';
var securityToken = '<?php echo $securityToken; ?>';
var tokenKey = '<?php echo $tokenKey; ?>';
var downloadBackupUrl = '<?php echo $downloadBackupUrl; ?>';
var deleteBackupUrl = '<?php echo $deleteBackupUrl; ?>';
var deleteAllBackupsUrl = '<?php echo $deleteAllBackupsUrl; ?>';
var listBackupsUrl = '<?php echo $listBackupsUrl; ?>';
var selectedFiles = [];

function showToast(type, message, duration) {
    duration = duration || 4000;
    var container = document.getElementById('toastContainer');
    var toast = document.createElement('div');
    toast.className = 'tpm-toast ' + type;
    var icons = { success: '&#9989;', error: '&#9888;', info: '&#8505;' };
    toast.innerHTML = '<span class="toast-icon">' + (icons[type] || icons.info) + '</span><span class="toast-content">' + escapeHtml(message) + '</span><button class="toast-close" onclick="dismissToast(this.parentElement)">&times;</button>';
    container.appendChild(toast);
    setTimeout(function() { dismissToast(toast); }, duration);
}
function dismissToast(toast) {
    if (!toast || !toast.parentElement) return;
    toast.style.animation = 'tpm-fade-out 0.3s ease forwards';
    setTimeout(function() { if (toast.parentElement) toast.parentElement.removeChild(toast); }, 300);
}

function filterTable(bodyId, keyword) {
    keyword = keyword.toLowerCase().trim();
    var rows = document.querySelectorAll('#' + bodyId + ' tr');
    for (var i = 0; i < rows.length; i++) {
        var s = (rows[i].getAttribute('data-search') || '').toLowerCase();
        rows[i].style.display = (keyword === '' || s.indexOf(keyword) !== -1) ? '' : 'none';
    }
}

function toggleSelectAll(className, source) {
    var boxes = document.querySelectorAll('.' + className);
    for (var i = 0; i < boxes.length; i++) { boxes[i].checked = source.checked; }
}
function getCheckedValues(className) {
    var boxes = document.querySelectorAll('.' + className);
    var values = [];
    for (var i = 0; i < boxes.length; i++) { if (boxes[i].checked) values.push(boxes[i].value); }
    return values;
}

function batchAction(baseActionUrl, className) {
    var values = getCheckedValues(className);
    if (values.length === 0) { showToast('error', '请至少选择一项'); return; }
    var form = createPostForm(baseActionUrl, values, className);
    document.body.appendChild(form);
    form.submit();
}
function batchUninstall(baseActionUrl, className) {
    var values = getCheckedValues(className);
    if (values.length === 0) { showToast('error', '请至少选择一项'); return; }
    if (values.length === 1) { var form = createPostForm(baseActionUrl, values, className); document.body.appendChild(form); form.submit(); return; }
    showUninstallModal(baseActionUrl, values, className);
}
function createPostForm(actionUrl, values, valueName) {
    var form = document.createElement('form');
    form.method = 'post';
    form.action = actionUrl;
    form.style.display = 'none';
    var tokenInput = document.createElement('input');
    tokenInput.type = 'hidden'; tokenInput.name = tokenKey; tokenInput.value = securityToken;
    form.appendChild(tokenInput);
    var fieldName = valueName === 'plugin-check' ? 'plugins[]' : 'themes[]';
    for (var i = 0; i < values.length; i++) {
        var input = document.createElement('input');
        input.type = 'hidden'; input.name = fieldName; input.value = values[i];
        form.appendChild(input);
    }
    return form;
}

var modalTimer = null, modalCountdown = 0;
function showUninstallModal(actionUrl, values, className) {
    modalCountdown = 3;
    document.getElementById('modalTitle').textContent = '批量卸载确认';
    document.getElementById('modalMessage').textContent = '即将卸载 ' + values.length + ' 个项目，此操作不可恢复。请等待倒计时结束后确认。';
    var itemsContainer = document.getElementById('modalItems');
    itemsContainer.innerHTML = '';
    for (var i = 0; i < values.length; i++) {
        var item = document.createElement('div');
        item.className = 'modal-item';
        item.textContent = (i + 1) + '. ' + values[i];
        itemsContainer.appendChild(item);
    }
    itemsContainer.style.display = 'block';
    var confirmBtn = document.getElementById('modalConfirmBtn');
    var countdownEl = document.getElementById('modalCountdown');
    confirmBtn.disabled = true;
    countdownEl.textContent = '(' + modalCountdown + ')';
    document.getElementById('confirmModal').classList.add('show');
    modalTimer = setInterval(function() {
        modalCountdown--;
        countdownEl.textContent = modalCountdown > 0 ? '(' + modalCountdown + ')' : '';
        if (modalCountdown <= 0) { clearInterval(modalTimer); confirmBtn.disabled = false; }
    }, 1000);
    confirmBtn.onclick = function() {
        closeModal();
        var form = createPostForm(actionUrl, values, className);
        document.body.appendChild(form);
        form.submit();
    };
}
function closeModal() {
    document.getElementById('confirmModal').classList.remove('show');
    if (modalTimer) { clearInterval(modalTimer); modalTimer = null; }
}

// 上传 - file input 只覆盖 upload zone，按钮在外部不被遮挡
var uploadZone = document.getElementById('uploadZone');
if (uploadZone) {
    uploadZone.addEventListener('dragover', function(e) { e.preventDefault(); this.classList.add('dragover'); });
    uploadZone.addEventListener('dragleave', function(e) { if (e.target === this) this.classList.remove('dragover'); });
    uploadZone.addEventListener('drop', function(e) { e.preventDefault(); this.classList.remove('dragover'); addFiles(e.dataTransfer.files); });
    document.getElementById('fileInput').addEventListener('change', function() { addFiles(this.files); this.value = ''; });
}
function addFiles(files) {
    for (var i = 0; i < files.length; i++) {
        if (files[i].name.toLowerCase().indexOf('.zip') !== -1 || files[i].type === 'application/zip') {
            selectedFiles.push(files[i]);
        } else {
            showToast('error', files[i].name + ' 不是 ZIP 文件，已跳过');
        }
    }
    renderFileList();
}
function renderFileList() {
    var list = document.getElementById('fileList');
    var row = document.getElementById('submitRow');
    if (!list || !row) return;
    list.innerHTML = '';
    if (selectedFiles.length === 0) { row.style.display = 'none'; return; }
    row.style.display = 'flex';
    for (var i = 0; i < selectedFiles.length; i++) {
        var f = selectedFiles[i];
        var div = document.createElement('div');
        div.className = 'tpm-file-item';
        div.innerHTML = '<div class="file-info"><span class="file-icon">&#128206;</span><span>' + escapeHtml(f.name) + '</span><span class="file-size">' + formatSize(f.size) + '</span></div><button class="remove-file" onclick="removeFile(' + i + ')">&times;</button>';
        list.appendChild(div);
    }
}
function removeFile(index) { selectedFiles.splice(index, 1); renderFileList(); }
function clearFiles() { selectedFiles = []; renderFileList(); }
function submitUpload() {
    if (selectedFiles.length === 0) { showToast('error', '请先选择文件'); return; }
    var form = document.createElement('form');
    form.method = 'post';
    form.enctype = 'multipart/form-data';
    form.action = actionBaseUrl + '?' + (activeTab === 'plugins' ? 'uploadPlugin' : 'uploadTheme') + '=1';
    form.style.display = 'none';
    var tokenInput = document.createElement('input');
    tokenInput.type = 'hidden'; tokenInput.name = tokenKey; tokenInput.value = securityToken;
    form.appendChild(tokenInput);
    var fieldName = activeTab === 'plugins' ? 'plugin_zip[]' : 'theme_zip[]';
    var dt = new DataTransfer();
    for (var i = 0; i < selectedFiles.length; i++) dt.items.add(selectedFiles[i]);
    var realInput = document.createElement('input');
    realInput.type = 'file'; realInput.name = fieldName; realInput.files = dt.files;
    form.appendChild(realInput);
    var submitBtn = document.getElementById('submitBtn');
    if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '&#8987; 上传中...'; }
    document.body.appendChild(form);
    form.submit();
}

// 备份管理
function loadBackups() {
    var listEl = document.getElementById('backupList');
    if (!listEl) return;
    listEl.innerHTML = '<div class="tpm-empty"><div class="empty-text">加载中...</div></div>';
    fetch(listBackupsUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: tokenKey + '=' + encodeURIComponent(securityToken) })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.error) { listEl.innerHTML = '<div class="tpm-empty"><div class="empty-text">' + escapeHtml(data.error) + '</div></div>'; return; }
        renderBackupList(data.backups || []);
    })
    .catch(function() { listEl.innerHTML = '<div class="tpm-empty"><div class="empty-text">请求失败，请重试</div></div>'; });
}
function renderBackupList(backups) {
    var listEl = document.getElementById('backupList');
    if (backups.length === 0) { listEl.innerHTML = '<div class="tpm-empty"><div class="empty-icon">&#128190;</div><div class="empty-text">暂无备份文件</div></div>'; return; }
    listEl.innerHTML = '';
    for (var i = 0; i < backups.length; i++) {
        var b = backups[i];
        var item = document.createElement('div');
        item.className = 'tpm-backup-item';
        var downloadLink = actionBaseUrl + '?downloadBackup=' + encodeURIComponent(b.name);
        item.innerHTML = '<div class="backup-file-icon">&#128230;</div><div class="backup-info"><div class="backup-name">' + escapeHtml(b.name) + '</div><div class="backup-meta">' + formatSize(b.size) + ' &middot; ' + formatTime(b.time) + '</div></div><div class="backup-actions"><a class="tpm-btn primary tpm-btn-sm" href="' + downloadLink + '">&#11015; 下载</a><button class="tpm-btn danger tpm-btn-sm" data-backup-name="' + escapeHtml(b.name) + '">&#128465;</button></div>';
        var deleteBtn = item.querySelector('button[data-backup-name]');
        (function(btn, filename) { btn.onclick = function() { deleteBackup(filename); }; })(deleteBtn, b.name);
        listEl.appendChild(item);
    }
}
function deleteBackup(filename) {
    if (!confirm('确认删除备份文件 ' + filename + ' ?')) return;
    fetch(deleteBackupUrl + encodeURIComponent(filename), { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: tokenKey + '=' + encodeURIComponent(securityToken) })
    .then(function(r) { return r.json(); })
    .then(function(d) { if (d.success) { showToast('success', '已删除'); loadBackups(); } else { showToast('error', d.error || '删除失败'); } })
    .catch(function() { showToast('error', '请求失败'); });
}
function deleteAllBackups() {
    if (!confirm('确认删除所有备份文件？此操作不可恢复。')) return;
    fetch(deleteAllBackupsUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: tokenKey + '=' + encodeURIComponent(securityToken) })
    .then(function(r) { return r.json(); })
    .then(function(d) { if (d.success) { showToast('success', '已删除 ' + d.deleted + ' 个备份' + (d.failed > 0 ? '，' + d.failed + ' 个失败' : '')); loadBackups(); } else { showToast('error', d.error || '删除失败'); } })
    .catch(function() { showToast('error', '请求失败'); });
}

function formatSize(bytes) { if (bytes < 1024) return bytes + ' B'; if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'; return (bytes / 1048576).toFixed(1) + ' MB'; }
function formatTime(timestamp) { var d = new Date(timestamp * 1000), pad = function(n) { return n < 10 ? '0' + n : n; }; return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()); }
function escapeHtml(str) { var div = document.createElement('div'); div.textContent = str; return div.innerHTML; }

(function() {
    var successEl = document.getElementById('tpmSuccessMsg');
    var errorEl = document.getElementById('tpmErrorMsg');
    if (successEl) showToast('success', successEl.getAttribute('data-msg'), 5000);
    if (errorEl) showToast('error', errorEl.getAttribute('data-msg'), 8000);
    if (activeTab === 'backups') loadBackups();
})();
</script>

<?php
include_once 'copyright.php';
include_once 'common-js.php';
include_once 'footer.php';
