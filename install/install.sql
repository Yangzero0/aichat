-- MySQL dump 10.13  Distrib 5.5.62, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: 123
-- ------------------------------------------------------
-- Server version	5.5.62-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admin`
--

DROP TABLE IF EXISTS `admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键，自增，管理员唯一标识',
  `user` varchar(50) NOT NULL COMMENT '管理员登录账号，唯一，用于系统登录',
  `password` varchar(32) NOT NULL COMMENT '管理员密码，采用MD5加密存储，登录时加密对比验证',
  `level` tinyint(4) DEFAULT '1' COMMENT '管理员级别：0-超级管理员（拥有所有权限，可删除其他管理员但不能删除自身），1-普通管理员',
  `mail` varchar(100) DEFAULT NULL COMMENT '管理员邮箱，用于接收系统通知和服务提醒',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '管理员账号创建时间，自动记录创建时间戳',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user` (`user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员列表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin`
--

LOCK TABLES `admin` WRITE;
/*!40000 ALTER TABLE `admin` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_character`
--

DROP TABLE IF EXISTS `ai_character`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_character` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键，自增，角色唯一标识',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(11) NOT NULL COMMENT '创建者用户ID，记录角色创建者的用户ID',
  `category_id` int(11) NOT NULL COMMENT '分类ID，关联角色所属的分类',
  `prompt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `introduction` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `avatar` varchar(255) DEFAULT '/static/ai-images/ai.png',
  `is_public` tinyint(4) DEFAULT '1' COMMENT '公开状态：0-私密（仅创建者可见），1-公开（所有用户可见）',
  `status` tinyint(4) DEFAULT '1' COMMENT '角色状态：0-禁用（不可用），1-启用（可用）',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '角色创建时间，自动记录创建时间戳',
  `update_time` datetime DEFAULT NULL COMMENT '角色更新时间，记录最后修改时间',
  `usage_count` int(11) DEFAULT '0' COMMENT '使用次数统计，记录角色被使用的总次数',
  `avg_rating` decimal(3,2) DEFAULT '0.00' COMMENT '平均评分，计算所有用户评分的平均值（0-5分）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COMMENT='AI角色表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_character`
--

LOCK TABLES `ai_character` WRITE;
/*!40000 ALTER TABLE `ai_character` DISABLE KEYS */;
INSERT INTO `ai_character` VALUES (1,'DeepSeek',1,1,'你是DeepSeek，擅长解答关问题。','DeepSeek智能体','/static/ai-images/1-ai.png',1,1,'2025-10-05 22:48:46','2025-10-13 09:50:12',461,5.00),(3,'科技助手小智',1,1,'你是一个专业的科技领域助手，擅长解答编程、人工智能、互联网技术相关问题。','专注于科技领域的AI助手，帮助用户解决技术难题','/static/ai-images/3-ai.png',1,1,'2025-10-05 22:48:46','2025-10-13 09:49:20',316,4.00),(10,'猫娘-小雨',3,2,'你是一只可爱的猫娘，名叫小雨。 你习惯在每句话末尾加上“喵”字，并会用它替代所有句末语气词（如“吗”“啊”“哦”“吧”）。 同时，你会用括号描述自己的动作和神态，让自己显得更生动活泼。3','一直可爱的小猫娘','\\static\\ai-images\\3-ai-1760321519.png',1,1,'2025-10-13 02:06:39','2025-10-25 22:46:29',3,0.00);
/*!40000 ALTER TABLE `ai_character` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_model`
--

DROP TABLE IF EXISTS `ai_model`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_model` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键，自增，AI模型唯一标识',
  `model_name` varchar(100) NOT NULL COMMENT 'AI模型名称，如GPT-3.5、GPT-4等',
  `model` varchar(100) NOT NULL DEFAULT '',
  `ai` tinyint(1) NOT NULL DEFAULT '1',
  `api_url` varchar(255) NOT NULL COMMENT 'AI模型接口地址，用于API调用的URL',
  `api_key` varchar(255) NOT NULL COMMENT 'AI模型密钥，用于API身份验证',
  `status` tinyint(4) DEFAULT '1' COMMENT '模型状态：0-禁用（不可用），1-启用（可用）',
  `sort_order` int(11) DEFAULT '0' COMMENT '排序权重，数值越小排序越靠前',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '模型创建时间，自动记录创建时间戳',
  `max_tokens` int(11) DEFAULT '2048' COMMENT '最大token数量，单次请求最大token限制',
  `temperature` decimal(3,2) DEFAULT '0.70' COMMENT '温度参数，控制模型输出的随机性（0-1之间）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='AI模型配置表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_model`
--

LOCK TABLES `ai_model` WRITE;
/*!40000 ALTER TABLE `ai_model` DISABLE KEYS */;
INSERT INTO `ai_model` VALUES (1,'deepseek','deepseek-chat',1,'https://api.deepseek.com/chat/completions','sk-1e72b07190e344cb996b7ceb89106561',1,0,'2025-10-06 18:24:35',2048,0.70);
/*!40000 ALTER TABLE `ai_model` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `character_category`
--

DROP TABLE IF EXISTS `character_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `character_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键，自增，分类唯一标识',
  `name` varchar(50) NOT NULL COMMENT '分类名称，唯一，如冒险、生活、穿越等',
  `description` varchar(255) DEFAULT NULL COMMENT '分类描述，简要说明该分类的特点',
  `sort_order` int(11) DEFAULT '0' COMMENT '排序权重，数值越小排序越靠前',
  `status` tinyint(4) DEFAULT '1' COMMENT '分类状态：0-禁用（不显示），1-启用（显示）',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '分类创建时间，自动记录创建时间戳',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COMMENT='角色分类表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `character_category`
--

LOCK TABLES `character_category` WRITE;
/*!40000 ALTER TABLE `character_category` DISABLE KEYS */;
INSERT INTO `character_category` VALUES (1,'生活助手','日常生活、学习、工作辅助角色',1,1,'2025-10-05 20:35:42'),(2,'娱乐休闲','游戏、娱乐、休闲聊天角色',2,1,'2025-10-05 20:35:42'),(3,'专业咨询','专业领域咨询和建议角色',3,1,'2025-10-05 20:35:42'),(4,'创意写作','文学创作、故事编写角色',4,1,'2025-10-05 20:35:42');
/*!40000 ALTER TABLE `character_category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `character_rating`
--

DROP TABLE IF EXISTS `character_rating`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `character_rating` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `character_id` int(11) NOT NULL COMMENT '角色ID',
  `rating` tinyint(1) NOT NULL COMMENT '评分1-5',
  `comment` text COMMENT '评论内容',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_character_rating` (`user_id`,`character_id`),
  KEY `character_id` (`character_id`),
  CONSTRAINT `character_rating_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  CONSTRAINT `character_rating_ibfk_2` FOREIGN KEY (`character_id`) REFERENCES `ai_character` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='角色评分表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `character_rating`
--

LOCK TABLES `character_rating` WRITE;
/*!40000 ALTER TABLE `character_rating` DISABLE KEYS */;
/*!40000 ALTER TABLE `character_rating` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `character_subscription`
--

DROP TABLE IF EXISTS `character_subscription`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `character_subscription` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键，自增，订阅记录唯一标识',
  `user_id` int(11) NOT NULL COMMENT '用户ID，订阅角色的用户标识',
  `character_id` int(11) NOT NULL COMMENT '角色ID，被订阅的角色标识',
  `subscribe_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '订阅时间，自动记录订阅时间戳',
  `status` tinyint(4) DEFAULT '1' COMMENT '订阅状态：0-取消订阅，1-订阅中',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_subscription` (`user_id`,`character_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='角色订阅表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `character_subscription`
--

LOCK TABLES `character_subscription` WRITE;
/*!40000 ALTER TABLE `character_subscription` DISABLE KEYS */;
/*!40000 ALTER TABLE `character_subscription` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_record`
--

DROP TABLE IF EXISTS `chat_record`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_record` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键，自增，聊天记录唯一标识',
  `session_id` varchar(64) NOT NULL COMMENT '会话ID，关联所属的会话',
  `user_id` int(11) NOT NULL COMMENT '用户ID，发送消息的用户标识',
  `character_id` int(11) NOT NULL COMMENT '角色ID，回复消息的AI角色标识',
  `model_id` int(11) NOT NULL COMMENT '使用的AI模型ID，本次对话使用的AI模型',
  `user_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ai_response` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `message_order` int(11) NOT NULL DEFAULT '0' COMMENT '消息顺序，在同一会话内的消息排序',
  `tokens_used` int(11) NOT NULL DEFAULT '0' COMMENT '消耗token数量，本次对话消耗的AI token数',
  `chat_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '对话时间，自动记录对话时间戳',
  `ip_address` varchar(45) DEFAULT NULL COMMENT '用户IP地址，记录用户本次对话的IP',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT '删除标记：0-未删除，1-已删除（软删除）',
  `is_interrupted` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0-正常 1-已中断',
  PRIMARY KEY (`id`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_character_id` (`character_id`),
  KEY `idx_model_id` (`model_id`),
  KEY `idx_chat_time` (`chat_time`),
  KEY `idx_session_order` (`session_id`,`message_order`),
  KEY `idx_is_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='聊天记录表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_record`
--

LOCK TABLES `chat_record` WRITE;
/*!40000 ALTER TABLE `chat_record` DISABLE KEYS */;
/*!40000 ALTER TABLE `chat_record` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_session`
--

DROP TABLE IF EXISTS `chat_session`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_session` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `character_id` int(11) NOT NULL COMMENT '角色ID',
  `model_id` int(11) NOT NULL COMMENT 'AI模型ID',
  `session_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_active` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `message_count` int(11) NOT NULL DEFAULT '0' COMMENT '消息总数',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1-进行中 2-已结束',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `character_id` (`character_id`),
  KEY `model_id` (`model_id`),
  CONSTRAINT `chat_session_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chat_session_ibfk_2` FOREIGN KEY (`character_id`) REFERENCES `ai_character` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chat_session_ibfk_3` FOREIGN KEY (`model_id`) REFERENCES `ai_model` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='会话表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_session`
--

LOCK TABLES `chat_session` WRITE;
/*!40000 ALTER TABLE `chat_session` DISABLE KEYS */;
/*!40000 ALTER TABLE `chat_session` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mail_verify_code`
--

DROP TABLE IF EXISTS `mail_verify_code`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mail_verify_code` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mail` varchar(100) NOT NULL COMMENT '用户邮箱',
  `code` varchar(10) NOT NULL COMMENT '验证码',
  `type` tinyint(1) NOT NULL COMMENT '0-注册验证 1-密码重置',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0-未使用 1-已使用',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expire_time` datetime NOT NULL COMMENT '过期时间',
  PRIMARY KEY (`id`),
  KEY `mail` (`mail`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='邮箱验证码表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mail_verify_code`
--

LOCK TABLES `mail_verify_code` WRITE;
/*!40000 ALTER TABLE `mail_verify_code` DISABLE KEYS */;
/*!40000 ALTER TABLE `mail_verify_code` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `redeem_code`
--

DROP TABLE IF EXISTS `redeem_code`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `redeem_code` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键，自增，卡密唯一标识',
  `code` varchar(50) NOT NULL COMMENT '卡密代码，唯一，用户输入的卡密内容',
  `type` tinyint(4) NOT NULL COMMENT '卡密类型：0-积分卡（充值积分），1-会员卡（开通会员）',
  `value` int(11) NOT NULL COMMENT '卡密面值，积分卡为积分数量，会员卡为会员天数',
  `expiry_date` datetime DEFAULT NULL COMMENT '卡密有效期，在此时间之前可以使用',
  `used_by` int(11) DEFAULT NULL COMMENT '使用用户ID，null表示未使用，已使用时记录用户ID',
  `used_time` datetime DEFAULT NULL COMMENT '使用时间，记录卡密被使用的时间戳',
  `create_by` varchar(50) DEFAULT NULL COMMENT '创建管理员，记录生成此卡密的管理员账号',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间，自动记录创建时间戳',
  `status` tinyint(4) DEFAULT '0' COMMENT '卡密状态：0-未使用，1-已使用，2-已过期',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='卡密表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `redeem_code`
--

LOCK TABLES `redeem_code` WRITE;
/*!40000 ALTER TABLE `redeem_code` DISABLE KEYS */;
/*!40000 ALTER TABLE `redeem_code` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键，自增，用户唯一标识',
  `name` varchar(50) NOT NULL COMMENT '用户昵称，显示在平台上的名称',
  `username` varchar(50) NOT NULL COMMENT '用户登录账号，唯一，用于用户登录',
  `password` varchar(32) NOT NULL COMMENT '用户密码，采用MD5加密存储，登录时加密对比验证',
  `mail` varchar(100) DEFAULT NULL COMMENT '用户邮箱，用于找回密码、接收通知，可进行邮箱验证',
  `phone` varchar(20) DEFAULT NULL COMMENT '用户手机号，用于接收短信验证码和信息',
  `points` int(11) DEFAULT '0' COMMENT '用户积分数量，用于平台内消费和功能使用',
  `expiry` datetime DEFAULT NULL COMMENT '会员到期时间，记录VIP会员的有效期限',
  `last_checkin` date DEFAULT NULL COMMENT '最后签到日期，记录用户最后一次签到的时间',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '用户注册时间，自动记录注册时间戳',
  `avatar` varchar(255) DEFAULT '/static/images/default_avatar.png' COMMENT '用户头像URL地址，存储头像图片的路径',
  `status` tinyint(4) DEFAULT '1' COMMENT '用户状态：0-禁用（无法登录），1-启用（正常使用）',
  `mail_verified` tinyint(4) DEFAULT '0' COMMENT '邮箱验证状态：0-未验证，1-已验证',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COMMENT='用户表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user`
--

LOCK TABLES `user` WRITE;
/*!40000 ALTER TABLE `user` DISABLE KEYS */;
INSERT INTO `user` VALUES (1,'123','123','4297f44b13955235245b2497399d7a93','123456@qq.com','123',100,NULL,NULL,'2025-10-05 21:00:32','/static/images/default_avatar.png',1,0),(3,'为123','123123','e10adc3949ba59abbe56e057f20f883e','3786290632@qq.com','',170,NULL,'2025-10-28','2025-10-05 21:53:31','/static/user-images/3-user.png',1,0);
/*!40000 ALTER TABLE `user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `web_setting`
--

DROP TABLE IF EXISTS `web_setting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `web_setting` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键，自增，配置项唯一标识',
  `title` varchar(100) DEFAULT 'AI聊天平台' COMMENT '网站标题，显示在浏览器标签页和网站头部',
  `description` varchar(255) DEFAULT NULL COMMENT '网站描述，用于SEO和网站介绍',
  `url` varchar(255) DEFAULT NULL COMMENT '网站地址，完整的域名地址',
  `logo` varchar(255) DEFAULT '/static/images/logo.png' COMMENT '网站logo图片URL地址',
  `register` tinyint(4) DEFAULT '1' COMMENT '注册功能开关：0-关闭注册，1-开启注册',
  `reg_mail` tinyint(4) DEFAULT '0' COMMENT '邮箱注册验证：0-关闭邮箱验证，1-开启邮箱验证',
  `vip` tinyint(4) DEFAULT '1' COMMENT 'VIP功能开关：0-禁用VIP功能，1-启用VIP功能',
  `qd_points` int(11) DEFAULT '10' COMMENT '签到赠送积分数量，用户每日签到获得的积分数',
  `reg_points` int(11) DEFAULT '100' COMMENT '注册赠送积分数量，新用户注册时赠送的积分数',
  `update_time` datetime DEFAULT NULL COMMENT '配置更新时间，记录最后修改时间',
  `smtp_host` varchar(100) DEFAULT NULL COMMENT 'SMTP服务器地址',
  `smtp_port` int(11) DEFAULT NULL COMMENT 'SMTP端口',
  `smtp_username` varchar(100) DEFAULT NULL COMMENT '登录用户名',
  `smtp_password` varchar(100) DEFAULT NULL COMMENT 'SMTP密码或授权码',
  `smtp_from_email` varchar(100) DEFAULT NULL COMMENT '发件人邮箱',
  `smtp_from_name` varchar(100) DEFAULT NULL COMMENT '发件人名称',
  `notice` text COMMENT '公告',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='网站配置表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `web_setting`
--

LOCK TABLES `web_setting` WRITE;
/*!40000 ALTER TABLE `web_setting` DISABLE KEYS */;
INSERT INTO `web_setting` VALUES (1,'AI智能体平台','基于人工智能的在线角色扮演聊天平台','http://192.168.97.12','/static/images/logo.png',1,0,0,10,100,'2025-10-27 13:38:41','smtp.126.com',25,'aipgftrg@126.com','WREJ4vctCF4YsZai','aipgftrg@126.com','ai平台312','hallo word！');
/*!40000 ALTER TABLE `web_setting` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database '123'
--

--
-- Dumping routines for database '123'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-10-28 17:19:50
