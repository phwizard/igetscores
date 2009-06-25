-- phpMyAdmin SQL Dump
-- version 2.11.9.1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Jun 24, 2009 at 09:29 PM
-- Server version: 4.1.22
-- PHP Version: 5.2.6

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `igetscor_main`
--

-- --------------------------------------------------------

--
-- Table structure for table `custom_fields_names`
--

CREATE TABLE IF NOT EXISTS `custom_fields_names` (
  `id` int(11) NOT NULL auto_increment,
  `game_id` int(11) NOT NULL default '0',
  `field_name` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=5 ;

-- --------------------------------------------------------

--
-- Table structure for table `custom_fields_values`
--

CREATE TABLE IF NOT EXISTS `custom_fields_values` (
  `id` int(11) NOT NULL auto_increment,
  `score_id` int(11) NOT NULL default '0',
  `field_id` int(11) NOT NULL default '0',
  `field_value` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=309 ;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_consumer_registry`
--

CREATE TABLE IF NOT EXISTS `oauth_consumer_registry` (
  `ocr_id` int(11) NOT NULL auto_increment,
  `ocr_usa_id_ref` int(11) default NULL,
  `ocr_consumer_key` varchar(64) character set utf8 collate utf8_bin NOT NULL default '',
  `ocr_consumer_secret` varchar(64) character set utf8 collate utf8_bin NOT NULL default '',
  `ocr_signature_methods` varchar(255) NOT NULL default 'HMAC-SHA1,PLAINTEXT',
  `ocr_server_uri` varchar(255) NOT NULL default '',
  `ocr_server_uri_host` varchar(128) NOT NULL default '',
  `ocr_server_uri_path` varchar(128) character set utf8 collate utf8_bin NOT NULL default '',
  `ocr_request_token_uri` varchar(255) NOT NULL default '',
  `ocr_authorize_uri` varchar(255) NOT NULL default '',
  `ocr_access_token_uri` varchar(255) NOT NULL default '',
  `ocr_timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`ocr_id`),
  UNIQUE KEY `ocr_consumer_key` (`ocr_consumer_key`,`ocr_usa_id_ref`),
  KEY `ocr_server_uri` (`ocr_server_uri`),
  KEY `ocr_server_uri_host` (`ocr_server_uri_host`,`ocr_server_uri_path`),
  KEY `ocr_usa_id_ref` (`ocr_usa_id_ref`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_consumer_token`
--

CREATE TABLE IF NOT EXISTS `oauth_consumer_token` (
  `oct_id` int(11) NOT NULL auto_increment,
  `oct_ocr_id_ref` int(11) NOT NULL default '0',
  `oct_usa_id_ref` int(11) NOT NULL default '0',
  `oct_token` varchar(64) character set utf8 collate utf8_bin NOT NULL default '',
  `oct_token_secret` varchar(64) character set utf8 collate utf8_bin NOT NULL default '',
  `oct_token_type` enum('request','authorized','access') default NULL,
  `oct_timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`oct_id`),
  UNIQUE KEY `oct_ocr_id_ref` (`oct_ocr_id_ref`,`oct_token`),
  UNIQUE KEY `oct_usa_id_ref` (`oct_usa_id_ref`,`oct_ocr_id_ref`,`oct_token_type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_log`
--

CREATE TABLE IF NOT EXISTS `oauth_log` (
  `olg_id` int(11) NOT NULL auto_increment,
  `olg_osr_consumer_key` varchar(64) character set utf8 collate utf8_bin default NULL,
  `olg_ost_token` varchar(64) character set utf8 collate utf8_bin default NULL,
  `olg_ocr_consumer_key` varchar(64) character set utf8 collate utf8_bin default NULL,
  `olg_oct_token` varchar(64) character set utf8 collate utf8_bin default NULL,
  `olg_usa_id_ref` int(11) default NULL,
  `olg_received` text NOT NULL,
  `olg_sent` text NOT NULL,
  `olg_base_string` text NOT NULL,
  `olg_notes` text NOT NULL,
  `olg_timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `olg_remote_ip` bigint(20) NOT NULL default '0',
  PRIMARY KEY  (`olg_id`),
  KEY `olg_osr_consumer_key` (`olg_osr_consumer_key`,`olg_id`),
  KEY `olg_ost_token` (`olg_ost_token`,`olg_id`),
  KEY `olg_ocr_consumer_key` (`olg_ocr_consumer_key`,`olg_id`),
  KEY `olg_oct_token` (`olg_oct_token`,`olg_id`),
  KEY `olg_usa_id_ref` (`olg_usa_id_ref`,`olg_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_server_nonce`
--

CREATE TABLE IF NOT EXISTS `oauth_server_nonce` (
  `osn_id` int(11) NOT NULL auto_increment,
  `osn_consumer_key` varchar(64) character set utf8 collate utf8_bin NOT NULL default '',
  `osn_token` varchar(64) character set utf8 collate utf8_bin NOT NULL default '',
  `osn_timestamp` bigint(20) NOT NULL default '0',
  `osn_nonce` varchar(80) character set utf8 collate utf8_bin NOT NULL default '',
  PRIMARY KEY  (`osn_id`),
  UNIQUE KEY `osn_consumer_key` (`osn_consumer_key`,`osn_token`,`osn_timestamp`,`osn_nonce`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=3968 ;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_server_registry`
--

CREATE TABLE IF NOT EXISTS `oauth_server_registry` (
  `osr_id` int(11) NOT NULL auto_increment,
  `osr_usa_id_ref` int(11) default NULL,
  `osr_consumer_key` varchar(64) character set utf8 collate utf8_bin NOT NULL default '',
  `osr_consumer_secret` varchar(64) character set utf8 collate utf8_bin NOT NULL default '',
  `osr_enabled` tinyint(1) NOT NULL default '1',
  `osr_status` varchar(16) NOT NULL default '',
  `osr_requester_name` varchar(64) NOT NULL default '',
  `osr_requester_email` varchar(64) NOT NULL default '',
  `osr_callback_uri` varchar(255) NOT NULL default '',
  `osr_application_uri` varchar(255) NOT NULL default '',
  `osr_application_title` varchar(80) NOT NULL default '',
  `osr_application_descr` text NOT NULL,
  `osr_application_notes` text NOT NULL,
  `osr_application_type` varchar(20) NOT NULL default '',
  `osr_application_commercial` tinyint(1) NOT NULL default '0',
  `osr_issue_date` datetime NOT NULL default '0000-00-00 00:00:00',
  `osr_timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`osr_id`),
  UNIQUE KEY `osr_consumer_key` (`osr_consumer_key`),
  KEY `osr_usa_id_ref` (`osr_usa_id_ref`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=10 ;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_server_token`
--

CREATE TABLE IF NOT EXISTS `oauth_server_token` (
  `ost_id` int(11) NOT NULL auto_increment,
  `ost_osr_id_ref` int(11) NOT NULL default '0',
  `ost_usa_id_ref` int(11) NOT NULL default '0',
  `ost_token` varchar(64) character set utf8 collate utf8_bin NOT NULL default '',
  `ost_token_secret` varchar(64) character set utf8 collate utf8_bin NOT NULL default '',
  `ost_token_type` enum('request','access') default NULL,
  `ost_authorized` tinyint(1) NOT NULL default '0',
  `ost_referrer_host` varchar(128) NOT NULL default '',
  `ost_timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`ost_id`),
  UNIQUE KEY `ost_token` (`ost_token`),
  KEY `ost_osr_id_ref` (`ost_osr_id_ref`),
  KEY `ost_usa_id_ref` (`ost_usa_id_ref`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1985 ;

-- --------------------------------------------------------

--
-- Table structure for table `scores`
--

CREATE TABLE IF NOT EXISTS `scores` (
  `id` int(11) NOT NULL auto_increment,
  `subgame_id` varchar(255) default NULL,
  `device_id` varchar(255) NOT NULL default '',
  `email` varchar(255) NOT NULL default '',
  `name` varchar(255) NOT NULL default '',
  `value` int(11) NOT NULL default '0',
  `timestamp` datetime NOT NULL default '0000-00-00 00:00:00',
  `ip` varchar(100) NOT NULL default '',
  `country_code` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=cp1251 AUTO_INCREMENT=283 ;

-- --------------------------------------------------------

--
-- Table structure for table `subgames`
--

CREATE TABLE IF NOT EXISTS `subgames` (
  `id` int(11) NOT NULL auto_increment,
  `game_id` int(11) NOT NULL default '0',
  `subgame_title` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=cp1251 AUTO_INCREMENT=7 ;
