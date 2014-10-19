
DROP TABLE IF EXISTS `#__post_tag`;
DROP TABLE IF EXISTS `#__post`;
DROP TABLE IF EXISTS `#__tag`;
DROP TABLE IF EXISTS `#__blog`;

CREATE TABLE `#__blog` (
	`id` SERIAL,
	`title` character(250) NOT NULL,
	`created_at` integer NOT NULL,
	PRIMARY KEY (`id`)
);

CREATE TABLE `#__post` (
	`id` SERIAL,
	`blog_id` integer NOT NULL,
	`title` character(250) NOT NULL,
	`content` text NOT NULL,
	`created_at` integer NOT NULL,
	PRIMARY KEY (`id`),
	FOREIGN KEY (`blog_id`) REFERENCES `#__blog` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE `#__tag` (
	`id` SERIAL,
	`name` character(250) NOT NULL,
	PRIMARY KEY (`id`)
);

CREATE UNIQUE INDEX `#__tag_name` ON `#__tag` (`name`);

CREATE TABLE `#__post_tag` (
	`post_id` integer NOT NULL,
	`tag_id` integer NOT NULL,
	PRIMARY KEY (`post_id`, `tag_id`),
	FOREIGN KEY (`post_id`) REFERENCES `#__post` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
	FOREIGN KEY (`tag_id`) REFERENCES `#__tag` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
);
