-- Generation Time: Jun 14, 2024 at 10:03 AM

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `CAS_users`
--

-- --------------------------------------------------------

--
-- Table structure for table `T_UTILISATEURS`
--

CREATE TABLE `T_UTILISATEURS` (
                                  `UID` int(11) UNSIGNED NOT NULL,
                                  `USERNAME` varchar(100) NOT NULL,
                                  `PASSWORD` varchar(128) NOT NULL,
                                  `EMAIL` varchar(320) NOT NULL COMMENT 'http://tools.ietf.org/html/rfc3696#section-3',
                                  `CIV` varchar(255) DEFAULT NULL,
                                  `LASTNAME` varchar(100) NOT NULL,
                                  `FIRSTNAME` varchar(100) DEFAULT NULL,
                                  `MIDDLENAME` varchar(100) DEFAULT NULL,
                                  `URL` varchar(500) DEFAULT NULL,
                                  `PHONE` varchar(50) DEFAULT NULL,
                                  `FAX` varchar(50) DEFAULT NULL,
                                  `TIME_REGISTERED` timestamp NULL DEFAULT NULL COMMENT 'Date création du compte',
                                  `TIME_MODIFIED` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date modification du compte',
                                  `PHOTO` mediumblob,
                                  `FTP_HOME` varchar(255) DEFAULT NULL COMMENT 'Chemin du home FTP',
                                  `FTP_LAST_AUTH` datetime DEFAULT NULL COMMENT 'Dernière authentification par FTP',
                                  `FTP_LAST_USE` datetime DEFAULT NULL COMMENT 'Dernière utilisation du FTP',
                                  `VALID` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Comptes utilisateurs pour CAS' ROW_FORMAT=DYNAMIC;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `T_UTILISATEURS`
--
ALTER TABLE `T_UTILISATEURS`
    ADD PRIMARY KEY (`UID`),
  ADD UNIQUE KEY `U_USERNAME` (`USERNAME`),
  ADD KEY `PASSWORD` (`PASSWORD`),
  ADD KEY `VALID` (`VALID`),
  ADD KEY `FIRSTNAME` (`FIRSTNAME`),
  ADD KEY `LASTNAME` (`LASTNAME`),
  ADD KEY `EMAIL` (`EMAIL`(100));

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `T_UTILISATEURS`
--
ALTER TABLE `T_UTILISATEURS`
    MODIFY `UID` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;