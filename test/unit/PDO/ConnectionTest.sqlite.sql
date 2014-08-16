
DROP TABLE IF EXISTS `#__blog`;
DROP TABLE IF EXISTS `#__post`;
DROP TABLE IF EXISTS `#__tag`;
DROP TABLE IF EXISTS `#__post_tag`;

CREATE TABLE `#__blog` (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT,
	`title` TEXT NOT NULL,
	`created_at` INTEGER NOT NULL
);

CREATE TABLE `#__post` (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT,
	`blog_id` INTEGER NOT NULL,
	`title` TEXT NOT NULL,
	`content` TEXT NOT NULL,
	`created_at` INTEGER NOT NULL,
	FOREIGN KEY (`blog_id`) REFERENCES `#__blog` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE `#__tag` (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT,
	`name` TEXT NOT NULL
);

CREATE UNIQUE INDEX `#__tag_name` ON `#__tag` (`name`);

CREATE TABLE `#__post_tag` (
	`post_id` INTEGER NOT NULL,
	`tag_id` INTEGER NOT NULL,
	PRIMARY KEY (`post_id`, `tag_id`),
	FOREIGN KEY (`post_id`) REFERENCES `#__post` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
	FOREIGN KEY (`tag_id`) REFERENCES `#__tag` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
);
