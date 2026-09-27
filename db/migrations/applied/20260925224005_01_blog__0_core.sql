CREATE TABLE `blog_posts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `title` VARCHAR(190) NOT NULL,
  `slug` VARCHAR(190) NOT NULL,
  `excerpt` VARCHAR(255) NOT NULL,
  `body` TEXT NOT NULL,
  `cover_image_url` VARCHAR(255) NOT NULL,
  `author_user_id` INT UNSIGNED NOT NULL,
  `stage` VARCHAR(50) NOT NULL,
  `published_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_blog_posts_author_user_id` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

