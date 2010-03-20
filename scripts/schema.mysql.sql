/**
 * RULE: replace TIMESTAMP NULL --> TIMESTAMP NULL
 */
-- DO NOT alter the lines above!!!

DROP TABLE IF EXISTS `Scraps`;
DROP TABLE IF EXISTS `Tasks`;
DROP TABLE IF EXISTS `Projects`;
DROP TABLE IF EXISTS `Users`;


CREATE TABLE `Users`
(
`id` INTEGER UNSIGNED AUTO_INCREMENT,
`username` VARCHAR(30) NOT NULL,
`password` VARCHAR(255),
`email` VARCHAR(30),
`emailTmp` VARCHAR(30) DEFAULT NULL,
`tzDiff` SMALLINT DEFAULT NULL,
`activeTask` INTEGER UNSIGNED DEFAULT NULL,
`proUntil` TIMESTAMP NULL,
`credits` INTEGER,
`added` TIMESTAMP,
`updated` TIMESTAMP,
PRIMARY KEY (`id`)
);

CREATE TABLE `Projects`
(
`id` INTEGER UNSIGNED AUTO_INCREMENT,
`userId` INTEGER UNSIGNED NOT NULL,
`title` VARCHAR(30) NOT NULL,
`flags`  SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
`finished` TIMESTAMP NULL,
`added` TIMESTAMP,
`updated` TIMESTAMP,
PRIMARY KEY (`id`)
);

CREATE TABLE `Tasks`
(
`id` INTEGER UNSIGNED AUTO_INCREMENT,
`userId` INTEGER UNSIGNED NOT NULL,
`projectId` INTEGER UNSIGNED DEFAULT NULL,
`liveline` TIMESTAMP NULL,
`deadline` TIMESTAMP NULL,
`lastStarted` INTEGER UNSIGNED DEFAULT NULL,
`lastStopped` INTEGER UNSIGNED DEFAULT NULL,
`duration` INTEGER UNSIGNED DEFAULT 0 NOT NULL,
`title` VARCHAR(60) NOT NULL,
`flags` SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
`scrap` VARCHAR(60) NOT NULL,
`added` TIMESTAMP,
`updated` TIMESTAMP,
PRIMARY KEY (`id`)
);

CREATE TABLE `Scraps`
(
`taskId` INTEGER UNSIGNED,
`userId` INTEGER UNSIGNED NOT NULL,
`longScrap` TEXT,
`added` TIMESTAMP,
`updated` TIMESTAMP,
PRIMARY KEY (`taskId`)
);

CREATE INDEX `Users_username_idx` ON `Users`(`username`);
CREATE INDEX `Projects_userId_idxfk` ON `Projects`(`userId`);
ALTER TABLE `Projects` ADD FOREIGN KEY userId_idxfk (`userId`) REFERENCES `Users` (`id`);

CREATE INDEX `Tasks_userId_idxfk` ON `Tasks`(`userId`);
ALTER TABLE `Tasks` ADD FOREIGN KEY userId_idxfk_1 (`userId`) REFERENCES `Users` (`id`);

CREATE INDEX `Tasks_projectId_idxfk` ON `Tasks`(`projectId`);
ALTER TABLE `Tasks` ADD FOREIGN KEY projectId_idxfk (`projectId`) REFERENCES `Projects` (`id`);

ALTER TABLE `Scraps` ADD FOREIGN KEY taskId_idxfk (`taskId`) REFERENCES `Tasks` (`id`);

CREATE INDEX `Scraps_userId_idxfk` ON `Scraps`(`userId`);
ALTER TABLE `Scraps` ADD FOREIGN KEY userId_idxfk_2 (`userId`) REFERENCES `Users` (`id`);
