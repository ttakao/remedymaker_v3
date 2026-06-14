<?php
// index.php
require_once 'config.php';
require_once 'Template.php';

$tpl = new Template('templates/layout.html');
$tpl->assign('version', APP_VERSION);
$tpl->assign('title', 'レメディ生成機コントロールシステム');

echo $tpl->render();