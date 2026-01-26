<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-17
最后编辑时间：2025-01-26
文件描述：退出登录，只清除管理员会话，不影响用户会话
*/
session_start();

// 只清除管理员相关的会话变量，保留用户会话
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);
unset($_SESSION['admin_level']);

// 不销毁整个会话，避免影响用户登录状态
// session_destroy();

header('Location: login.php');
exit;
?>