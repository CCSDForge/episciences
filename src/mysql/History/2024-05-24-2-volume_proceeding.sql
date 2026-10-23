--
-- Table structure for table `volume_proceeding`
--

CREATE TABLE `volume_proceeding` (
                                     `VID` int UNSIGNED NOT NULL,
                                     `SETTING` varchar(200) NOT NULL,
                                     `VALUE` text CHARACTER SET utf8mb3 COLLATE utf8_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `volume_proceeding`
--
ALTER TABLE `volume_proceeding`
    ADD PRIMARY KEY (`VID`,`SETTING`),
  ADD KEY `FK_RVID0_idx` (`VID`);
COMMIT;
