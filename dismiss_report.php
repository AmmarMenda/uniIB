<?php
session_start();

if (!isset($_SESSION['authenticated'])) {
    header("HTTP/1.1 403 Forbidden");
    exit;
}

$config = [
    'reports_dir' => 'b/reports/',
    'backup_dir' => 'b/backups/'
];

if (isset($_POST['post_id'])) {
    $post_id = basename($_POST['post_id']);
    $report_file = $config['reports_dir'].'report_'.$post_id.'.json';
    
    if (file_exists($report_file)) {
        if (!is_dir($config['backup_dir'])) {
            mkdir($config['backup_dir'], 0755, true);
        }
        
        // Move report to backups
        rename($report_file, $config['backup_dir'].'dismissed_'.$post_id.'_'.time().'.json');
    }
}

header("Location: mod.php");
exit;
?>