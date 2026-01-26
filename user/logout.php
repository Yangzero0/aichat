<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-6
最后编辑时间：2025-01-26
文件描述：退出登录功能，只清除用户会话，不影响管理员会话

*/
session_start();

// 只清除用户相关的会话变量，保留管理员会话
unset($_SESSION['user_id']);
unset($_SESSION['username']);
unset($_SESSION['name']);

// 不销毁整个会话，避免影响管理员登录状态
// session_destroy();

header('Location: login');
exit;
?>