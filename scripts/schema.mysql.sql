/**
 * RULE: replace TIMESTAMP NULL --> TIMESTAMP NULL
 */
-- DO NOT alter the lines above!!!

DROP TABLE IF EXISTS `t_scrap`;
DROP TABLE IF EXISTS `t_task`;
DROP TABLE IF EXISTS `t_project`;
DROP TABLE IF EXISTS `t_user`;


CREATE TABLE `t_user`
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

CREATE TABLE `t_project`
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

CREATE TABLE `t_task`
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

CREATE TABLE `t_scrap`
(
`taskId` INTEGER UNSIGNED,
`userId` INTEGER UNSIGNED NOT NULL,
`longScrap` TEXT,
`added` TIMESTAMP,
`updated` TIMESTAMP,
PRIMARY KEY (`taskId`)
);

CREATE INDEX `t_user_username_idx` ON `t_user`(`username`);
CREATE INDEX `t_project_userId_idxfk` ON `t_project`(`userId`);
ALTER TABLE `t_project` ADD FOREIGN KEY userId_idxfk (`userId`) REFERENCES `t_user` (`id`);

CREATE INDEX `t_task_userId_idxfk` ON `t_task`(`userId`);
ALTER TABLE `t_task` ADD FOREIGN KEY userId_idxfk_1 (`userId`) REFERENCES `t_user` (`id`);

CREATE INDEX `t_task_projectId_idxfk` ON `t_task`(`projectId`);
ALTER TABLE `t_task` ADD FOREIGN KEY projectId_idxfk (`projectId`) REFERENCES `t_project` (`id`);

ALTER TABLE `t_scrap` ADD FOREIGN KEY taskId_idxfk (`taskId`) REFERENCES `t_task` (`id`);

CREATE INDEX `t_scrap_userId_idxfk` ON `t_scrap`(`userId`);
ALTER TABLE `t_scrap` ADD FOREIGN KEY userId_idxfk_2 (`userId`) REFERENCES `t_user` (`id`);
