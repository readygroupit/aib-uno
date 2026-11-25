-- La foto di ciascuno (Il mio profilo): file in public/uploads/avatars, qui l'indirizzo.
ALTER TABLE `users` ADD COLUMN `avatar_path` VARCHAR(255) NULL;
