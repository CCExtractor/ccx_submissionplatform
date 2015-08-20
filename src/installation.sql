/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
CREATE DATABASE /*!32312 IF NOT EXISTS*/`ccx_sampleplatform` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `ccx_sampleplatform`;

/*Table structure for table `blacklist_extension` */

DROP TABLE IF EXISTS `blacklist_extension`;

CREATE TABLE `blacklist_extension` (
  `extension` varchar(10) NOT NULL,
  PRIMARY KEY (`extension`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

/*Table structure for table `ccextractor_reference` */

DROP TABLE IF EXISTS `ccextractor_reference`;

CREATE TABLE `ccextractor_reference` (
  `sample_id` int(11) NOT NULL,
  `ccx_version` int(11) NOT NULL,
  PRIMARY KEY (`sample_id`,`ccx_version`),
  KEY `FK_ccxreference_ccx` (`ccx_version`),
  CONSTRAINT `FK_ccxreference_ccx` FOREIGN KEY (`ccx_version`) REFERENCES `ccextractor_versions` (`id`),
  CONSTRAINT `FK_ccxreference_sample` FOREIGN KEY (`sample_id`) REFERENCES `sample` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

/*Table structure for table `ccextractor_versions` */

DROP TABLE IF EXISTS `ccextractor_versions`;

CREATE TABLE `ccextractor_versions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `version` varchar(10) NOT NULL,
  `released` date NOT NULL,
  `hash` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=latin1;

/*Table structure for table `ftpd` */

DROP TABLE IF EXISTS `ftpd`;

CREATE TABLE `ftpd` (
  `user_id` int(11) NOT NULL,
  `user` varchar(32) NOT NULL DEFAULT '',
  `status` enum('0','1') NOT NULL DEFAULT '0',
  `password` varchar(64) NOT NULL DEFAULT '',
  `dir` varchar(128) NOT NULL DEFAULT '' COMMENT 'Home directory of the user',
  `ipaccess` varchar(15) NOT NULL DEFAULT '*' COMMENT 'Restrict to ip address? * for everything',
  `QuotaFiles` int(11) NOT NULL DEFAULT '0' COMMENT 'Number of files allowed for this user',
  PRIMARY KEY (`user`),
  UNIQUE KEY `User` (`user`),
  UNIQUE KEY `FK_ftpd_user` (`user_id`),
  CONSTRAINT `FK_ftpd_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

/*Table structure for table `processing_messages` */

DROP TABLE IF EXISTS `processing_messages`;

CREATE TABLE `processing_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `message` varchar(150) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_processing_messages_user` (`user_id`),
  CONSTRAINT `FK_processing_messages_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=latin1;

/*Table structure for table `processing_queued` */

DROP TABLE IF EXISTS `processing_queued`;

CREATE TABLE `processing_queued` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `hash` varchar(128) NOT NULL,
  `extension` varchar(50) DEFAULT NULL,
  `original` varchar(150) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_processing_queue_user` (`user_id`),
  CONSTRAINT `FK_processing_queue_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=latin1;

/*Table structure for table `regression_tests` */

DROP TABLE IF EXISTS `regression_tests`;

CREATE TABLE `regression_tests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sample_id` int(11) NOT NULL,
  `command` varchar(200) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sample_id` (`sample_id`,`command`),
  CONSTRAINT `FK_regression_sample` FOREIGN KEY (`sample_id`) REFERENCES `sample` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=latin1;

/*Table structure for table `sample` */

DROP TABLE IF EXISTS `sample`;

CREATE TABLE `sample` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hash` varchar(128) NOT NULL,
  `extension` varchar(50) NOT NULL,
  `original_name` varchar(150) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hash` (`hash`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=latin1;

/*Table structure for table `sample_tests` */

DROP TABLE IF EXISTS `sample_tests`;

CREATE TABLE `sample_tests` (
  `sample_id` int(11) NOT NULL,
  `ccx_version` int(11) NOT NULL,
  `test_result` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`sample_id`,`ccx_version`),
  KEY `FK_sampletests_ccxversion` (`ccx_version`),
  CONSTRAINT `FK_sampletests_ccxversion` FOREIGN KEY (`ccx_version`) REFERENCES `ccextractor_versions` (`id`),
  CONSTRAINT `FK_sampletests_sample` FOREIGN KEY (`sample_id`) REFERENCES `sample` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

/*Table structure for table `upload` */

DROP TABLE IF EXISTS `upload`;

CREATE TABLE `upload` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `sample_id` int(11) NOT NULL,
  `ccx_used` int(11) NOT NULL,
  `platform` varchar(50) NOT NULL,
  `parameters` text NOT NULL,
  `notes` text NOT NULL,
  `additional_files` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `sample_id` (`sample_id`,`user_id`),
  KEY `FK_upload_ccx_version` (`ccx_used`),
  KEY `FK_upload_user` (`user_id`),
  CONSTRAINT `FK_upload_ccx_version` FOREIGN KEY (`ccx_used`) REFERENCES `ccextractor_versions` (`id`),
  CONSTRAINT `FK_upload_sample` FOREIGN KEY (`sample_id`) REFERENCES `sample` (`id`),
  CONSTRAINT `FK_upload_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=latin1;

/*Table structure for table `user` */

DROP TABLE IF EXISTS `user`;

CREATE TABLE `user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(500) NOT NULL,
  `github_linked` tinyint(1) NOT NULL DEFAULT '0',
  `admin` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=latin1;

/*Table structure for table `additional_file` */

DROP TABLE IF EXISTS `additional_file`;

CREATE TABLE `additional_file` (
  `id` int(11) NOT NULL,
  `sample_id` int(11) NOT NULL,
  `original_name` varchar(150) NOT NULL,
  `extension` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_additional_sample` (`sample_id`),
  CONSTRAINT `FK_additional_sample` FOREIGN KEY (`sample_id`) REFERENCES `sample` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
